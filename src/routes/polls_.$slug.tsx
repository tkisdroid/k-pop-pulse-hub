import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { useState } from "react";
import { NotifyButton } from "@/components/notifications/NotifyButton";
import { cmsProvider } from "@/services/cms";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { ShareButtons } from "@/components/articles/ShareButtons";

export const Route = createFileRoute("/polls_/$slug")({
  head: ({ params }) => buildHead({ title: "Poll", canonical: `/polls/${params.slug}` }),
  component: PollPage,
});

function PollPage() {
  const { slug } = Route.useParams();
  const { data, isLoading, error } = useRuntimeData();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [voted, setVoted] = useState<string | null>(null);
  const [voting, setVoting] = useState(false);
  const [voteError, setVoteError] = useState<string | null>(null);

  if (isLoading)
    return <div className="mx-auto max-w-3xl px-4 py-12 text-muted-foreground">Loading poll…</div>;
  if (error) return <div className="mx-auto max-w-3xl px-4 py-12 text-destructive">{error}</div>;

  const p = data.polls.find((item) => item.slug === slug);
  if (!p)
    return (
      <div className="mx-auto max-w-3xl px-4 py-12 text-muted-foreground">Poll not found.</div>
    );

  const vote = async (optionId: string) => {
    if (!user) {
      show("Log in to vote");
      return;
    }
    if (voting || voted) return;
    setVoting(true);
    setVoteError(null);
    try {
      const result = await cmsProvider.votePoll?.(p.slug, optionId);
      if (!result?.ok) throw new Error(result?.error || "Vote could not be saved.");
      setVoted(optionId);
    } catch (voteRequestError) {
      setVoteError(
        voteRequestError instanceof Error ? voteRequestError.message : "Vote could not be saved.",
      );
    } finally {
      setVoting(false);
    }
  };

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <h1 className="font-display text-3xl font-bold mb-2">{p.title}</h1>
      <p className="text-muted-foreground mb-6">
        {p.description ?? "Cast your vote and see what fans think."}
      </p>
      <div className="space-y-3">
        {p.options.map((o: { id: string; label: string; votes: number }) => {
          const pct = p.totalVotes > 0 ? Math.round((o.votes / p.totalVotes) * 100) : 0;
          const isVoted = voted === o.id;
          return (
            <button
              key={o.id}
              type="button"
              disabled={voting || Boolean(voted)}
              onClick={() => void vote(o.id)}
              className={`w-full text-left p-3 rounded-xl bg-card border ${isVoted ? "border-primary" : "border-border"} hover:border-primary/40 disabled:cursor-not-allowed disabled:opacity-70`}
            >
              <div className="flex justify-between">
                <span className="font-semibold">{o.label}</span>
                <span className="text-sm">{pct}%</span>
              </div>
              <div className="mt-2 h-2 bg-muted rounded-full overflow-hidden">
                <div className="h-full gradient-neon" style={{ width: `${pct}%` }} />
              </div>
            </button>
          );
        })}
      </div>
      {voteError && (
        <div role="alert" className="mt-3 text-sm text-destructive">
          {voteError}
        </div>
      )}
      <div className="mt-4 text-xs text-muted-foreground">
        {p.totalVotes.toLocaleString()} total votes
      </div>
      <div className="mt-6 flex flex-wrap gap-2">
        <ShareButtons
          title={p.title}
          url={typeof window !== "undefined" ? window.location.href : `/polls/${p.slug}`}
        />
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
