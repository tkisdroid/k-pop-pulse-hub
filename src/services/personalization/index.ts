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
const CONSENT_KEY = "kpopblog:personalization:consent";
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

function hasConsent(): boolean {
  if (typeof localStorage === "undefined") return false;
  try {
    return localStorage.getItem(CONSENT_KEY) === "1";
  } catch {
    return false;
  }
}

function setConsent(enabled: boolean) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(CONSENT_KEY, enabled ? "1" : "0");
}

function resetData() {
  if (typeof localStorage === "undefined") return;
  localStorage.removeItem(KEY);
}

export const personalization = {
  signals: read,
  hasConsent,
  setConsent,
  resetData,

  followArtist(id: string) {
    if (!hasConsent()) return;
    const s = read();
    if (!s.followedArtists.includes(id)) s.followedArtists.push(id);
    write(s);
  },
  unfollowArtist(id: string) {
    if (!hasConsent()) return;
    const s = read();
    s.followedArtists = s.followedArtists.filter((x) => x !== id);
    write(s);
  },
  isFollowing(id: string) {
    return read().followedArtists.includes(id);
  },
  recordView(article: Pick<Article, "id" | "tags">) {
    if (!hasConsent()) return;
    const s = read();
    s.viewedArticleIds = [article.id, ...s.viewedArticleIds.filter((x) => x !== article.id)].slice(0, VIEW_LIMIT);
    for (const t of article.tags ?? []) s.likedTags[t] = (s.likedTags[t] ?? 0) + 1;
    write(s);
  },

  score(article: Article): number {
    if (!hasConsent()) return -1;
    const s = read();
    let score = 0;
    if (s.viewedArticleIds.includes(article.id)) return -1;
    // Followed-artist match is the strongest signal (weighted per match).
    const matchedFollows = article.relatedArtistIds?.filter((id) => s.followedArtists.includes(id)).length ?? 0;
    score += matchedFollows * 12;
    // Tag affinity, capped to avoid runaway from a single hot tag.
    for (const t of article.tags ?? []) score += Math.min(s.likedTags[t] ?? 0, 10) * 1.5;
    // Recency boost: linear decay over 7 days, max +6.
    const ageDays = (Date.now() - +new Date(article.publishedAt)) / (1000 * 60 * 60 * 24);
    score += Math.max(0, 6 - ageDays * (6 / 7));
    // Engagement signal (sublinear so megaviral doesn't dominate).
    score += Math.log10(1 + (article.viewCount ?? 0)) * 0.6;
    return score;
  },

  recommend(pool: Article[], limit = 6): Article[] {
    if (!hasConsent()) return [];
    return pool
      .map((a) => ({ a, score: this.score(a) }))
      .filter((x) => x.score >= 0)
      .sort((a, b) => b.score - a.score)
      .slice(0, limit)
      .map((x) => x.a);
  },
};
