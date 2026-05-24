import { createFileRoute, Link } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { ForYouSection } from "@/components/articles/ForYouSection";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { Button } from "@/components/ui/button";
import { buildHead } from "@/components/layout/seo";
import { Flame, Calendar, Vote, MessageSquare, Bell, ArrowRight } from "lucide-react";
import { AdSlot } from "@/components/ads/AdSlot";
import { NewsletterCTA } from "@/components/newsletter/NewsletterCTA";
import { DailyQuizWidget } from "@/components/quiz/DailyQuizWidget";

export const Route = createFileRoute("/")({
  head: () => {
    const head = buildHead({ title: "Home", description: "Latest K-pop news, comebacks, artists and fan discussions.", canonical: "/" });
    const hero = demoData.articles[0]?.featuredImage;
    if (hero) head.links.push({ rel: "preload", as: "image", href: hero, fetchpriority: "high" });
    return head;
  },
  component: Index,
});

const LABELS = ["BREAKING", "COMEBACK", "TOUR", "AWARD", "OFFICIAL", "RUMOR"];

function Index() {
  const featured = demoData.articles[0];
  const secondary = demoData.articles.slice(1, 4);
  const trending = demoData.articles.slice(0, 5);
  const latest = demoData.articles.slice(2, 8);
  const upcoming = demoData.comebacks.slice(0, 5);
  const spotlightArtists = demoData.artists.slice(0, 3);
  const hotThreads = demoData.threads.slice(0, 5);
  const polls = demoData.polls.slice(0, 2);
  const community = demoData.community.slice(0, 4);

  return (
    <div>
      {/* Breaking ticker */}
      <div className="bg-card border-b border-border overflow-hidden">
        <div className="mx-auto max-w-7xl px-4 py-2 flex items-center gap-4">
          <span className="shrink-0 px-2 py-0.5 rounded text-xs font-bold bg-destructive text-destructive-foreground">BREAKING</span>
          <div className="flex gap-6 overflow-x-auto scrollbar-hide text-sm">
            {demoData.articles.slice(0, 6).map((a, i) => (
              <Link key={a.id} to="/news/$slug" params={{ slug: a.slug }} className="whitespace-nowrap hover:text-primary">
                <span className="text-primary mr-2 font-semibold">{LABELS[i % LABELS.length]}</span>
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

      <ForYouSection pool={demoData.articles} />

      {/* Trending */}

      <section className="mx-auto max-w-7xl px-4 py-8">
        <SectionHeader eyebrow="Trending Now" title="What everyone is reading" />
        <div data-reveal-children className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          {trending.map((a, i) => (
            <Link key={a.id} to="/news/$slug" params={{ slug: a.slug }} className="group relative rounded-xl overflow-hidden bg-card border border-border">
              <div className="aspect-[4/5]"><img src={a.featuredImage} alt={a.title} className="size-full object-cover group-hover:scale-105 transition-transform" /></div>
              <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent" />
              <div className="absolute top-2 left-2 size-8 grid place-items-center rounded-full bg-primary text-primary-foreground font-display font-bold">{i + 1}</div>
              <div className="absolute bottom-0 p-3 text-white">
                <div className="text-[10px] uppercase tracking-wider opacity-80">{a.category}</div>
                <h3 className="text-sm font-semibold line-clamp-3 leading-snug">{a.title}</h3>
                <div className="text-[11px] opacity-80 mt-1 flex items-center gap-2"><Flame className="size-3" />{a.viewCount.toLocaleString()} views</div>
              </div>
            </Link>
          ))}
        </div>
      </section>

      {/* Latest */}
      <section className="mx-auto max-w-7xl px-4 py-8">
        <SectionHeader eyebrow="Latest News" title="Fresh off the wire" />
        <div className="flex gap-2 overflow-x-auto scrollbar-hide pb-3 mb-4">
          {["All", "Music", "Comeback", "Tour", "Awards", "Drama", "Variety", "Fashion", "Business", "Rumors", "Official Statements"].map((c) => (
            <button key={c} className="shrink-0 px-3 py-1.5 rounded-full text-sm bg-accent hover:bg-primary hover:text-primary-foreground transition-colors">{c}</button>
          ))}
        </div>
        <div data-reveal-children className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {latest.map((a) => <ArticleCard key={a.id} article={a} />)}
        </div>
        <AdSlot slotId="home-mid-feed" variant="billboard" />
      </section>

      {/* Comebacks + Spotlight */}
      <section className="mx-auto max-w-7xl px-4 py-8 grid gap-8 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <SectionHeader eyebrow="Comeback Calendar" title="Upcoming releases" />
          <div className="grid gap-3">
            {upcoming.map((c) => {
              const artist = demoData.artists.find((a) => a.id === c.artistId)!;
              const days = Math.max(0, Math.ceil((+new Date(c.releaseAt) - Date.now()) / 86400000));
              return (
                <div key={c.id} className="flex items-center gap-4 p-3 rounded-xl bg-card border border-border">
                  <div className="size-14 rounded-lg overflow-hidden shrink-0"><img src={c.image} alt={c.title} className="size-full object-cover" /></div>
                  <div className="flex-1 min-w-0">
                    <div className="text-xs uppercase text-primary">{c.type}</div>
                    <div className="font-semibold truncate">{c.title}</div>
                    <div className="text-xs text-muted-foreground">{artist.name} · {new Date(c.releaseAt).toDateString()}</div>
                  </div>
                  <div className="text-right">
                    <div className="font-display text-2xl text-gradient font-bold">{days}d</div>
                    <Button size="sm" variant="outline">Remind me</Button>
                  </div>
                </div>
              );
            })}
            <Link to="/comebacks" className="text-sm text-primary hover:underline self-end">See full calendar →</Link>
          </div>
        </div>
        <div>
          <SectionHeader eyebrow="Artist Spotlight" title="Featured artists" />
          <div className="grid gap-3">
            {spotlightArtists.map((a) => (
              <Link key={a.id} to="/artist/$slug" params={{ slug: a.slug }} className="block rounded-xl overflow-hidden bg-card border border-border">
                <div className="aspect-[3/2]"><img src={a.image} alt={a.name} className="size-full object-cover" /></div>
                <div className="p-3">
                  <div className="font-display font-bold">{a.name}</div>
                  <div className="text-xs text-muted-foreground">{a.agency} · {a.followerCount.toLocaleString()} followers</div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Forum + Polls */}
      <section className="mx-auto max-w-7xl px-4 py-8 grid gap-8 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <SectionHeader eyebrow="Hot Forum Threads" title="Where fans are talking" />
          <div className="grid gap-2">
            {hotThreads.map((t) => {
              const cat = demoData.categories.find((c) => c.id === t.categoryId)!;
              return (
                <Link key={t.id} to="/thread/$threadSlug" params={{ threadSlug: t.slug }} className="flex items-center gap-3 p-3 rounded-xl bg-card border border-border hover:border-primary/40">
                  <div className="size-10 grid place-items-center rounded-lg bg-accent">{cat.icon}</div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      {t.pinned && <span className="text-[10px] px-1.5 py-0.5 rounded bg-primary text-primary-foreground">PINNED</span>}
                      {t.flair && <span className="text-[10px] px-1.5 py-0.5 rounded bg-accent">{t.flair}</span>}
                    </div>
                    <div className="font-semibold truncate">{t.title}</div>
                    <div className="text-xs text-muted-foreground">{cat.name} · {t.replies} replies · {t.views} views</div>
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
                    const pct = Math.round((o.votes / p.totalVotes) * 100);
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
      <section className="mx-auto max-w-7xl px-4 py-8">
        <SectionHeader eyebrow="Community Wall" title="Fans around the world" />
        <div data-reveal-children className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {community.map((c) => {
            const user = demoData.users.find((u) => u.id === c.authorId)!;
            return (
              <div key={c.id} className="p-4 rounded-xl bg-card border border-border">
                <div className="flex items-center gap-2 mb-2">
                  <div className="size-7 rounded-full overflow-hidden"><img src={user.avatar} alt="" /></div>
                  <div className="text-xs"><div className="font-medium">{user.displayName}</div><div className="text-muted-foreground">{c.language.toUpperCase()}</div></div>
                </div>
                <p className="text-sm">{c.body}</p>
                <div className="mt-3 flex items-center gap-3 text-xs text-muted-foreground">
                  <button className="hover:text-primary">♥ {c.reactions}</button>
                  <button className="hover:text-primary">Translate</button>
                  <button className="hover:text-destructive ml-auto">Report</button>
                </div>
              </div>
            );
          })}
        </div>
      </section>

      {/* Newsletter */}
      <section className="mx-auto max-w-7xl px-4 py-12">
        <div className="rounded-3xl gradient-neon p-8 md:p-12 text-white text-center">
          <Bell className="size-8 mx-auto mb-3 opacity-90" />
          <h2 className="font-display text-3xl md:text-4xl font-bold">Never miss a comeback</h2>
          <p className="mt-2 opacity-90 max-w-xl mx-auto">Get breaking K-pop news, comeback alerts and weekly fan picks straight to your inbox.</p>
          <form className="mt-5 max-w-md mx-auto flex gap-2" onSubmit={(e) => e.preventDefault()}>
            <input type="email" placeholder="you@email.com" className="flex-1 h-11 px-4 rounded-md text-foreground bg-background/95" />
            <Button type="submit" variant="secondary">Subscribe</Button>
          </form>
        </div>
      </section>
    </div>
  );
}
