/**
 * Personalization signals stored client-side.
 * Tracks viewed articles, followed artists, and liked tags; scores
 * candidate articles by overlap.
 */
import type { Article } from "@/types";

interface Signals {
  followedArtists: string[];
  likedTags: Record<string, number>;
  viewedArticleIds: string[];
}

const KEY = "kpopblog:personalization";
const VIEW_LIMIT = 100;

function read(): Signals {
  if (typeof localStorage === "undefined") return { followedArtists: [], likedTags: {}, viewedArticleIds: [] };
  try {
    const parsed = JSON.parse(localStorage.getItem(KEY) ?? "{}");
    return {
      followedArtists: parsed.followedArtists ?? [],
      likedTags: parsed.likedTags ?? {},
      viewedArticleIds: parsed.viewedArticleIds ?? [],
    };
  } catch {
    return { followedArtists: [], likedTags: {}, viewedArticleIds: [] };
  }
}

function write(s: Signals) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(KEY, JSON.stringify(s));
}

export const personalization = {
  signals: read,

  followArtist(id: string) {
    const s = read();
    if (!s.followedArtists.includes(id)) s.followedArtists.push(id);
    write(s);
  },
  unfollowArtist(id: string) {
    const s = read();
    s.followedArtists = s.followedArtists.filter((x) => x !== id);
    write(s);
  },
  isFollowing(id: string) {
    return read().followedArtists.includes(id);
  },
  recordView(article: Pick<Article, "id" | "tags">) {
    const s = read();
    s.viewedArticleIds = [article.id, ...s.viewedArticleIds.filter((x) => x !== article.id)].slice(0, VIEW_LIMIT);
    for (const t of article.tags ?? []) s.likedTags[t] = (s.likedTags[t] ?? 0) + 1;
    write(s);
  },

  score(article: Article): number {
    const s = read();
    let score = 0;
    if (s.viewedArticleIds.includes(article.id)) return -1;
    if (article.relatedArtistIds?.some((id) => s.followedArtists.includes(id))) score += 8;
    for (const t of article.tags ?? []) score += (s.likedTags[t] ?? 0) * 1.2;
    score += Math.max(0, 4 - (Date.now() - +new Date(article.publishedAt)) / (1000 * 60 * 60 * 24)); // recency boost (4d)
    return score;
  },

  recommend(pool: Article[], limit = 6): Article[] {
    return pool
      .map((a) => ({ a, score: this.score(a) }))
      .filter((x) => x.score >= 0)
      .sort((a, b) => b.score - a.score)
      .slice(0, limit)
      .map((x) => x.a);
  },
};
