import { createFileRoute, Link } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Play } from "lucide-react";
import { useMemo, useState } from "react";

export const Route = createFileRoute("/videos")({
  head: () => buildHead({ title: "Videos", canonical: "/videos", description: "Watch real K-pop MVs and performances right inside the site, and join the conversation per artist." }),
  component: Videos,
});

function Videos() {
  const artists = demoData.artists;
  const [filter, setFilter] = useState<string>("all");
  const videos = useMemo(
    () => (filter === "all" ? demoData.videos : demoData.videos.filter((v) => v.artistId === filter)),
    [filter]
  );

  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <SectionHeader eyebrow="Videos" title="MVs, performances & interviews — watch in-site" />
      <AdSlot slotId="videos-top" variant="leaderboard" />

      <div className="flex gap-2 overflow-x-auto scrollbar-hide pb-2 mb-4">
        <button
          onClick={() => setFilter("all")}
          className={`shrink-0 px-3 py-1.5 rounded-full text-sm border ${filter === "all" ? "bg-primary text-primary-foreground border-primary" : "bg-card border-border text-muted-foreground"}`}
        >
          All
        </button>
        {artists.map((a) => (
          <button
            key={a.id}
            onClick={() => setFilter(a.id)}
            className={`shrink-0 px-3 py-1.5 rounded-full text-sm border ${filter === a.id ? "bg-primary text-primary-foreground border-primary" : "bg-card border-border text-muted-foreground"}`}
          >
            {a.name}
          </button>
        ))}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {videos.map((v) => {
          const artist = demoData.artists.find((a) => a.id === v.artistId);
          return (
            <Link
              key={v.id}
              to="/watch/$videoId"
              params={{ videoId: v.id }}
              className="rounded-xl overflow-hidden bg-card border border-border group"
            >
              <div className="aspect-video relative">
                <img src={v.thumbnail} alt={v.title} className="size-full object-cover" loading="lazy" />
                <div className="absolute inset-0 grid place-items-center bg-black/30 group-hover:bg-black/50 transition">
                  <Play className="size-12 text-white" />
                </div>
                <div className="absolute bottom-2 right-2 px-1.5 py-0.5 rounded bg-black/70 text-white text-xs">{v.duration}</div>
              </div>
              <div className="p-3">
                <div className="text-xs text-primary uppercase">{v.category}{artist ? ` · ${artist.name}` : ""}</div>
                <div className="font-semibold text-sm line-clamp-2">{v.title}</div>
              </div>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
