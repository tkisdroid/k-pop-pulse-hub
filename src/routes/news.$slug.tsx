import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead, breadcrumbLd } from "@/components/layout/seo";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { ArticleSummary } from "@/components/articles/ArticleSummary";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { useI18n } from "@/hooks/useI18n";
import { useEffect, useState } from "react";
import { Bookmark, Heart, Share2, Flag, Languages, Clock, Eye, MessageCircle, Loader2, ShieldAlert } from "lucide-react";
import { aiHelpers } from "@/services/ai/helpers";
import { cmsProvider } from "@/services/cms";
import { personalization } from "@/services/personalization";
import { gamification } from "@/services/gamification";
import { notifications } from "@/services/notifications/store";
import { AdSlot } from "@/components/ads/AdSlot";

export const Route = createFileRoute("/news/$slug")({
  loader: ({ params }) => {
    const article = demoData.articles.find((a) => a.slug === params.slug);
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
    return buildHead({
      title: a.title,
      description: a.excerpt,
      canonical,
      ogImage: a.featuredImage,
      ogType: "article",
      jsonLd: [newsArticleLd, crumbs],
    });
  },
  component: ArticlePage,
});

function ArticlePage() {
  const article = Route.useLoaderData();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const { lang } = useI18n();
  const [translated, setTranslated] = useState<string | null>(null);
  const [translating, setTranslating] = useState(false);
  const [draft, setDraft] = useState("");
  const [posting, setPosting] = useState(false);
  const [modError, setModError] = useState<string | null>(null);
  const artists = demoData.artists.filter((a) => article.relatedArtistIds.includes(a.id));
  const related = demoData.articles.filter((a) => a.id !== article.id).slice(0, 4);
  const comments = demoData.comments.filter((c) => c.articleId === article.id);

  useEffect(() => {
    if (personalization.hasConsent()) {
      personalization.recordView({ id: article.id, tags: article.tags });
    }
    gamification.award(2, "articlesRead");
    cmsProvider.recordEngagement?.(article.slug, "view");
  }, [article.id, article.slug, article.tags]);

  const gate = (action: () => void) => () => (user ? action() : show("Log in to interact"));

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
      gamification.award(5, "commentsPosted");
      notifications.notify({ kind: "reply", title: "Comment posted", body: draft.slice(0, 80), href: `/news/${article.slug}` });
      setDraft("");
    } finally {
      setPosting(false);
    }
  }

  return (
    <article className="mx-auto max-w-4xl px-4 py-8">
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
        <span>{new Date(article.publishedAt).toLocaleDateString()}</span>
        <span className="flex items-center gap-1"><Clock className="size-3" />{article.readingTime} min</span>
        <span className="flex items-center gap-1"><Eye className="size-3" />{article.viewCount.toLocaleString()}</span>
        <span className="flex items-center gap-1"><MessageCircle className="size-3" />{article.commentCount}</span>
      </div>
      <div className="mt-6 rounded-2xl overflow-hidden">
        <img src={article.featuredImage} alt={article.title} className="w-full aspect-video object-cover" />
      </div>

      <ArticleSummary text={article.content} locale={lang as string} />

      <div className="mt-4 flex flex-wrap gap-2">
        <Button size="sm" variant="outline" onClick={gate(() => { gamification.award(1); cmsProvider.recordEngagement?.(article.slug, "reaction"); })}>
          <Heart className="size-3" /> React ({article.reactionCount})
        </Button>
        <Button size="sm" variant="outline" onClick={gate(() => {})}><Bookmark className="size-3" /> Save</Button>
        <Button size="sm" variant="outline" onClick={() => navigator.share?.({ title: article.title, url: location.href }).catch(() => {})}><Share2 className="size-3" /> Share</Button>
        <Button size="sm" variant="outline" disabled={translating} onClick={onTranslate}>
          {translating ? <Loader2 className="size-3 animate-spin" /> : <Languages className="size-3" />} Translate
        </Button>
        <Button size="sm" variant="ghost" onClick={gate(() => {})}><Flag className="size-3" /> Report</Button>
      </div>
      {translated && (
        <div className="mt-4 p-3 rounded-md bg-muted/40 text-xs">
          <div className="font-medium mb-1">Machine translated. Original language: {article.language.toUpperCase()}</div>
          <div className="line-clamp-3 text-muted-foreground">{translated.replace(/<[^>]+>/g, "").slice(0, 240)}…</div>
        </div>
      )}
      <div className="prose prose-invert max-w-none mt-8 dark:prose-invert" dangerouslySetInnerHTML={{ __html: article.content }} />

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
          {comments.map((c) => {
            const u = demoData.users.find((x) => x.id === c.authorId)!;
            return (
              <div key={c.id} className="flex gap-3">
                <img src={u.avatar} alt="" className="size-9 rounded-full" />
                <div className="flex-1">
                  <div className="text-sm"><span className="font-semibold">{u.displayName}</span> <span className="text-xs text-muted-foreground">· {new Date(c.createdAt).toLocaleTimeString()}</span></div>
                  <p className="text-sm">{c.body}</p>
                  <div className="text-xs text-muted-foreground mt-1 flex gap-3">
                    <button className="hover:text-primary">♥ {c.reactions}</button>
                    <button className="hover:text-primary">Reply</button>
                  </div>
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
    </article>
  );
}
