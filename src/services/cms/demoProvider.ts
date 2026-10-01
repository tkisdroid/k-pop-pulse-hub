import { demoData } from "@/data/demo";
import { aiProvider } from "@/services/ai";
import { adaptArticle } from "@/services/ai/adapters";
import type { Article } from "@/types";
import type { CmsProvider } from "./types";

const artistSlugToId = new Map(demoData.artists.map((a) => [a.slug, a.id]));

async function mergedArticles(): Promise<Article[]> {
  const aiArticles = await aiProvider.list("article");
  const adapted = aiArticles.map((a, i) => adaptArticle(a, i, artistSlugToId));
  // AI content takes priority — fresher and Zod-validated.
  return [...adapted, ...demoData.articles];
}

export const demoCmsProvider: CmsProvider = {
  name: "demo",
  async listArticles(opts = {}) {
    let out = await mergedArticles();
    if (opts.category)
      out = out.filter((a) => a.category.toLowerCase() === opts.category!.toLowerCase());
    if (opts.tag) out = out.filter((a) => a.tags.includes(opts.tag!));
    if (opts.author)
      out = out.filter((a) => a.author.toLowerCase().replace(/\s+/g, "-") === opts.author);
    if (opts.artistId) out = out.filter((a) => a.relatedArtistIds.includes(opts.artistId!));
    if (opts.search) {
      const q = opts.search.toLowerCase();
      out = out.filter(
        (a) => a.title.toLowerCase().includes(q) || a.excerpt.toLowerCase().includes(q),
      );
    }
    if (opts.limit) out = out.slice(0, opts.limit);
    return out;
  },
  async getArticleBySlug(slug) {
    const all = await mergedArticles();
    return all.find((a) => a.slug === slug) ?? null;
  },
  async getRelated(article, limit = 4) {
    const all = await mergedArticles();
    return all
      .filter(
        (a) =>
          a.id !== article.id &&
          a.relatedArtistIds.some((id) => article.relatedArtistIds.includes(id)),
      )
      .slice(0, limit);
  },
};
