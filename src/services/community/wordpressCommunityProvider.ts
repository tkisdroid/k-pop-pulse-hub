import type {
  CommunityPost,
  ForumCategory,
  ForumPost,
  ForumThread,
  PublicProfile,
  Report,
  User,
} from "@/types";
import type { CommunityProvider, Paginated } from "./types";

function apiBase(): string {
  if (typeof window !== "undefined" && window.kpopblogConfig?.apiUrl) {
    return window.kpopblogConfig.apiUrl.replace(/\/$/, "");
  }
  const configured = (import.meta as { env?: { VITE_WORDPRESS_API_URL?: string } }).env
    ?.VITE_WORDPRESS_API_URL;
  return (configured ?? "").replace(/\/$/, "");
}

async function request<T>(
  path: string,
  options?: RequestInit,
): Promise<{ data: T; response: Response }> {
  const base = apiBase();
  if (!base) throw new Error("WordPress API URL is not configured.");
  const headers = new Headers(options?.headers);
  headers.set("Accept", "application/json");
  if (options?.body) headers.set("Content-Type", "application/json");
  if (typeof window !== "undefined" && window.kpopblogConfig?.nonce) {
    headers.set("X-WP-Nonce", window.kpopblogConfig.nonce);
  }
  const response = await fetch(`${base}${path}`, {
    ...options,
    headers,
    credentials: "same-origin",
  });
  const data = (await response.json().catch(() => ({}))) as T & { message?: string };
  if (!response.ok) throw new Error(data.message || `Request failed (${response.status}).`);
  return { data, response };
}

function pageResult<T>(data: T[], response: Response): Paginated<T> {
  return {
    items: data,
    total: Number(response.headers.get("X-WP-Total") ?? data.length),
    totalPages: Number(response.headers.get("X-WP-TotalPages") ?? (data.length ? 1 : 0)),
  };
}

export const wordpressCommunityProvider: CommunityProvider = {
  name: "wordpress",
  async listCommunity(input = {}) {
    const query = new URLSearchParams({
      page: String(input.page ?? 1),
      per_page: String(input.perPage ?? 20),
    });
    if (input.mine) query.set("mine", "1");
    const { data, response } = await request<CommunityPost[]>(`/community?${query}`);
    return pageResult(data, response);
  },
  async createCommunity(input) {
    return (
      await request<{ item: CommunityPost; status: string }>("/community", {
        method: "POST",
        body: JSON.stringify(input),
      })
    ).data;
  },
  async listCategories() {
    return (await request<ForumCategory[]>("/forum/categories")).data;
  },
  async listThreads(input = {}) {
    const query = new URLSearchParams({
      page: String(input.page ?? 1),
      per_page: String(input.perPage ?? 20),
    });
    if (input.category) query.set("category", input.category);
    const { data, response } = await request<ForumThread[]>(`/threads?${query}`);
    return pageResult(data, response);
  },
  async getThread(slug) {
    try {
      return (await request<ForumThread>(`/threads/${encodeURIComponent(slug)}`)).data;
    } catch (error) {
      if (
        (error as Error).message.includes("not found") ||
        (error as Error).message.includes("Not found")
      )
        return null;
      throw error;
    }
  },
  async createThread(input) {
    return (
      await request<{ item: ForumThread; status: string }>("/threads", {
        method: "POST",
        body: JSON.stringify(input),
      })
    ).data;
  },
  async listReplies(slug, page = 1) {
    const { data, response } = await request<ForumPost[]>(
      `/threads/${encodeURIComponent(slug)}/replies?page=${page}&per_page=50`,
    );
    return pageResult(data, response);
  },
  async createReply(slug, body, parentId) {
    return (
      await request<{ item: ForumPost; pending: boolean }>(
        `/threads/${encodeURIComponent(slug)}/replies`,
        {
          method: "POST",
          body: JSON.stringify({ body, parentId }),
        },
      )
    ).data;
  },
  async getProfile(username) {
    try {
      return (await request<PublicProfile>(`/profiles/${encodeURIComponent(username)}`)).data;
    } catch (error) {
      if (
        (error as Error).message.includes("not found") ||
        (error as Error).message.includes("Not found")
      )
        return null;
      throw error;
    }
  },
  async updateProfile(input) {
    return (
      await request<{ nonce?: string; user: User }>("/profile/me", {
        method: "POST",
        body: JSON.stringify(input),
      })
    ).data.user;
  },
  async report(input) {
    return (
      await request<{ report: Report }>("/reports", { method: "POST", body: JSON.stringify(input) })
    ).data.report;
  },
  async listReports(page = 1) {
    const { data, response } = await request<Report[]>(
      `/moderation/reports?page=${page}&per_page=50`,
    );
    return pageResult(data, response);
  },
  async resolveReport(id, action, note = "") {
    return (
      await request<{ report: Report }>(`/moderation/reports/${encodeURIComponent(id)}`, {
        method: "POST",
        body: JSON.stringify({ action, note }),
      })
    ).data.report;
  },
};
