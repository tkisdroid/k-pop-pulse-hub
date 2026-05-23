import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { demoData } from "@/data/demo";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";

export const Route = createFileRoute("/latest")({
  head: () => buildHead({ title: "Latest K-pop News", description: "Browse the freshest K-pop stories.", canonical: "/latest" }),
  component: Latest,
});

const CATS = ["All", "Music", "Comeback", "Tour", "Awards", "Drama", "Variety", "Fashion", "Business", "Rumor", "Official Statement", "Feature"];
const SORTS = ["Newest", "Trending", "Most commented", "Most viewed"] as const;

function Latest() {
  const [cat, setCat] = useState("All");
  const [sort, setSort] = useState<typeof SORTS[number]>("Newest");
  const [q, setQ] = useState("");

  let articles = [...demoData.articles];
  if (cat !== "All") articles = articles.filter((a) => a.category.toLowerCase() === cat.toLowerCase());
  if (q) articles = articles.filter((a) => a.title.toLowerCase().includes(q.toLowerCase()));
  articles.sort((a, b) => {
    if (sort === "Trending") return b.reactionCount - a.reactionCount;
    if (sort === "Most commented") return b.commentCount - a.commentCount;
    if (sort === "Most viewed") return b.viewCount - a.viewCount;
    return +new Date(b.publishedAt) - +new Date(a.publishedAt);
  });

  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <SectionHeader eyebrow="News" title="Latest K-pop News" subtitle="Filter, sort, and search the editorial feed." />
      <AdSlot slotId="latest-top" variant="leaderboard" />
      <div className="grid gap-3 md:flex md:items-center md:justify-between mb-6">
        <div className="flex gap-2 overflow-x-auto scrollbar-hide">
          {CATS.map((c) => (
            <button key={c} onClick={() => setCat(c)} className={`shrink-0 px-3 py-1.5 rounded-full text-sm ${cat === c ? "bg-primary text-primary-foreground" : "bg-accent"}`}>{c}</button>
          ))}
        </div>
        <div className="flex gap-2">
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search news" className="h-9 px-3 rounded-md bg-background border border-input text-sm" />
          <select value={sort} onChange={(e) => setSort(e.target.value as any)} className="h-9 px-2 rounded-md bg-background border border-input text-sm">
            {SORTS.map((s) => <option key={s}>{s}</option>)}
          </select>
        </div>
      </div>
      {articles.length === 0 ? (
        <div className="py-20 text-center text-muted-foreground">No results found.</div>
      ) : (
        <div data-reveal-children className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {articles.map((a) => <ArticleCard key={a.id} article={a} />)}
        </div>
      )}
    </div>
  );
}
