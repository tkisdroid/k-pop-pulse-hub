import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { demoData } from "@/data/demo";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";

export const Route = createFileRoute("/artists")({
  head: () => buildHead({ title: "K-pop Artists", description: "Artist & group directory.", canonical: "/artists" }),
  component: Artists,
});

const TYPES = [["all", "All"], ["boy_group", "Boy Groups"], ["girl_group", "Girl Groups"], ["soloist", "Soloists"], ["coed", "Co-ed"], ["band", "Bands"]] as const;

function Artists() {
  const [type, setType] = useState<string>("all");
  const [gen, setGen] = useState<number | null>(null);
  const [q, setQ] = useState("");
  let artists = [...demoData.artists];
  if (type !== "all") artists = artists.filter((a) => a.type === type);
  if (gen) artists = artists.filter((a) => a.generation === gen);
  if (q) artists = artists.filter((a) => a.name.toLowerCase().includes(q.toLowerCase()) || a.agency.toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <SectionHeader eyebrow="Directory" title="K-pop Artists" subtitle="Search by artist, group, agency or generation." />
      <AdSlot slotId="artists-top" variant="leaderboard" />
      <div className="grid gap-3 md:flex md:items-center md:justify-between mb-6">
        <div className="flex gap-2 overflow-x-auto scrollbar-hide">
          {TYPES.map(([k, l]) => <button key={k} onClick={() => setType(k)} className={`shrink-0 px-3 py-1.5 rounded-full text-sm ${type === k ? "bg-primary text-primary-foreground" : "bg-accent"}`}>{l}</button>)}
        </div>
        <div className="flex gap-2">
          <select value={gen ?? ""} onChange={(e) => setGen(e.target.value ? Number(e.target.value) : null)} className="h-9 px-2 rounded-md bg-background border border-input text-sm">
            <option value="">All gens</option>
            {[1, 2, 3, 4, 5].map((g) => <option key={g} value={g}>{g}th gen</option>)}
          </select>
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search artists, agency" className="h-9 px-3 rounded-md bg-background border border-input text-sm" />
        </div>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
        {artists.map((a) => (
          <Link key={a.id} to="/artist/$slug" params={{ slug: a.slug }} className="rounded-xl overflow-hidden bg-card border border-border hover:border-primary/40 group">
            <div className="aspect-[3/4] overflow-hidden"><img src={a.image} alt={a.name} className="size-full object-cover group-hover:scale-105 transition-transform" /></div>
            <div className="p-3">
              <div className="font-display font-bold">{a.name}</div>
              <div className="text-xs text-muted-foreground">{a.agency} · Gen {a.generation}</div>
              <div className="text-xs text-muted-foreground mt-1">{a.followerCount.toLocaleString()} followers</div>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
