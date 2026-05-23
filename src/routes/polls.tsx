import { createFileRoute, Link } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { SectionHeader } from "@/components/layout/SectionHeader";

export const Route = createFileRoute("/polls")({
  head: () => buildHead({ title: "Fan Polls", canonical: "/polls" }),
  component: Polls,
});

function Polls() {
  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <SectionHeader eyebrow="Polls" title="Active fan polls" />
      <div className="grid gap-4 sm:grid-cols-2">
        {demoData.polls.map((p) => (
          <Link key={p.id} to="/polls/$slug" params={{ slug: p.slug }} className="p-5 rounded-xl bg-card border border-border hover:border-primary/40 block">
            <div className="font-display text-lg font-bold mb-3">{p.title}</div>
            <div className="space-y-2">
              {p.options.map((o) => {
                const pct = Math.round((o.votes / p.totalVotes) * 100);
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
