import { createFileRoute } from "@tanstack/react-router";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { TrendingUp, TrendingDown, Minus } from "lucide-react";

export const Route = createFileRoute("/charts")({
  head: () => buildHead({ title: "K-pop Charts", canonical: "/charts" }),
  component: Charts,
});

function Charts() {
  const { data } = useRuntimeData();
  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <SectionHeader
        as="h1"
        eyebrow="Charts"
        title="Weekly K-pop ranking"
        subtitle="Current rankings published by the editorial team."
      />
      <AdSlot slotId="charts-top" variant="leaderboard" />
      <div className="rounded-xl bg-card border border-border overflow-hidden">
        {data.charts.length === 0 && (
          <p className="p-8 text-center text-muted-foreground">No chart has been published yet.</p>
        )}
        {data.charts.map((c) => (
          <div
            key={c.rank}
            className="flex items-center gap-4 p-3 border-b border-border last:border-b-0"
          >
            <div className="size-10 grid place-items-center font-display text-xl font-bold text-gradient">
              #{c.rank}
            </div>
            <div className="flex-1">
              <div className="font-semibold">{c.title}</div>
              <div className="text-xs text-muted-foreground">{c.artist}</div>
            </div>
            <div
              className={`flex items-center gap-1 text-sm ${c.change > 0 ? "text-emerald-500" : c.change < 0 ? "text-destructive" : "text-muted-foreground"}`}
            >
              {c.change > 0 ? (
                <TrendingUp className="size-4" />
              ) : c.change < 0 ? (
                <TrendingDown className="size-4" />
              ) : (
                <Minus className="size-4" />
              )}
              {c.change !== 0 && Math.abs(c.change)}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
