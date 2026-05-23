import type { CmsProvider } from "./types";
// Placeholder. Activated later when Supabase is connected.
export const supabaseCmsProvider: CmsProvider = {
  name: "supabase",
  async listArticles() { return []; },
  async getArticleBySlug() { return null; },
  async getRelated() { return []; },
};
