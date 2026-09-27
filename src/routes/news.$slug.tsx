import { createFileRoute, Link, notFound, useRouter } from "@tanstack/react-router";
import { buildHead, breadcrumbLd } from "@/components/layout/seo";
import { LocalTime } from "@/components/layout/LocalTime";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { ArticleSummary } from "@/components/articles/ArticleSummary";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { useI18n } from "@/hooks/useI18n";
import { useEffect, useMemo, useRef, useState } from "react";
import { Bookmark, BookmarkCheck, Heart, Flag, Languages, Clock, Eye, MessageCircle, Loader2, ShieldAlert, CornerDownRight } from "lucide-react";
import { aiHelpers } from "@/services/ai/helpers";
import { cmsProvider } from "@/services/cms";
import { personalization } from "@/services/personalization";
import { gamification } from "@/services/gamification";
import { notifications } from "@/services/notifications/store";
import { AdSlot } from "@/components/ads/AdSlot";
import { bookmarks } from "@/services/bookmarks";
import { recentlyViewed } from "@/services/recentlyViewed";
import { RecentlyViewedRail } from "@/components/articles/RecentlyViewedRail";
import { ReadingProgress } from "@/components/articles/ReadingProgress";
import { ArticleToc, extractHeadings } from "@/components/articles/ArticleToc";
import { useProseLightbox } from "@/components/articles/Lightbox";
import { ShareButtons } from "@/components/articles/ShareButtons";
import { useRuntimeData } from "@/services/cms/runtimeData";
import type { Comment } from "@/types";
import { communityProvider } from "@/services/community";

export const Route = createFileRoute("/news/$slug")({
  loader: async ({ params }) => {
    const article = await cmsProvider.getArticleBySlug(params.slug);
    if (!article) throw notFound();
    return article;
  },
  head: ({ loaderData }) => {
    if (!loaderData) return buildHead({ title: "Article" });
    const a = loaderData;
    const canonical = `/news/${a.slug}`;
    const newsArticleLd = {
      "@context": "https://schema.org",
      "@type": "NewsArticle",
      headline: a.title,
      description: a.excerpt,
      image: a.featuredImage ? [a.featuredImage] : undefined,
      datePublished: a.publishedAt,
      dateModified: a.modifiedAt ?? a.publishedAt,
      author: [{ "@type": "Person", name: a.author }],
      publisher: { "@type": "Organization", name: "KpopBlog" },
      mainEntityOfPage: { "@type": "WebPage", "@id": canonical },
      articleSection: a.category,
      keywords: a.tags?.join(", "),
      inLanguage: a.language,
    };
    const crumbs = breadcrumbLd([
      { name: "Home", path: "/" },
      { name: "News", path: "/latest" },
      { name: a.category, path: `/category/${a.category}` },
      { name: a.title, path: canonical },
    ]);
    const head = buildHead({
      title: a.title,
      description: a.excerpt,
      canonical,
      ogImage: a.featuredImage,
      ogType: "article",
      jsonLd: [newsArticleLd, crumbs],
    });
    if (a.featuredImage) head.links.push({ rel: "preload", as: "image", href: a.featuredImage, fetchpriority: "high" });
    return head;
  },
  component: ArticlePage,
});

function ArticlePage() {
  const article = Route.useLoaderData();
  const { data } = useRuntimeData();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const { lang } = useI18n();
  const [translated, setTranslated] = useState<string | null>(null);
  const [translating, setTranslating] = useState(false);
  const [draft, setDraft] = useState("");
  const [posting, setPosting] = useState(false);
  const [modError, setModError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [comments, setComments] = useState<Comment[]>([]);
  const [commentsLoading, setCommentsLoading] = useState(false);
  const [replyTo, setReplyTo] = useState<string | null>(null);
  const [replyDraft, setReplyDraft] = useState("");
  const [replyPosting, setReplyPosting] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);
  const [reportReason, setReportReason] = useState("");
  const [reporting, setReporting] = useState(false);
  const artists = data.artists.filter((a) => article.relatedArtistIds.includes(a.id) || article.relatedArtistIds.includes(a.slug));
  // Same artists first, then the same category, then the newest stories.
  const related = data.articles
    .filter((a) => a.id !== article.id)
    .map((a) => ({
      a,
      score:
        a.relatedArtistIds.filter((id) => id && article.relatedArtistIds.includes(id)).length * 10 +
        (a.category === article.category ? 3 : 0),
    }))
    .sort((x, y) => y.score - x.score || +new Date(y.a.publishedAt) - +new Date(x.a.publishedAt))
    .map((entry) => entry.a)
    .slice(0, 4);
  const rootComments = comments.filter((comment) => !comment.parentId);
  const articleRef = useRef<HTMLElement>(null);
  const contentRef = useRef<HTMLDivElement>(null);
  const router = useRouter();
  // Links inside the article body are plain HTML; keep same-site ones inside the app
  // (no full reload) and let external source credits open in a new tab.
  const handleContentClick = (event: React.MouseEvent<HTMLDivElement>) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const anchor = (event.target as HTMLElement).closest("a");
    if (!anchor || anchor.target === "_blank" || anchor.hasAttribute("download")) return;
    const url = new URL(anchor.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    event.preventDefault();
    router.history.push(url.pathname + url.search + url.hash);
  };
  const { html: contentHtml, headings } = useMemo(() => extractHeadings(article.content), [article.content]);
  const { lightbox } = useProseLightbox(contentRef);

  useEffect(() => {
    if (personalization.hasConsent()) {
      personalization.recordView({ id: article.id, tags: article.tags });
    }
    recentlyViewed.push({ id: article.id, slug: article.slug, title: article.title, image: article.featuredImage });
    setSaved(bookmarks.has(article.id));
    gamification.award(2, "articlesRead");
    cmsProvider.recordEngagement?.(article.slug, "view");
  }, [article.id, article.slug, article.tags, article.title, article.featuredImage]);

  useEffect(() => {
    if (!cmsProvider.listArticleComments) {
      setComments(data.comments.filter((comment) => comment.articleId === article.id));
      return;
    }
    let active = true;
    setCommentsLoading(true);
    setModError(null);
    void cmsProvider.listArticleComments(article.slug)
      .then((items) => { if (active) setComments(items); })
      .catch((requestError) => { if (active) setModError(requestError instanceof Error ? requestError.message : "Comments could not be loaded."); })
      .finally(() => { if (active) setCommentsLoading(false); });
    return () => { active = false; };
  }, [article.id, article.slug, data.comments]);

  const gate = (action: () => void) => () => (user ? action() : show("Log in to interact"));

  function onToggleSave() {
    const nowSaved = bookmarks.toggle({
      id: article.id,
      slug: article.slug,
      title: article.title,
      image: article.featuredImage,
    });
    setSaved(nowSaved);
    if (nowSaved) {
      gamification.award(1, "articlesRead");
      notifications.notify({ kind: "reply", title: "Saved", body: article.title, href: "/bookmarks" });
    }
  }

  async function onSubmitReply(parentId: string) {
    if (!replyDraft.trim()) return;
    setReplyPosting(true);
    setModError(null);
    try {
      const result = await cmsProvider.postComment?.(article.slug, replyDraft.trim(), parentId);
      if (!result?.ok) {
        setModError(result?.error ?? "Reply could not be saved.");
        return;
      }
      if (result.item && !result.pending) setComments((current) => [...current, result.item!]);
      setReplyDraft("");
      setReplyTo(null);
      gamification.award(3, "commentsPosted");
    } finally {
      setReplyPosting(false);
    }
  }


  async function onTranslate() {
    setTranslating(true);
    try {
      const r = await aiHelpers.translate(article.content, lang as string);
      setTranslated(r.text);
    } finally {
      setTranslating(false);
    }
  }

  async function onPostComment(e: React.FormEvent) {
    e.preventDefault();
    setModError(null);
    if (!draft.trim()) return;
    setPosting(true);
    try {
      const check = await aiHelpers.moderate(draft);
      if (!check.allowed) {
        setModError(check.reason || `Comment blocked by AI moderation: ${check.reasons.join(", ")}`);
        return;
      }
      const res = await cmsProvider.postComment?.(article.slug, draft);
      if (res && !res.ok) {
        setModError(res.error ?? "Failed to post comment");
        return;
      }
      if (res?.item && !res.pending) setComments((current) => [res.item!, ...current]);
      gamification.award(5, "commentsPosted");
      notifications.notify({ kind: "reply", title: "Comment posted", body: draft.slice(0, 80), href: `/news/${article.slug}` });
      setDraft("");
    } finally {
      setPosting(false);
    }
  }

  async function onReportArticle(e: React.FormEvent) {
    e.preventDefault();
    if (!reportReason.trim()) return;
    setReporting(true);
    setModError(null);
    try {
      await communityProvider.report({ targetType: "article", targetId: article.id, reason: reportReason.trim() });
      setReportReason("");
      setReportOpen(false);
      notifications.notify({ kind: "system", title: "Report submitted", body: "Moderators will review this article.", href: `/news/${article.slug}` });
    } catch (reportError) {
      setModError(reportError instanceof Error ? reportError.message : "Report could not be submitted.");
    } finally {
      setReporting(false);
    }
  }

  return (
    <>

    <ReadingProgress targetRef={articleRef} />
    {lightbox}
    <article ref={articleRef} className="mx-auto max-w-6xl px-4 py-8 lg:grid lg:grid-cols-[1fr_220px] lg:gap-10">
      <div className="min-w-0">
      <nav className="text-xs text-muted-foreground mb-4">
        <Link to="/" className="hover:text-foreground">Home</Link> / <Link to="/latest" className="hover:text-foreground">News</Link> / <span>{article.category}</span>
      </nav>
      <div className="text-xs uppercase tracking-wider text-primary font-semibold mb-2">{article.category}</div>
      <h1 className="font-display text-3xl md:text-5xl font-bold leading-tight">{article.title}</h1>
      {article.subtitle && <p className="mt-3 text-lg text-muted-foreground">{article.subtitle}</p>}
      <div className="mt-4 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
        <div className="flex items-center gap-2">
          {article.authorAvatar && <img src={article.authorAvatar} alt="" className="size-7 rounded-full" />}
          <Link to="/author/$slug" params={{ slug: article.author.toLowerCase().replace(/\s+/g, "-") }} className="hover:text-foreground">{article.author}</Link>
        </div>
        <span><LocalTime value={article.publishedAt} mode="date" /></span>
        <span className="flex items-center gap-1"><Clock className="size-3" />{article.readingTime} min read</span>
        <span className="flex items-center gap-1"><Eye className="size-3" />{article.viewCount.toLocaleString()}</span>
        <span className="flex items-center gap-1"><MessageCircle className="size-3" />{article.commentCount}</span>
      </div>
      <div className="mt-6 rounded-2xl overflow-hidden">
        <img src={article.featuredImage} alt={article.title} fetchPriority="high" decoding="async" width={1280} height={720} className="w-full aspect-video object-cover" />
      </div>

      <ArticleSummary text={article.content} locale={lang as string} />

      <div className="mt-4 flex flex-wrap gap-2">
        <Button size="sm" variant="outline" onClick={gate(() => { gamification.award(1); cmsProvider.recordEngagement?.(article.slug, "reaction"); })}>
          <Heart className="size-3" /> React ({article.reactionCount})
        </Button>
        <Button size="sm" variant={saved ? "default" : "outline"} onClick={onToggleSave} aria-pressed={saved}>
          {saved ? <BookmarkCheck className="size-3" /> : <Bookmark className="size-3" />} {saved ? "Saved" : "Save"}
        </Button>
        <Button size="sm" variant="outline" disabled={translating} onClick={onTranslate}>
          {translating ? <Loader2 className="size-3 animate-spin" /> : <Languages className="size-3" />} Translate
        </Button>
        <Button size="sm" variant="ghost" onClick={gate(() => setReportOpen((open) => !open))}><Flag className="size-3" /> Report</Button>
      </div>
      {reportOpen && (
        <form onSubmit={onReportArticle} className="mt-3 rounded-xl border border-border bg-card p-3">
          <label className="text-sm font-medium">Why should moderators review this article?<textarea value={reportReason} onChange={(event) => setReportReason(event.target.value)} minLength={3} maxLength={500} required className="mt-2 w-full min-h-20 rounded-md border border-input bg-background p-2 text-sm" /></label>
          <div className="mt-2 flex justify-end gap-2"><Button type="button" size="sm" variant="ghost" onClick={() => setReportOpen(false)}>Cancel</Button><Button type="submit" size="sm" disabled={reporting || reportReason.trim().length < 3}>{reporting && <Loader2 className="size-3 animate-spin" />} Submit report</Button></div>
        </form>
      )}
      <div className="mt-3">
        <ShareButtons title={article.title} />
      </div>
      {translated && (
        <div className="mt-4 p-3 rounded-md bg-muted/40 text-xs">
          <div className="font-medium mb-1">Machine translated. Original language: {article.language.toUpperCase()}</div>
          <div className="line-clamp-3 text-muted-foreground">{translated.replace(/<[^>]+>/g, "").slice(0, 240)}…</div>
        </div>
      )}
      <div ref={contentRef} onClick={handleContentClick} className="prose prose-invert max-w-none mt-8 dark:prose-invert scroll-mt-24" dangerouslySetInnerHTML={{ __html: contentHtml }} />

      <AdSlot slotId={`article-${article.slug}-inline`} variant="in-article" className="my-8" />

      {artists.length > 0 && (
        <section className="mt-8">
          <h2 className="font-display text-xl font-bold mb-3">Related artists</h2>
          <div className="flex flex-wrap gap-2">
            {artists.map((a) => (
              <Link key={a.id} to="/artist/$slug" params={{ slug: a.slug }} className="px-3 py-1.5 rounded-full bg-accent hover:bg-primary hover:text-primary-foreground text-sm">{a.name}</Link>
            ))}
          </div>
        </section>
      )}

      <section className="mt-12">
        <h2 className="font-display text-2xl font-bold mb-4">Comments ({comments.length})</h2>
        {user ? (
          <form className="mb-6" onSubmit={onPostComment}>
            <textarea
              placeholder="Add a comment..."
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              maxLength={4000}
              className="w-full p-3 rounded-md bg-background border border-input min-h-24"
            />
            {modError && (
              <div className="mt-2 text-xs text-destructive flex items-center gap-1.5">
                <ShieldAlert className="size-3" /> {modError}
              </div>
            )}
            <div className="mt-2 flex items-center justify-between">
              <span className="text-[11px] text-muted-foreground">AI-moderated · {draft.length}/4000</span>
              <Button disabled={posting || !draft.trim()}>
                {posting ? <Loader2 className="size-3 animate-spin" /> : null} Post comment
              </Button>
            </div>
          </form>
        ) : (
          <div className="mb-6 p-4 rounded-md bg-muted/40 text-sm flex items-center justify-between">
            <span>Join the discussion.</span>
            <Button size="sm" onClick={() => show("Log in to comment")}>Log in to comment</Button>
          </div>
        )}
        <div className="space-y-4">
          {commentsLoading && <div className="py-6 text-sm text-muted-foreground">Loading comments…</div>}
          {!commentsLoading && rootComments.length === 0 && <div className="py-6 text-sm text-muted-foreground">No published comments yet.</div>}
          {rootComments.map((c) => {
            const u = c.author;
            const childReplies = comments.filter((reply) => reply.parentId === c.id);
            return (
              <div key={c.id} className="flex gap-3">
                {u?.avatar ? <img src={u.avatar} alt="" loading="lazy" decoding="async" width={36} height={36} className="size-9 rounded-full" /> : <div className="size-9 rounded-full bg-muted" aria-hidden="true" />}
                <div className="flex-1">
                  <div className="text-sm"><span className="font-semibold">{u?.displayName ?? "Community member"}</span> <span className="text-xs text-muted-foreground">· {new Date(c.createdAt).toLocaleTimeString()}</span></div>
                  <p className="text-sm">{c.body}</p>
                  <div className="text-xs text-muted-foreground mt-1 flex gap-3 items-center">
                    <button
                      className="hover:text-primary"
                      onClick={gate(() => setReplyTo(replyTo === c.id ? null : c.id))}
                    >
                      Reply
                    </button>
                  </div>

                  {childReplies.length > 0 && (
                    <ul className="mt-3 space-y-2 border-l border-border pl-3">
                      {childReplies.map((r) => (
                        <li key={r.id} className="text-sm flex gap-2">
                          <CornerDownRight className="size-3 mt-1 shrink-0 text-muted-foreground" />
                          <div>
                            <div className="text-xs text-muted-foreground">{r.author?.displayName ?? "Community member"} · {new Date(r.createdAt).toLocaleTimeString()}</div>
                            <p>{r.body}</p>
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}

                  {replyTo === c.id && user && (
                    <div className="mt-3">
                      <textarea
                        autoFocus
                        value={replyDraft}
                        onChange={(e) => setReplyDraft(e.target.value)}
                        placeholder={`Reply to ${u?.displayName ?? "community member"}...`}
                        maxLength={1000}
                        className="w-full p-2 text-sm rounded-md bg-background border border-input min-h-16"
                      />
                      <div className="mt-1 flex justify-end gap-2">
                        <Button size="sm" variant="ghost" onClick={() => { setReplyTo(null); setReplyDraft(""); }}>Cancel</Button>
                        <Button size="sm" onClick={() => void onSubmitReply(c.id)} disabled={replyPosting || !replyDraft.trim()}>{replyPosting && <Loader2 className="size-3 animate-spin" />} Post reply</Button>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </section>


      <AdSlot slotId={`article-${article.slug}-pre-related`} variant="rectangle" />

      <section className="mt-12">
        <h2 className="font-display text-2xl font-bold mb-4">Related articles</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          {related.slice(0, 4).map((a) => <ArticleCard key={a.id} article={a} variant="compact" />)}
        </div>
      </section>

      <RecentlyViewedRail excludeId={article.id} />
      </div>
      <aside className="hidden lg:block">
        <div className="sticky top-24">
          <ArticleToc headings={headings} />
        </div>
      </aside>
    </article>
    </>
  );
}
