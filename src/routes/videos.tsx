import { createFileRoute, Link } from "@tanstack/react-router";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Play } from "lucide-react";
import { useMemo, useState } from "react";
import { useRuntimeData } from "@/services/cms/runtimeData";

export const Route = createFileRoute("/videos")({
  head: () => buildHead({ title: "Videos", canonical: "/videos", description: "Watch real K-pop MVs and performances right inside the site, and join the conversation per artist." }),
  component: Videos,
});

function Videos() {
  const { data, isLoading, error } = useRuntimeData();
  const artists = data.artists;
  const [filter, setFilter] = useState<string>("all");
  const videos = useMemo(
    () => {
      if (filter === "all") return data.videos;
      const artist = data.artists.find((a) => a.id === filter);
      const keys = new Set([filter, artist?.slug].filter(Boolean));
      return data.videos.filter((v) => keys.has(v.artistId) || keys.has(v.artistSlug));
    },
    [data.videos, data.artists, filter]
  );
  // Only offer artist filters that actually have videos.
  const videoArtists = useMemo(
    () => artists.filter((a) => data.videos.some((v) => v.artistId === a.id || v.artistId === a.slug || v.artistSlug === a.slug)),
    [artists, data.videos]
  );

  if (isLoading) return <div className="mx-auto max-w-7xl px-4 py-12 text-muted-foreground">Loading videos…</div>;
  if (error) return <div className="mx-auto max-w-7xl px-4 py-12 text-destructive">{error}</div>;

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
        {videoArtists.map((a) => (
          <button
            key={a.id}
            onClick={() => setFilter(a.id)}
            className={`shrink-0 px-3 py-1.5 rounded-full text-sm border ${filter === a.id ? "bg-primary text-primary-foreground border-primary" : "bg-card border-border text-muted-foreground"}`}
          >
            {a.name}
          </button>
        ))}
      </div>

      <div data-reveal-children className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {videos.map((v) => {
          const artist = data.artists.find((a) => a.id === v.artistId || a.slug === v.artistId || a.slug === v.artistSlug);
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
      {videos.length === 0 && <div className="rounded-xl border border-dashed border-border p-10 text-center text-muted-foreground">No published videos yet.</div>}
    </div>
  );
}
