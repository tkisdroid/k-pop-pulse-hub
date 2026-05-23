import { createFileRoute } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Play } from "lucide-react";

export const Route = createFileRoute("/videos")({
  head: () => buildHead({ title: "Videos", canonical: "/videos" }),
  component: Videos,
});

function Videos() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <SectionHeader eyebrow="Videos" title="MVs, performances & interviews" />
      <AdSlot slotId="videos-top" variant="leaderboard" />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {demoData.videos.map((v) => (
          <div key={v.id} className="rounded-xl overflow-hidden bg-card border border-border">
            <div className="aspect-video relative"><img src={v.thumbnail} alt={v.title} className="size-full object-cover" /><div className="absolute inset-0 grid place-items-center bg-black/30"><Play className="size-12 text-white" /></div><div className="absolute bottom-2 right-2 px-1.5 py-0.5 rounded bg-black/70 text-white text-xs">{v.duration}</div></div>
            <div className="p-3">
              <div className="text-xs text-primary uppercase">{v.category}</div>
              <div className="font-semibold text-sm">{v.title}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
