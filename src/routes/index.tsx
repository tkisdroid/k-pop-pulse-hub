import { createFileRoute, Link } from "@tanstack/react-router";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { ForYouSection } from "@/components/articles/ForYouSection";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { Button } from "@/components/ui/button";
import { buildHead } from "@/components/layout/seo";
import { Flame, Calendar, Vote, MessageSquare, ArrowRight } from "lucide-react";
import { AdSlot } from "@/components/ads/AdSlot";
import { NewsletterCTA } from "@/components/newsletter/NewsletterCTA";
import { DailyQuizWidget } from "@/components/quiz/DailyQuizWidget";
import { NotifyButton } from "@/components/notifications/NotifyButton";
import { useState } from "react";

export const Route = createFileRoute("/")({
  head: () => {
    const head = buildHead({ title: "Home", description: "Latest K-pop news, comebacks, artists and fan discussions.", canonical: "/" });
    return head;
  },
  component: Index,
});

function Index() {
  const { data, isLoading, error } = useRuntimeData();
  const [latestCategory, setLatestCategory] = useState("All");
  if (isLoading) return <p className="mx-auto max-w-7xl px-4 py-20 text-center text-muted-foreground">Loading current K-pop coverage…</p>;
  if (error) return <p className="mx-auto max-w-7xl px-4 py-20 text-center text-destructive" role="alert">{error}</p>;
  if (data.articles.length === 0) return <p className="mx-auto max-w-7xl px-4 py-20 text-center text-muted-foreground">No published articles yet.</p>;

  const now = Date.now();
  const featured = data.articles[0];
  const secondary = data.articles.slice(1, 4);
  // Most-read stories from the last week; fall back to the newest when nothing has views yet.
  const weekAgo = now - 7 * 86400000;
  const trending = [...data.articles]
    .filter((article) => +new Date(article.publishedAt) >= weekAgo)
    .sort((a, b) => b.viewCount - a.viewCount || +new Date(b.publishedAt) - +new Date(a.publishedAt))
    .slice(0, 5);
  if (trending.length < 5) trending.push(...data.articles.filter((a) => !trending.includes(a)).slice(0, 5 - trending.length));
  const categories = ["All", ...Array.from(new Set(data.articles.map((article) => article.category).filter(Boolean)))];
  const latest = data.articles.filter((article) => latestCategory === "All" || article.category === latestCategory).slice(0, 9);
  const upcoming = data.comebacks
    .filter((c) => +new Date(c.releaseAt) >= now - 86400000)
    .sort((a, b) => +new Date(a.releaseAt) - +new Date(b.releaseAt))
    .slice(0, 5);
  // Spotlight the artists with the most coverage right now.
  const coverage = new Map<string, number>();
  data.articles.forEach((article) => article.relatedArtistIds.forEach((id) => coverage.set(id, (coverage.get(id) ?? 0) + 1)));
  const spotlightArtists = [...data.artists]
    .sort((a, b) => (coverage.get(b.slug) ?? coverage.get(b.id) ?? 0) - (coverage.get(a.slug) ?? coverage.get(a.id) ?? 0))
    .slice(0, 3);
  const hotThreads = [...data.threads]
    .sort((a, b) => Number(!!b.pinned) - Number(!!a.pinned) || +new Date(b.lastActivityAt || b.createdAt) - +new Date(a.lastActivityAt || a.createdAt))
    .slice(0, 6);
  const polls = data.polls.slice(0, 2);
  const community = data.community.slice(0, 4);

  return (
    <div>
      {/* Breaking ticker */}
      <div className="bg-card border-b border-border overflow-hidden">
        <div className="mx-auto max-w-7xl px-4 py-2 flex items-center gap-4">
          <span className="shrink-0 px-2 py-0.5 rounded text-xs font-bold bg-destructive text-destructive-foreground">BREAKING</span>
          <div className="flex gap-6 overflow-x-auto scrollbar-hide text-sm">
            {data.articles.slice(0, 6).map((a) => (
              <Link key={a.id} to="/news/$slug" params={{ slug: a.slug }} className="whitespace-nowrap hover:text-primary">
                <span className="text-primary mr-2 font-semibold uppercase">{a.category || "News"}</span>
                {a.title}
              </Link>
            ))}
          </div>
        </div>
      </div>

      {/* Hero */}
      <section className="mx-auto max-w-7xl px-4 pt-8 pb-12 grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2"><ArticleCard article={featured} variant="hero" /></div>
        <div className="grid gap-4">
          {secondary.map((a) => <ArticleCard key={a.id} article={a} variant="compact" />)}
          <div className="rounded-xl border border-border bg-card p-4">
            <div className="flex flex-wrap gap-2 mb-3">
              {spotlightArtists.map((a) => <Link key={a.id} to="/artist/$slug" params={{ slug: a.slug }} className="px-2 py-1 text-xs rounded-full bg-accent hover:bg-primary hover:text-primary-foreground">{a.name}</Link>)}
            </div>
            <div className="flex flex-wrap gap-2">
              <Button size="sm" asChild><Link to="/latest">Explore Latest <ArrowRight className="size-3" /></Link></Button>
              <Button size="sm" variant="outline" asChild><Link to="/forum">Join Forum</Link></Button>
              <Button size="sm" variant="ghost" asChild><Link to="/artists">Follow Artists</Link></Button>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-7xl px-4"><AdSlot slotId="home-after-hero" variant="leaderboard" /></div>

      <ForYouSection pool={data.articles} />

      {/* Trending */}

      <section className="mx-auto max-w-7xl px-4 py-8">
        <SectionHeader eyebrow="Trending Now" title="What everyone is reading" />
        <div data-reveal-children className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {trending.map((a, i) => (
            <Link key={a.id} to="/news/$slug" params={{ slug: a.slug }} className="group relative rounded-xl overflow-hidden bg-card border border-border">
              <div className="aspect-[4/5] bg-muted"><img src={a.featuredImage} alt={a.title} loading="lazy" onError={(e) => { e.currentTarget.style.visibility = "hidden"; }} className="size-full object-cover group-hover:scale-105 transition-transform" /></div>
              <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent" />
              <div className="absolute top-2 left-2 size-8 grid place-items-center rounded-full bg-primary text-primary-foreground font-display font-bold">{i + 1}</div>
              <div className="absolute bottom-0 p-3 text-white">
                <div className="text-[10px] uppercase tracking-wider opacity-80">{a.category}</div>
                <h3 className="text-sm font-semibold line-clamp-3 leading-snug">{a.title}</h3>
                {a.viewCount > 0 && <div className="text-[11px] opacity-80 mt-1 flex items-center gap-2"><Flame className="size-3" />{a.viewCount.toLocaleString()} views</div>}
              </div>
            </Link>
          ))}
        </div>
      </section>

      {/* Latest */}
      <section className="mx-auto max-w-7xl px-4 py-8">
        <SectionHeader eyebrow="Latest News" title="Fresh off the wire" />
        <div className="flex gap-2 overflow-x-auto scrollbar-hide pb-3 mb-4">
          {categories.map((c) => (
            <button key={c} type="button" onClick={() => setLatestCategory(c)} aria-pressed={latestCategory === c} className={`shrink-0 px-3 py-1.5 rounded-full text-sm transition-colors ${latestCategory === c ? "bg-primary text-primary-foreground" : "bg-accent hover:bg-primary hover:text-primary-foreground"}`}>{c}</button>
          ))}
        </div>
        <div data-reveal-children className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {latest.map((a) => <ArticleCard key={a.id} article={a} />)}
        </div>
        <AdSlot slotId="home-mid-feed" variant="billboard" />
      </section>

      {/* Comebacks + Spotlight */}
      <section className="mx-auto max-w-7xl px-4 py-8 grid gap-8 lg:grid-cols-3">
        {upcoming.length > 0 && (
        <div className="min-w-0 lg:col-span-2">
          <SectionHeader eyebrow="Comeback Calendar" title="Upcoming releases" />
          <div className="grid gap-3">
            {upcoming.map((c) => {
              const artist = data.artists.find((a) => a.id === c.artistId || a.slug === c.artistId);
              const days = Math.max(0, Math.ceil((+new Date(c.releaseAt) - Date.now()) / 86400000));
              return (
                <div key={c.id} className="min-w-0 flex items-center gap-4 p-3 rounded-xl bg-card border border-border">
                  <div className="size-14 rounded-lg overflow-hidden shrink-0"><img src={c.image} alt={c.title} className="size-full object-cover" /></div>
                  <div className="flex-1 min-w-0">
                    <div className="text-xs uppercase text-primary">{c.type}</div>
                    <div className="font-semibold truncate">{c.title}</div>
                    <div className="text-xs text-muted-foreground">{artist?.name ?? c.artistId} · {new Date(c.releaseAt).toDateString()}</div>
                  </div>
                  <div className="text-right">
                    <div className="font-display text-2xl text-gradient font-bold">{days}d</div>
                    <NotifyButton label="Remind me" reminder={{ id: `home-comeback:${c.id}`, kind: "comeback", title: c.title, body: `${artist?.name ?? c.artistId} ${c.type} starts soon`, url: "/comebacks", icon: c.image, fireAt: c.releaseAt, leadMinutes: 15 }} />
                  </div>
                </div>
              );
            })}
            <Link to="/comebacks" className="text-sm text-primary hover:underline self-end">See full calendar →</Link>
          </div>
        </div>
        )}
        {spotlightArtists.length > 0 && (
        <div className={upcoming.length > 0 ? "" : "lg:col-span-3"}>
          <SectionHeader eyebrow="Artist Spotlight" title="Featured artists" />
          <div className={upcoming.length > 0 ? "grid gap-3" : "grid gap-3 sm:grid-cols-3"}>
            {spotlightArtists.map((a) => (
              <Link key={a.id} to="/artist/$slug" params={{ slug: a.slug }} className="block rounded-xl overflow-hidden bg-card border border-border">
                <div className="aspect-[3/2]"><img src={a.image} alt={a.name} className="size-full object-cover" /></div>
                <div className="p-3">
                  <div className="font-display font-bold">{a.name}</div>
                  <div className="text-xs text-muted-foreground">{a.agency}{coverage.get(a.slug) ? ` · ${coverage.get(a.slug)} recent stories` : ""}</div>
                </div>
              </Link>
            ))}
          </div>
        </div>
        )}
      </section>

      {/* Forum + Polls */}
      <section className="mx-auto max-w-7xl px-4 py-8 grid gap-8 lg:grid-cols-3">
        <div className="min-w-0 lg:col-span-2">
          <SectionHeader eyebrow="Hot Forum Threads" title="Where fans are talking" />
          <div className="grid gap-2">
            {hotThreads.map((t) => {
              const cat = data.categories.find((c) => c.id === t.categoryId || c.slug === t.categoryId);
              return (
                <Link key={t.id} to="/thread/$threadSlug" params={{ threadSlug: t.slug }} className="min-w-0 flex items-center gap-3 p-3 rounded-xl bg-card border border-border hover:border-primary/40">
                  <div className="size-10 grid place-items-center rounded-lg bg-accent">{cat?.icon ?? "💬"}</div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      {t.pinned && <span className="text-[10px] px-1.5 py-0.5 rounded bg-primary text-primary-foreground">PINNED</span>}
                      {t.flair && <span className="text-[10px] px-1.5 py-0.5 rounded bg-accent">{t.flair}</span>}
                    </div>
                    <div className="font-semibold truncate">{t.title}</div>
                    <div className="text-xs text-muted-foreground">{[cat?.name ?? "Forum", t.replies ? `${t.replies} replies` : null, t.views ? `${t.views} views` : null].filter(Boolean).join(" · ")}</div>
                  </div>
                  <MessageSquare className="size-4 text-muted-foreground" />
                </Link>
              );
            })}
            <Link to="/forum" className="text-sm text-primary hover:underline self-end mt-1">Start a discussion →</Link>
          </div>
        </div>
        <div>
          <SectionHeader eyebrow="Fan Polls" title="This week" />
          <div className="grid gap-3">
            {polls.map((p) => (
              <Link key={p.id} to="/polls/$slug" params={{ slug: p.slug }} className="block p-4 rounded-xl bg-card border border-border hover:border-primary/40">
                <div className="flex items-center gap-2 mb-2 text-xs text-primary"><Vote className="size-3" />POLL</div>
                <div className="font-semibold mb-2">{p.title}</div>
                <div className="space-y-1">
                  {p.options.slice(0, 3).map((o) => {
                    const pct = p.totalVotes > 0 ? Math.round((o.votes / p.totalVotes) * 100) : 0;
                    return (
                      <div key={o.id}>
                        <div className="flex justify-between text-xs"><span>{o.label}</span><span>{pct}%</span></div>
                        <div className="h-1.5 bg-muted rounded-full overflow-hidden"><div className="h-full gradient-neon" style={{ width: `${pct}%` }} /></div>
                      </div>
                    );
                  })}
                </div>
              </Link>
            ))}
          </div>
          <div className="mt-4">
            <DailyQuizWidget />
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-7xl px-4"><AdSlot slotId="home-pre-community" variant="leaderboard" /></div>

      <section className="mx-auto max-w-7xl px-4 py-8">
        <NewsletterCTA variant="card" source="home" showTopics />
      </section>

      {/* Community wall */}
      {community.length > 0 && (
      <section className="mx-auto max-w-7xl px-4 py-8">
        <SectionHeader eyebrow="Community Wall" title="Fans around the world" />
        <div data-reveal-children className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {community.map((c) => {
            const user = data.users.find((u) => u.id === c.authorId);
            return (
              <div key={c.id} className="p-4 rounded-xl bg-card border border-border">
                <div className="flex items-center gap-2 mb-2">
                  {(c.author?.avatar || user?.avatar) && <div className="size-7 rounded-full overflow-hidden"><img src={c.author?.avatar ?? user?.avatar} alt="" /></div>}
                  <div className="text-xs"><div className="font-medium">{c.author?.displayName ?? user?.displayName ?? "Community member"}</div><div className="text-muted-foreground">{c.language.toUpperCase()}</div></div>
                </div>
                <p className="text-sm">{c.body}</p>
                <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                  <span>♥ {c.reactions}</span>
                  <Link to="/community" className="hover:text-primary">Join discussion →</Link>
                </div>
              </div>
            );
          })}
        </div>
      </section>
      )}

    </div>
  );
}
