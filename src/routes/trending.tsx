import { createFileRoute, Link } from "@tanstack/react-router";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Flame } from "lucide-react";

export const Route = createFileRoute("/trending")({
  head: () => buildHead({ title: "Trending K-pop", description: "What's hot right now in the K-pop world.", canonical: "/trending" }),
  component: Trending,
});

function Trending() {
  const { data } = useRuntimeData();
  const articles = [...data.articles].sort((a, b) => b.reactionCount - a.reactionCount);
  const artists = [...data.artists].sort((a, b) => b.followerCount - a.followerCount).slice(0, 6);
  const threads = [...data.threads].sort((a, b) => b.views - a.views).slice(0, 5);
  return (
    <div className="mx-auto max-w-7xl px-4 py-8 space-y-12">
      <section>
        <SectionHeader eyebrow="Trending" title="Top stories right now" />
        <AdSlot slotId="trending-top" variant="leaderboard" />
        <div data-reveal-children className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          {articles.slice(0, 6).map((a) => <ArticleCard key={a.id} article={a} />)}
        </div>
      </section>
      <section>
        <SectionHeader title="Trending artists" />
        <div data-reveal-children className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
          {artists.map((a) => (
            <Link key={a.id} to="/artist/$slug" params={{ slug: a.slug }} className="text-center group">
              <div className="aspect-square rounded-full overflow-hidden mb-2 mx-auto w-24"><img src={a.image} alt={a.name} className="size-full object-cover group-hover:scale-105 transition-transform" /></div>
              <div className="font-semibold text-sm">{a.name}</div>
              <div className="text-xs text-muted-foreground flex items-center justify-center gap-1"><Flame className="size-3" />{a.followerCount.toLocaleString()}</div>
            </Link>
          ))}
        </div>
      </section>
      <section>
        <SectionHeader title="Trending forum threads" />
        <div className="grid gap-2">
          {threads.map((t) => (
            <Link key={t.id} to="/thread/$threadSlug" params={{ threadSlug: t.slug }} className="p-3 rounded-xl bg-card border border-border hover:border-primary/40">
              <div className="font-semibold">{t.title}</div>
              <div className="text-xs text-muted-foreground">{[t.views ? `${t.views} views` : null, t.replies ? `${t.replies} replies` : null].filter(Boolean).join(" · ") || "New discussion"}</div>
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}
