import { createFileRoute, Link } from "@tanstack/react-router";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { SectionHeader } from "@/components/layout/SectionHeader";

export const Route = createFileRoute("/polls")({
  head: () => buildHead({ title: "Fan Polls", canonical: "/polls" }),
  component: Polls,
});

function Polls() {
  const { data } = useRuntimeData();
  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <SectionHeader eyebrow="Polls" title="Active fan polls" />
      <AdSlot slotId="polls-top" variant="leaderboard" />
      <div data-reveal-children className="grid gap-4 sm:grid-cols-2">
        {data.polls.length === 0 && <p className="col-span-full py-16 text-center text-muted-foreground">No active polls yet.</p>}
        {data.polls.map((p) => (
          <Link key={p.id} to="/polls/$slug" params={{ slug: p.slug }} className="p-5 rounded-xl bg-card border border-border hover:border-primary/40 block">
            <div className="font-display text-lg font-bold mb-3">{p.title}</div>
            <div className="space-y-2">
              {p.options.map((o) => {
                const pct = p.totalVotes > 0 ? Math.round((o.votes / p.totalVotes) * 100) : 0;
                return (
                  <div key={o.id}>
                    <div className="flex justify-between text-xs"><span>{o.label}</span><span>{pct}%</span></div>
                    <div className="h-1.5 bg-muted rounded-full overflow-hidden"><div className="h-full gradient-neon" style={{ width: `${pct}%` }} /></div>
                  </div>
                );
              })}
            </div>
            <div className="mt-3 text-xs text-muted-foreground">{p.totalVotes.toLocaleString()} votes</div>
          </Link>
        ))}
      </div>
    </div>
  );
}
