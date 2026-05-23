import type { Article } from "@/types";

export interface CmsProvider {
  name: string;
  listArticles(opts?: { category?: string; tag?: string; author?: string; artistId?: string; limit?: number; search?: string }): Promise<Article[]>;
  getArticleBySlug(slug: string): Promise<Article | null>;
  getRelated(article: Article, limit?: number): Promise<Article[]>;
}
