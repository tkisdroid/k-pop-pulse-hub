import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Bookmark as BookmarkIcon, Trash2 } from "lucide-react";
import { bookmarks, type Bookmark } from "@/services/bookmarks";
import { Button } from "@/components/ui/button";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/bookmarks")({
  head: () =>
    buildHead({
      title: "Saved articles",
      description: "Your bookmarked K-pop stories, saved for later.",
      canonical: "/bookmarks",
    }),
  component: BookmarksPage,
});

function BookmarksPage() {
  const [items, setItems] = useState<Bookmark[]>([]);

  useEffect(() => {
    const refresh = () => setItems(bookmarks.list());
    refresh();
    window.addEventListener("bookmarks:changed", refresh);
    return () => window.removeEventListener("bookmarks:changed", refresh);
  }, []);

  return (
    <main className="mx-auto max-w-5xl px-4 py-10">
      <header className="flex items-end justify-between mb-8">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-wider text-primary font-semibold">
            <BookmarkIcon className="size-3" /> Library
          </div>
          <h1 className="font-display text-3xl md:text-4xl font-bold mt-1">Saved for later</h1>
          <p className="text-sm text-muted-foreground mt-2">
            {items.length} saved article{items.length === 1 ? "" : "s"}. Stored on this device only.
          </p>
        </div>
        {items.length > 0 && (
          <Button variant="ghost" size="sm" onClick={() => bookmarks.clear()}>
            <Trash2 className="size-3" /> Clear all
          </Button>
        )}
      </header>

      {items.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border bg-muted/30 p-12 text-center">
          <BookmarkIcon className="size-8 mx-auto mb-3 text-muted-foreground" />
          <p className="text-sm text-muted-foreground mb-4">
            You haven't saved any articles yet. Tap the Save button on any story to add it here.
          </p>
          <Button asChild size="sm">
            <Link to="/latest">Browse latest news</Link>
          </Button>
        </div>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2">
          {items.map((b) => (
            <li
              key={b.id}
              className="group rounded-xl overflow-hidden border border-border bg-card hover:border-primary/40 transition"
            >
              <Link to="/news/$slug" params={{ slug: b.slug }} className="block">
                {b.image && (
                  <img
                    src={b.image}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    width={640}
                    height={360}
                    className="w-full aspect-video object-cover"
                  />
                )}
                <div className="p-4">
                  <h2 className="font-display text-base font-semibold leading-snug line-clamp-2 group-hover:text-primary">
                    {b.title}
                  </h2>
                  <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                    <span>Saved {new Date(b.savedAt).toLocaleDateString()}</span>
                    <button
                      onClick={(e) => {
                        e.preventDefault();
                        bookmarks.remove(b.id);
                      }}
                      className="hover:text-destructive"
                      aria-label="Remove bookmark"
                    >
                      <Trash2 className="size-3" />
                    </button>
                  </div>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
