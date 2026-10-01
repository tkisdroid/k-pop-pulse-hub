import { createFileRoute } from "@tanstack/react-router";
import { useCallback, useEffect, useState } from "react";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { communityProvider } from "@/services/community";
import type { CommunityPost } from "@/types";

export const Route = createFileRoute("/community")({
  head: () => buildHead({ title: "Community Wall", canonical: "/community" }),
  component: Community,
});

function Community() {
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [items, setItems] = useState<CommunityPost[]>([]);
  const [body, setBody] = useState("");
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setItems((await communityProvider.listCommunity()).items);
    } catch (loadError) {
      setError((loadError as Error).message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function submit() {
    if (!body.trim() || submitting) return;
    setSubmitting(true);
    setError(null);
    setNotice(null);
    try {
      const created = await communityProvider.createCommunity({
        body: body.trim(),
        language: user?.language ?? "en",
      });
      setBody("");
      if (created.status === "publish") {
        setItems((current) => [created.item, ...current]);
        setNotice("Your post is live.");
      } else {
        setNotice("Your post was submitted for moderator review.");
      }
    } catch (submitError) {
      setError((submitError as Error).message);
    } finally {
      setSubmitting(false);
    }
  }

  async function report(item: CommunityPost) {
    if (!user) {
      show("Log in to report content");
      return;
    }
    const reason = window.prompt("Briefly explain why this post should be reviewed:");
    if (!reason?.trim()) return;
    try {
      await communityProvider.report({
        targetType: "community",
        targetId: item.id,
        reason: reason.trim(),
      });
      setNotice("Report submitted to the moderation queue.");
    } catch (reportError) {
      setError((reportError as Error).message);
    }
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <SectionHeader
        as="h1"
        eyebrow="Community"
        title="The Wall"
        subtitle="Quick fan thoughts from around the world."
      />
      <AdSlot slotId="community-top" variant="leaderboard" />
      <div className="mb-6 rounded-xl border border-border bg-card p-4">
        {user ? (
          <form
            onSubmit={(event) => {
              event.preventDefault();
              void submit();
            }}
          >
            <label htmlFor="community-body" className="sr-only">
              Community post
            </label>
            <textarea
              id="community-body"
              maxLength={2000}
              required
              value={body}
              onChange={(event) => setBody(event.target.value)}
              placeholder="Share a thought..."
              className="min-h-20 w-full rounded-md border border-input bg-background p-3"
            />
            <div className="mt-2 flex items-center justify-between gap-3">
              <span className="text-xs text-muted-foreground">{body.length}/2000</span>
              <Button type="submit" disabled={submitting || !body.trim()}>
                {submitting ? "Posting…" : "Post"}
              </Button>
            </div>
          </form>
        ) : (
          <div className="flex items-center justify-between">
            <span className="text-sm">Log in to post.</span>
            <Button size="sm" onClick={() => show()}>
              Log in
            </Button>
          </div>
        )}
      </div>
      {notice && (
        <p className="mb-4 rounded-md bg-accent p-3 text-sm" role="status">
          {notice}
        </p>
      )}
      {error && (
        <div
          className="mb-4 rounded-md border border-destructive/40 p-3 text-sm text-destructive"
          role="alert"
        >
          {error}{" "}
          <Button size="sm" variant="ghost" onClick={() => void load()}>
            Retry
          </Button>
        </div>
      )}
      {loading ? (
        <p className="py-12 text-center text-muted-foreground">Loading community posts…</p>
      ) : (
        <div className="grid gap-3">
          {items.length === 0 && (
            <p className="py-12 text-center text-muted-foreground">No published posts yet.</p>
          )}
          {items.map((item) => (
            <article key={item.id} className="rounded-xl border border-border bg-card p-4">
              <div className="mb-2 flex items-center gap-2">
                {item.author?.avatar && (
                  <img src={item.author.avatar} alt="" className="size-7 rounded-full" />
                )}
                <div className="text-sm">
                  <span className="font-semibold">
                    {item.author?.displayName ?? "Community member"}
                  </span>{" "}
                  <span className="text-xs text-muted-foreground">
                    · {item.language.toUpperCase()}
                  </span>
                </div>
              </div>
              <p className="whitespace-pre-wrap text-sm">{item.body}</p>
              <div className="mt-2 flex gap-3 text-xs text-muted-foreground">
                <span>♥ {item.reactions}</span>
                <button
                  type="button"
                  className="ml-auto hover:text-destructive"
                  onClick={() => void report(item)}
                >
                  Report
                </button>
              </div>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
