import type { CmsProvider } from "./types";
// Placeholder. Activated later when VITE_WORDPRESS_API_URL is set.
export const wordpressCmsProvider: CmsProvider = {
  name: "wordpress",
  async listArticles() { return []; },
  async getArticleBySlug() { return null; },
  async getRelated() { return []; },
};
