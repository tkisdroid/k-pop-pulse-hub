import { demoData } from "@/data/demo";
import type { CmsProvider } from "./types";

export const demoCmsProvider: CmsProvider = {
  name: "demo",
  async listArticles(opts = {}) {
    let out = [...demoData.articles];
    if (opts.category) out = out.filter((a) => a.category.toLowerCase() === opts.category!.toLowerCase());
    if (opts.tag) out = out.filter((a) => a.tags.includes(opts.tag!));
    if (opts.author) out = out.filter((a) => a.author.toLowerCase().replace(/\s+/g, "-") === opts.author);
    if (opts.artistId) out = out.filter((a) => a.relatedArtistIds.includes(opts.artistId!));
    if (opts.search) {
      const q = opts.search.toLowerCase();
      out = out.filter((a) => a.title.toLowerCase().includes(q) || a.excerpt.toLowerCase().includes(q));
    }
    if (opts.limit) out = out.slice(0, opts.limit);
    return out;
  },
  async getArticleBySlug(slug) {
    return demoData.articles.find((a) => a.slug === slug) ?? null;
  },
  async getRelated(article, limit = 4) {
    return demoData.articles.filter((a) => a.id !== article.id && a.relatedArtistIds.some((id) => article.relatedArtistIds.includes(id))).slice(0, limit);
  },
};
