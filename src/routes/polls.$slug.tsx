import { createFileRoute, notFound } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { useState } from "react";
import { NotifyButton } from "@/components/notifications/NotifyButton";


export const Route = createFileRoute("/polls/$slug")({
  loader: ({ params }) => {
    const p = demoData.polls.find((x) => x.slug === params.slug);
    if (!p) throw notFound();
    return p;
  },
  head: ({ loaderData }) => buildHead({ title: loaderData?.title ?? "Poll", canonical: `/polls/${loaderData?.slug}` }),
  component: PollPage,
});

function PollPage() {
  const p = Route.useLoaderData();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [voted, setVoted] = useState<string | null>(null);
  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <h1 className="font-display text-3xl font-bold mb-2">{p.title}</h1>
      <p className="text-muted-foreground mb-6">{p.description ?? "Cast your vote and see what fans think."}</p>
      <div className="space-y-3">
        {p.options.map((o: { id: string; label: string; votes: number }) => {
          const pct = Math.round((o.votes / p.totalVotes) * 100);
          const isVoted = voted === o.id;
          return (
            <button key={o.id} onClick={() => (user ? setVoted(o.id) : show("Log in to vote"))} className={`w-full text-left p-3 rounded-xl bg-card border ${isVoted ? "border-primary" : "border-border"} hover:border-primary/40`}>
              <div className="flex justify-between"><span className="font-semibold">{o.label}</span><span className="text-sm">{pct}%</span></div>
              <div className="mt-2 h-2 bg-muted rounded-full overflow-hidden"><div className="h-full gradient-neon" style={{ width: `${pct}%` }} /></div>
            </button>
          );
        })}
      </div>
      <div className="mt-4 text-xs text-muted-foreground">{p.totalVotes.toLocaleString()} total votes</div>
      <div className="mt-6 flex flex-wrap gap-2">
        <Button variant="outline">Share poll</Button>
        {p.endsAt && +new Date(p.endsAt) > Date.now() && (
          <NotifyButton
            label="Notify before close"
            reminder={{
              id: `poll:${p.id}`,
              kind: "poll",
              title: `🗳️ Poll closing soon`,
              body: `${p.title} — closes in 1 hour`,
              url: `/polls/${p.slug}`,
              fireAt: p.endsAt,
              leadMinutes: 60,
            }}
          />
        )}
      </div>
    </div>
  );
}

