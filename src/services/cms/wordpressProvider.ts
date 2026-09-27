/**
 * WordPress CMS provider — reads from the KpopBlog plugin's unified REST API
 * at /wp-json/kpopblog/v1/*. Activated automatically when the React app is
 * mounted inside WordPress (window.kpopblogConfig is injected by the plugin)
 * or when VITE_WORDPRESS_API_URL is set during a standalone build.
 *
 * The PHP plugin already maps responses to the React app's TypeScript types,
 * so this provider does no transformation beyond fetch + JSON parse.
 */
import type { Article, Comment } from "@/types";
import type { CmsProvider } from "./types";

declare global {
  interface Window {
    kpopblogConfig?: {
      apiUrl?: string;
      nonce?: string;
      locale?: string;
      registrationEnabled?: boolean;
      adminUrl?: string;
      branding?: {
        siteName?: string;
        accentWord?: string;
        tagline?: string;
        accentColor?: string;
        logoUrl?: string;
      };
    };
  }
}

function getApiBase(): string {
  if (typeof window !== "undefined" && window.kpopblogConfig?.apiUrl) {
    return window.kpopblogConfig.apiUrl.replace(/\/$/, "");
  }
  const envUrl = (import.meta as any).env?.VITE_WORDPRESS_API_URL as string | undefined;
  return (envUrl ?? "").replace(/\/$/, "");
}

async function wpFetch<T>(path: string, params?: Record<string, string | number | undefined>): Promise<T> {
  const base = getApiBase();
  if (!base) throw new Error("WordPress API URL not configured");
  const url = new URL(`${base}${path}`);
  Object.entries(params ?? {}).forEach(([k, v]) => {
    if (v !== undefined && v !== "") url.searchParams.set(k, String(v));
  });
  const headers: HeadersInit = { Accept: "application/json" };
  if (typeof window !== "undefined" && window.kpopblogConfig?.nonce) {
    (headers as Record<string, string>)["X-WP-Nonce"] = window.kpopblogConfig.nonce;
  }
  const res = await fetch(url.toString(), { headers });
  if (!res.ok) throw new Error(`WP ${res.status}: ${path}`);
  return (await res.json()) as T;
}

async function wpPost<T>(path: string, body: unknown): Promise<T> {
  const base = getApiBase();
  if (!base) throw new Error("WordPress API URL not configured");
  const headers: Record<string, string> = { "Content-Type": "application/json", Accept: "application/json" };
  if (typeof window !== "undefined" && window.kpopblogConfig?.nonce) {
    headers["X-WP-Nonce"] = window.kpopblogConfig.nonce;
  }
  const res = await fetch(`${base}${path}`, { method: "POST", headers, body: JSON.stringify(body) });
  if (!res.ok) {
    let msg = `WP ${res.status}`;
    try { const j = await res.json(); msg = (j?.message as string) ?? msg; } catch { /* noop */ }
    throw new Error(msg);
  }
  return (await res.json()) as T;
}

export const wordpressCmsProvider: CmsProvider = {
  name: "wordpress",

  async listArticles(opts = {}) {
    try {
      const all = await wpFetch<Article[]>("/articles", {
        per_page: opts.limit ?? 20,
        search: opts.search,
        artist: opts.artistId,
      });
      let out = all;
      if (opts.category) out = out.filter((a) => a.category?.toLowerCase() === opts.category!.toLowerCase());
      if (opts.tag) out = out.filter((a) => a.tags?.includes(opts.tag!));
      if (opts.author) out = out.filter((a) => a.author?.toLowerCase().replace(/\s+/g, "-") === opts.author);
      if (opts.artistId) out = out.filter((a) => a.relatedArtistIds?.includes(opts.artistId!));
      return out;
    } catch (err) {
      console.error("[wordpressCmsProvider] listArticles failed", err);
      return [];
    }
  },

  async getArticleBySlug(slug) {
    try {
      return await wpFetch<Article>(`/articles/${encodeURIComponent(slug)}`);
    } catch {
      return null;
    }
  },

  async getRelated(article, limit = 4) {
    const all = await this.listArticles({ limit: 50 });
    return all
      .filter((a) => a.id !== article.id && a.relatedArtistIds.some((id) => article.relatedArtistIds.includes(id)))
      .slice(0, limit);
  },

  async postComment(slug, body, parentId) {
    try {
      const r = await wpPost<{ id: string; item?: Comment; pending: boolean }>(`/articles/${encodeURIComponent(slug)}/comments`, { body, parentId });
      return { ok: true, id: r.id, item: r.item, pending: r.pending };
    } catch (e) {
      return { ok: false, error: (e as Error).message };
    }
  },
  async listArticleComments(slug) {
    return wpFetch<Comment[]>(`/articles/${encodeURIComponent(slug)}/comments`, { per_page: 100 });
  },
  async listVideoComments(slug) {
    return wpFetch<Comment[]>(`/videos/${encodeURIComponent(slug)}/comments`, { per_page: 100 });
  },
  async postVideoComment(slug, body) {
    try {
      const response = await wpPost<{ item: Comment; pending: boolean }>(`/videos/${encodeURIComponent(slug)}/comments`, { body });
      return { ok: true, item: response.item, pending: response.pending };
    } catch (error) {
      return { ok: false, error: (error as Error).message };
    }
  },
  async votePoll(slug, optionId) {
    try {
      await wpPost(`/polls/${encodeURIComponent(slug)}/vote`, { optionId });
      return { ok: true };
    } catch (e) {
      return { ok: false, error: (e as Error).message };
    }
  },
  async toggleFollowArtist(slug) {
    try {
      const r = await wpPost<{ following: boolean; followerCount: number }>(`/artists/${encodeURIComponent(slug)}/follow`, {});
      return { ok: true, ...r };
    } catch (e) {
      return { ok: false, error: (e as Error).message };
    }
  },
  async recordEngagement(slug, kind) {
    try {
      await wpPost(`/articles/${encodeURIComponent(slug)}/engage`, { kind });
    } catch { /* fire-and-forget */ }
  },
};
