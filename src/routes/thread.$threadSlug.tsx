import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useState } from "react";
import { LinkifiedText } from "@/components/layout/LinkifiedText";
import { buildHead } from "@/components/layout/seo";
import { LocalTime } from "@/components/layout/LocalTime";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { communityProvider } from "@/services/community";
import type { ForumPost, ForumThread } from "@/types";
import { Flag, Lock, Pin } from "lucide-react";

export const Route = createFileRoute("/thread/$threadSlug")({
  head: ({ params }) =>
    buildHead({ title: "Forum thread", canonical: `/thread/${params.threadSlug}` }),
  component: ThreadPage,
});

function ThreadPage() {
  const { threadSlug } = Route.useParams();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [thread, setThread] = useState<ForumThread | null>(null);
  const [posts, setPosts] = useState<ForumPost[]>([]);
  const [draft, setDraft] = useState("");
  const [loading, setLoading] = useState(true);
  const [posting, setPosting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const nextThread = await communityProvider.getThread(threadSlug);
      if (!nextThread) {
        setThread(null);
        setPosts([]);
        return;
      }
      const replies = await communityProvider.listReplies(threadSlug);
      setThread(nextThread);
      setPosts(replies.items);
    } catch (loadError) {
      setError((loadError as Error).message);
    } finally {
      setLoading(false);
    }
  }, [threadSlug]);

  useEffect(() => {
    void load();
  }, [load]);

  // Count one view per thread per browser session (the server also rate-limits).
  useEffect(() => {
    const apiUrl = typeof window !== "undefined" ? window.kpopblogConfig?.apiUrl : undefined;
    if (!apiUrl || !thread) return;
    const key = `kb-thread-view:${thread.slug}`;
    try {
      if (sessionStorage.getItem(key)) return;
      sessionStorage.setItem(key, "1");
    } catch {
      /* storage unavailable: the server-side limit still applies */
    }
    void fetch(`${apiUrl.replace(/\/$/, "")}/threads/${encodeURIComponent(thread.slug)}/view`, { method: "POST", credentials: "same-origin" }).catch(() => undefined);
  }, [thread]);

  async function reply() {
    if (!draft.trim() || posting || !thread) return;
    setPosting(true);
    setError(null);
    try {
      const created = await communityProvider.createReply(thread.slug, draft.trim());
      setDraft("");
      if (created.pending) setNotice("Your reply was submitted for moderator review.");
      else setPosts((current) => [...current, created.item]);
    } catch (replyError) {
      setError((replyError as Error).message);
    } finally {
      setPosting(false);
    }
  }

  async function report(targetType: "thread" | "reply", targetId: string) {
    if (!user) {
      show("Log in to report content");
      return;
    }
    const reason = window.prompt("Briefly explain why this content should be reviewed:");
    if (!reason?.trim()) return;
    try {
      await communityProvider.report({ targetType, targetId, reason: reason.trim() });
      setNotice("Report submitted to the moderation queue.");
    } catch (reportError) {
      setError((reportError as Error).message);
    }
  }

  if (loading)
    return (
      <div className="mx-auto max-w-4xl px-4 py-16 text-center text-muted-foreground">
        Loading discussion…
      </div>
    );
  if (!thread)
    return (
      <div className="mx-auto max-w-4xl px-4 py-16 text-center">
        <h1 className="text-2xl font-bold">Thread not found</h1>
        <Button asChild className="mt-4">
          <Link to="/forum">Back to forum</Link>
        </Button>
      </div>
    );

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
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
      {notice && (
        <p className="mb-4 rounded-md bg-accent p-3 text-sm" role="status">
          {notice}
        </p>
      )}
      <div className="mb-2 flex flex-wrap items-center gap-2 text-xs">
        {thread.pinned && (
          <span className="rounded bg-primary px-1.5 py-0.5 text-primary-foreground">
            <Pin className="inline size-3" /> PINNED
          </span>
        )}
        {thread.locked && (
          <span className="rounded bg-muted px-1.5 py-0.5">
            <Lock className="inline size-3" /> LOCKED
          </span>
        )}
        {thread.flair && <span className="rounded bg-accent px-1.5 py-0.5">{thread.flair}</span>}
        {thread.rumor && (
          <span className="rounded bg-destructive px-1.5 py-0.5 text-destructive-foreground">
            RUMOR — verify sources
          </span>
        )}
      </div>
      <h1 className="font-display text-3xl font-bold">{thread.title}</h1>
      <div className="mt-3 flex items-center gap-3 text-sm">
        {thread.author?.avatar && (
          <img src={thread.author.avatar} alt="" className="size-8 rounded-full" />
        )}
        <span className="font-medium">{thread.author?.displayName ?? "Community member"}</span>
        <span className="text-muted-foreground">
          · <LocalTime value={thread.createdAt} mode="date" />
        </span>
      </div>
      <article className="mt-4 rounded-xl border border-border bg-card p-4">
        <LinkifiedText text={thread.body} className="space-y-1 break-words" />
        <div className="mt-3 flex gap-2 text-xs">
          <Button size="sm" variant="ghost" onClick={() => void report("thread", thread.id)}>
            <Flag className="size-3" /> Report
          </Button>
        </div>
      </article>

      <h2 className="mb-3 mt-8 font-display text-xl font-bold">{posts.length} published replies</h2>
      <div className="space-y-3">
        {posts.length === 0 && (
          <p className="py-8 text-center text-muted-foreground">No published replies yet.</p>
        )}
        {posts.map((post) => (
          <article key={post.id} className="rounded-xl border border-border bg-card p-3">
            <div className="mb-1 flex items-center gap-2 text-sm">
              {post.author?.avatar && (
                <img src={post.author.avatar} alt="" className="size-7 rounded-full" />
              )}
              <span className="font-medium">{post.author?.displayName ?? "Community member"}</span>
              <span className="text-xs text-muted-foreground">
                · <LocalTime value={post.createdAt} />
              </span>
            </div>
            <p className="whitespace-pre-wrap text-sm">{post.body}</p>
            <div className="mt-2 flex justify-end">
              <button
                type="button"
                className="text-xs text-muted-foreground hover:text-destructive"
                onClick={() => void report("reply", post.id)}
              >
                Report
              </button>
            </div>
          </article>
        ))}
      </div>

      <div className="mt-8">
        {user ? (
          thread.locked ? (
            <p className="rounded-md bg-muted p-4 text-sm">This thread is locked.</p>
          ) : (
            <form
              onSubmit={(event) => {
                event.preventDefault();
                void reply();
              }}
            >
              <label htmlFor="reply-body" className="sr-only">
                Write a reply
              </label>
              <textarea
                id="reply-body"
                required
                maxLength={10000}
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                className="min-h-24 w-full rounded-md border border-input bg-background p-3"
                placeholder="Write a reply..."
              />
              <div className="mt-2 flex justify-end">
                <Button type="submit" disabled={posting || !draft.trim()}>
                  {posting ? "Posting…" : "Post reply"}
                </Button>
              </div>
            </form>
          )
        ) : (
          <div className="flex items-center justify-between rounded-md bg-muted/40 p-4">
            <span className="text-sm">Log in to reply.</span>
            <Button size="sm" onClick={() => show("Log in to reply")}>
              Log in
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
