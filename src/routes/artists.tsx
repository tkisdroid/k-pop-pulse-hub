import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";

export const Route = createFileRoute("/artists")({
  head: () => buildHead({ title: "K-pop Artists", description: "Artist & group directory.", canonical: "/artists" }),
  component: Artists,
});

const TYPES = [["all", "All"], ["boy_group", "Boy Groups"], ["girl_group", "Girl Groups"], ["soloist", "Soloists"], ["coed", "Co-ed"], ["band", "Bands"]] as const;

function Artists() {
  const { data, isLoading, error } = useRuntimeData();
  const [type, setType] = useState<string>("all");
  const [gen, setGen] = useState<number | null>(null);
  const [q, setQ] = useState("");
  if (isLoading) return <p className="py-20 text-center text-muted-foreground">Loading artists…</p>;
  if (error) return <p className="py-20 text-center text-destructive" role="alert">{error}</p>;
  let artists = [...data.artists];
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
          <select value={gen ?? ""} onChange={(e) => setGen(e.target.value ? Number(e.target.value) : null)} aria-label="Generation" className="h-9 px-2 rounded-md bg-background border border-input text-sm shrink-0">
            <option value="">All gens</option>
            {[1, 2, 3, 4, 5].map((g) => <option key={g} value={g}>{g}th gen</option>)}
          </select>
          <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search artists, agency" aria-label="Search artists" className="h-9 min-w-0 flex-1 md:w-64 md:flex-none px-3 rounded-md bg-background border border-input text-base md:text-sm" />
        </div>
      </div>
      <div data-reveal-children className="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
        {artists.length === 0 && <p className="col-span-full py-16 text-center text-muted-foreground">No published artists yet.</p>}
        {artists.map((a) => (
          <Link key={a.id} to="/artist/$slug" params={{ slug: a.slug }} className="rounded-xl overflow-hidden bg-card border border-border hover:border-primary/40 group">
            <div className="aspect-[3/4] overflow-hidden bg-muted"><img src={a.image} alt={a.name} loading="lazy" decoding="async" width={300} height={400} className="size-full object-cover group-hover:scale-105 transition-transform" /></div>
            <div className="p-3">
              <div className="font-display font-bold truncate">{a.name}</div>
              <div className="text-xs text-muted-foreground line-clamp-2">{a.agency} · Gen {a.generation}</div>
              <div className="text-xs text-muted-foreground mt-1">{a.followerCount.toLocaleString()} followers</div>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
