import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useState } from "react";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Button } from "@/components/ui/button";
import { communityProvider } from "@/services/community";
import type { ForumCategory, ForumThread } from "@/types";

export const Route = createFileRoute("/forum")({
  head: () =>
    buildHead({
      title: "K-pop Forum",
      description: "Fan community discussions.",
      canonical: "/forum",
    }),
  component: ForumIndex,
});

function ForumIndex() {
  const [categories, setCategories] = useState<ForumCategory[]>([]);
  const [threads, setThreads] = useState<ForumThread[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [nextCategories, nextThreads] = await Promise.all([
        communityProvider.listCategories(),
        communityProvider.listThreads({ perPage: 8 }),
      ]);
      setCategories(nextCategories);
      setThreads(nextThreads.items);
    } catch (loadError) {
      setError((loadError as Error).message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="mx-auto grid max-w-7xl gap-8 px-4 py-8 lg:grid-cols-3">
      <div className="lg:col-span-2">
        <SectionHeader eyebrow="Forum" title="Categories" />
        <AdSlot slotId="forum-top" variant="leaderboard" />
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
          <p className="py-12 text-center text-muted-foreground">Loading forum…</p>
        ) : (
          <>
            <div className="grid gap-3 sm:grid-cols-2">
              {categories.length === 0 && (
                <p className="text-sm text-muted-foreground">No forum categories are configured.</p>
              )}
              {categories.map((category) => (
                <Link
                  key={category.id}
                  to="/forum/$categorySlug"
                  params={{ categorySlug: category.slug }}
                  className="rounded-xl border border-border bg-card p-4 hover:border-primary/40"
                >
                  <div className="mb-2 flex items-center gap-3">
                    <div className="grid size-10 place-items-center rounded-lg bg-accent text-xl">
                      {category.icon}
                    </div>
                    <div className="font-display font-bold">{category.name}</div>
                  </div>
                  <p className="text-sm text-muted-foreground">{category.description}</p>
                  <div className="mt-3 text-xs text-muted-foreground">
                    {[`${category.threadCount} threads`, category.postCount ? `${category.postCount} replies` : null].filter(Boolean).join(" · ")}
                  </div>
                </Link>
              ))}
            </div>
            <SectionHeader title="Latest threads" />
            <div className="grid gap-2">
              {threads.length === 0 && (
                <p className="py-8 text-center text-muted-foreground">No published threads yet.</p>
              )}
              {threads.map((thread) => (
                <Link
                  key={thread.id}
                  to="/thread/$threadSlug"
                  params={{ threadSlug: thread.slug }}
                  className="rounded-xl border border-border bg-card p-3 hover:border-primary/40"
                >
                  <div className="mb-1 flex flex-wrap items-center gap-2">
                    {thread.pinned && (
                      <span className="rounded bg-primary px-1.5 py-0.5 text-[10px] text-primary-foreground">
                        PINNED
                      </span>
                    )}
                    {thread.flair && (
                      <span className="rounded bg-accent px-1.5 py-0.5 text-[10px]">
                        {thread.flair}
                      </span>
                    )}
                    {thread.rumor && (
                      <span className="rounded bg-destructive px-1.5 py-0.5 text-[10px] text-destructive-foreground">
                        RUMOR
                      </span>
                    )}
                  </div>
                  <div className="font-semibold">{thread.title}</div>
                  <div className="text-xs text-muted-foreground">
                    {[thread.flair, thread.replies ? `${thread.replies} replies` : null, thread.views ? `${thread.views} views` : null].filter(Boolean).join(" · ") || "New discussion"}
                  </div>
                </Link>
              ))}
            </div>
          </>
        )}
      </div>
      <aside className="space-y-4">
        <div className="rounded-xl border border-border bg-card p-4">
          <h3 className="mb-2 font-semibold">New here?</h3>
          <p className="mb-3 text-sm text-muted-foreground">
            Welcome to the global K-pop community. Read the rules before posting.
          </p>
          <Button asChild size="sm">
            <Link to="/community-guidelines">Read community rules</Link>
          </Button>
        </div>
        <div className="rounded-xl border border-border bg-card p-4">
          <h3 className="mb-2 font-semibold">Start a discussion</h3>
          <p className="mb-3 text-sm text-muted-foreground">
            Choose a category to create a moderated thread.
          </p>
          {categories[0] ? (
            <Button asChild size="sm">
              <Link to="/forum/$categorySlug" params={{ categorySlug: categories[0].slug }}>
                Choose category
              </Link>
            </Button>
          ) : (
            <Button size="sm" disabled>
              No categories
            </Button>
          )}
        </div>
        <div className="rounded-xl border border-dashed border-border bg-muted/40 p-4 text-center text-xs text-muted-foreground">
          Sidebar ad slot
        </div>
      </aside>
    </div>
  );
}
