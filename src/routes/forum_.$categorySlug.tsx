import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useState } from "react";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { communityProvider } from "@/services/community";
import type { ForumCategory, ForumThread } from "@/types";

export const Route = createFileRoute("/forum_/$categorySlug")({
  head: ({ params }) =>
    buildHead({ title: params.categorySlug, canonical: `/forum/${params.categorySlug}` }),
  component: CategoryPage,
});

function CategoryPage() {
  const { categorySlug } = Route.useParams();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [category, setCategory] = useState<ForumCategory | null>(null);
  const [threads, setThreads] = useState<ForumThread[]>([]);
  const [formOpen, setFormOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [categories, nextThreads] = await Promise.all([
        communityProvider.listCategories(),
        communityProvider.listThreads({ category: categorySlug }),
      ]);
      setCategory(categories.find((item) => item.slug === categorySlug) ?? null);
      setThreads(nextThreads.items);
    } catch (loadError) {
      setError((loadError as Error).message);
    } finally {
      setLoading(false);
    }
  }, [categorySlug]);

  useEffect(() => {
    void load();
  }, [load]);

  async function createThread() {
    if (submitting || !title.trim() || !body.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      const created = await communityProvider.createThread({
        categorySlug,
        title: title.trim(),
        body: body.trim(),
        language: user?.language ?? "en",
      });
      setTitle("");
      setBody("");
      setFormOpen(false);
      if (created.status === "publish") {
        setThreads((current) => [created.item, ...current]);
        setNotice("Your thread is live.");
      } else {
        setNotice("Your thread was submitted for moderator review.");
      }
    } catch (submitError) {
      setError((submitError as Error).message);
    } finally {
      setSubmitting(false);
    }
  }

  if (!loading && !category)
    return (
      <div className="mx-auto max-w-5xl px-4 py-16 text-center">
        <h1 className="text-2xl font-bold">Forum category not found</h1>
        <Button asChild className="mt-4">
          <Link to="/forum">Back to forum</Link>
        </Button>
      </div>
    );

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div className="flex min-w-0 items-center gap-3">
          <div className="grid size-12 shrink-0 place-items-center rounded-lg bg-accent text-2xl">
            {category?.icon ?? "💬"}
          </div>
          <div className="min-w-0">
            <h1 className="font-display text-3xl font-bold">{category?.name ?? "Forum"}</h1>
            <p className="text-sm text-muted-foreground">{category?.description}</p>
          </div>
        </div>
        <Button
          className="shrink-0"
          onClick={() => (user ? setFormOpen((open) => !open) : show("Log in to create a thread"))}
        >
          Create thread
        </Button>
      </div>
      {formOpen && (
        <form
          className="mb-6 space-y-3 rounded-xl border border-border bg-card p-4"
          onSubmit={(event) => {
            event.preventDefault();
            void createThread();
          }}
        >
          <label className="block text-sm font-medium" htmlFor="thread-title">
            Title
          </label>
          <input
            id="thread-title"
            required
            minLength={5}
            maxLength={160}
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            className="h-10 w-full rounded-md border border-input bg-background px-3"
          />
          <label className="block text-sm font-medium" htmlFor="thread-body">
            Discussion
          </label>
          <textarea
            id="thread-body"
            required
            maxLength={10000}
            value={body}
            onChange={(event) => setBody(event.target.value)}
            className="min-h-32 w-full rounded-md border border-input bg-background p-3"
          />
          <div className="flex justify-end">
            <Button type="submit" disabled={submitting}>
              {submitting ? "Submitting…" : "Submit thread"}
            </Button>
          </div>
        </form>
      )}
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
        <p className="py-16 text-center text-muted-foreground">Loading threads…</p>
      ) : (
        <div className="grid gap-2">
          {threads.length === 0 && (
            <div className="py-16 text-center text-muted-foreground">No published threads yet.</div>
          )}
          {threads.map((thread) => (
            <Link
              key={thread.id}
              to="/thread/$threadSlug"
              params={{ threadSlug: thread.slug }}
              className="rounded-xl border border-border bg-card p-3 hover:border-primary/40"
            >
              <div className="font-semibold">{thread.title}</div>
              <div className="text-xs text-muted-foreground">
                {[thread.flair, thread.replies ? `${thread.replies} replies` : null, thread.views ? `${thread.views} views` : null, new Date(thread.lastActivityAt || thread.createdAt).toLocaleDateString()].filter(Boolean).join(" · ")}
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
