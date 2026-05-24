import { useEffect, useState } from "react";
import { Link } from "@tanstack/react-router";
import { Clock } from "lucide-react";
import { recentlyViewed, type RecentlyViewedEntry } from "@/services/recentlyViewed";

export function RecentlyViewedRail({ excludeId }: { excludeId?: string }) {
  const [items, setItems] = useState<RecentlyViewedEntry[]>([]);
  useEffect(() => {
    setItems(recentlyViewed.list().filter((x) => x.id !== excludeId));
  }, [excludeId]);
  if (items.length === 0) return null;
  return (
    <aside className="mt-10 rounded-xl border border-border bg-card/40 p-4">
      <div className="flex items-center gap-2 text-xs uppercase tracking-wider text-muted-foreground mb-3">
        <Clock className="size-3" /> Recently viewed
      </div>
      <ul className="grid gap-2 sm:grid-cols-2">
        {items.slice(0, 6).map((it) => (
          <li key={it.id}>
            <Link
              to="/news/$slug"
              params={{ slug: it.slug }}
              className="flex gap-3 items-center group"
            >
              {it.image && (
                <img
                  src={it.image}
                  alt=""
                  loading="lazy"
                  decoding="async"
                  width={80}
                  height={56}
                  className="size-14 rounded-md object-cover shrink-0"
                />
              )}
              <span className="text-sm leading-snug line-clamp-2 group-hover:text-primary">
                {it.title}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </aside>
  );
}
