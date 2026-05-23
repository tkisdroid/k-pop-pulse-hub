import type { Article } from "@/types";

export interface CmsProvider {
  name: string;
  listArticles(opts?: { category?: string; tag?: string; author?: string; artistId?: string; limit?: number; search?: string }): Promise<Article[]>;
  getArticleBySlug(slug: string): Promise<Article | null>;
  getRelated(article: Article, limit?: number): Promise<Article[]>;
  /** Write methods. Providers without a backend return { ok:false }. */
  postComment?(articleSlug: string, body: string): Promise<{ ok: boolean; id?: string; pending?: boolean; error?: string }>;
  votePoll?(pollSlug: string, optionId: string): Promise<{ ok: boolean; error?: string }>;
  toggleFollowArtist?(artistSlug: string): Promise<{ ok: boolean; following?: boolean; followerCount?: number; error?: string }>;
  recordEngagement?(articleSlug: string, kind: "view" | "reaction"): Promise<void>;
}
