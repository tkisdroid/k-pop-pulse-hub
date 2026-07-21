import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { demoData, type DemoData } from "@/data/demo";
import type {
  Article,
  Artist,
  ComebackEvent,
  CommunityPost,
  ForumCategory,
  ForumThread,
  Member,
  Poll,
} from "@/types";

interface WordPressChartEntry {
  rank: number;
  artistSlug: string;
  trackTitle: string;
  previousRank: number | null;
  weeksOnChart: number;
}

interface WordPressChart {
  id: string;
  chartId: string;
  title: string;
  weekStartDate: string;
  entries: WordPressChartEntry[];
}

interface WordPressRuntimeBundle {
  articles: Article[];
  artists: Artist[];
  members: Member[];
  comebacks: ComebackEvent[];
  charts: WordPressChart[];
  threads: ForumThread[];
  polls: Poll[];
  community: CommunityPost[];
  categories: ForumCategory[];
}

function wordpressConfig() {
  if (typeof window === "undefined") return undefined;
  return window.kpopblogConfig;
}

export function isWordPressRuntime(): boolean {
  return Boolean(wordpressConfig()?.apiUrl);
}

async function fetchRuntimeBundle(): Promise<WordPressRuntimeBundle> {
  const apiUrl = wordpressConfig()?.apiUrl?.replace(/\/$/, "");
  if (!apiUrl) throw new Error("WordPress API URL is unavailable.");
  const response = await fetch(`${apiUrl}/bundle`, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  if (!response.ok) throw new Error(`WordPress content request failed (${response.status}).`);
  return (await response.json()) as WordPressRuntimeBundle;
}

function buildRuntimeData(bundle: WordPressRuntimeBundle): DemoData {
  const artistNameBySlug = new Map(bundle.artists.map((artist) => [artist.slug, artist.name]));
  const charts = bundle.charts.flatMap((chart) =>
    chart.entries.map((entry) => ({
      rank: entry.rank,
      title: entry.trackTitle,
      artist: artistNameBySlug.get(entry.artistSlug) ?? entry.artistSlug,
      change: entry.previousRank === null ? 0 : entry.previousRank - entry.rank,
    })),
  );

  return {
    ...demoData,
    articles: bundle.articles,
    artists: bundle.artists,
    members: bundle.members,
    comebacks: bundle.comebacks,
    charts,
    threads: bundle.threads,
    polls: bundle.polls,
    community: bundle.community,
    categories: bundle.categories,
    comments: [],
    users: [],
  } as DemoData;
}

export function useRuntimeData() {
  const wordpress = isWordPressRuntime();
  const query = useQuery({
    queryKey: ["kpopblog", "runtime-bundle"],
    queryFn: fetchRuntimeBundle,
    enabled: wordpress,
    staleTime: 60_000,
    retry: 1,
  });
  const data = useMemo(
    () => (wordpress && query.data ? buildRuntimeData(query.data) : demoData),
    [wordpress, query.data],
  );

  return {
    data,
    isLoading: wordpress && query.isPending,
    error: wordpress && query.error instanceof Error ? query.error.message : null,
    refetch: query.refetch,
  };
}
