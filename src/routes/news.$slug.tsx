import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { translationProvider } from "@/services/translation";
import { useI18n } from "@/hooks/useI18n";
import { useState } from "react";
import { Bookmark, Heart, Share2, Flag, Languages, Clock, Eye, MessageCircle } from "lucide-react";

export const Route = createFileRoute("/news/$slug")({
  loader: ({ params }) => {
    const article = demoData.articles.find((a) => a.slug === params.slug);
    if (!article) throw notFound();
    return article;
  },
  head: ({ loaderData }) =>
    buildHead({
      title: loaderData?.title ?? "Article",
      description: loaderData?.excerpt,
      canonical: `/news/${loaderData?.slug}`,
      ogImage: loaderData?.featuredImage,
    }),
  component: ArticlePage,
});

function ArticlePage() {
  const article = Route.useLoaderData();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const { lang } = useI18n();
  const [translated, setTranslated] = useState<string | null>(null);
  const artists = demoData.artists.filter((a) => article.relatedArtistIds.includes(a.id));
  const related = demoData.articles.filter((a) => a.id !== article.id).slice(0, 4);
  const comments = demoData.comments.filter((c) => c.articleId === article.id);

  const gate = (action: () => void) => () => (user ? action() : show("Log in to interact"));

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
      <div className="mt-4 flex flex-wrap gap-2">
        <Button size="sm" variant="outline" onClick={gate(() => {})}><Heart className="size-3" /> React ({article.reactionCount})</Button>
        <Button size="sm" variant="outline" onClick={gate(() => {})}><Bookmark className="size-3" /> Save</Button>
        <Button size="sm" variant="outline" onClick={() => navigator.share?.({ title: article.title, url: location.href }).catch(() => {})}><Share2 className="size-3" /> Share</Button>
        <Button size="sm" variant="outline" onClick={async () => { const r = await translationProvider.translate(article.content, lang as string); setTranslated(r.text); }}>
          <Languages className="size-3" /> Translate
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

      {/* sidebar ad slot inline */}
      <div className="my-8 rounded-xl border border-dashed border-border bg-muted/40 p-4 text-center text-xs text-muted-foreground">Sponsored — inline ad slot</div>

      {/* Related artists */}
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

      {/* Comments */}
      <section className="mt-12">
        <h2 className="font-display text-2xl font-bold mb-4">Comments ({comments.length})</h2>
        {user ? (
          <form className="mb-6" onSubmit={(e) => e.preventDefault()}>
            <textarea placeholder="Add a comment..." className="w-full p-3 rounded-md bg-background border border-input min-h-24" />
            <div className="mt-2 flex justify-end"><Button>Post comment</Button></div>
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

      <section className="mt-12">
        <h2 className="font-display text-2xl font-bold mb-4">Related articles</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          {related.slice(0, 4).map((a) => <ArticleCard key={a.id} article={a} variant="compact" />)}
        </div>
      </section>
    </article>
  );
}
