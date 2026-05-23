import { useMemo } from "react";
import { Sparkles } from "lucide-react";
import { Link } from "@tanstack/react-router";
import type { Article } from "@/types";
import { personalization } from "@/services/personalization";
import { ArticleCard } from "./ArticleCard";

export function ForYouSection({ pool }: { pool: Article[] }) {
  const recs = useMemo(() => personalization.recommend(pool, 6), [pool]);
  const signals = personalization.signals();
  const hasSignals = signals.followedArtists.length > 0 || signals.viewedArticleIds.length > 0;

  if (recs.length === 0) return null;

  return (
    <section className="mx-auto max-w-7xl px-4 py-10">
      <div className="flex items-end justify-between mb-4">
        <div>
          <div className="flex items-center gap-2 text-xs uppercase tracking-wider text-primary font-semibold">
            <Sparkles className="size-3" /> For You
          </div>
          <h2 className="font-display text-2xl md:text-3xl font-bold mt-1">
            {hasSignals ? "Picked from your taste" : "Trending picks you might like"}
          </h2>
          <p className="text-sm text-muted-foreground mt-1">
            {hasSignals
              ? `Based on ${signals.followedArtists.length} followed artists and your reading history.`
              : "Follow artists and read more to personalize this feed."}
          </p>
        </div>
        <Link to="/latest" className="text-sm text-primary hover:underline shrink-0">See all</Link>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {recs.map((a) => <ArticleCard key={a.id} article={a} variant="compact" />)}
      </div>
    </section>
  );
}
