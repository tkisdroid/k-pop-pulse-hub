import { demoData } from "@/data/demo";
import type { CommunityPost, ForumPost, ForumThread, PublicProfile, Report } from "@/types";
import type { CommunityProvider } from "./types";

const reports: Report[] = [];

export const demoCommunityProvider: CommunityProvider = {
  name: "demo",
  async listCommunity() {
    return { items: demoData.community, total: demoData.community.length, totalPages: 1 };
  },
  async createCommunity(input) {
    const item: CommunityPost = {
      id: `demo-${Date.now()}`,
      authorId: demoData.users[0].id,
      body: input.body,
      language: input.language ?? "en",
      reactions: 0,
      createdAt: new Date().toISOString(),
      status: "publish",
    };
    return { item, status: "publish" };
  },
  async createSubmission() {
    return { id: `demo-${Date.now()}`, status: "pending" };
  },
  async listCategories() {
    return demoData.categories;
  },
  async listThreads(input = {}) {
    const category = input.category
      ? demoData.categories.find((item) => item.slug === input.category)
      : undefined;
    const items = (
      category
        ? demoData.threads.filter((thread) => thread.categoryId === category.id)
        : demoData.threads
    ).filter((thread) => !input.artist || (thread.relatedArtistIds ?? []).includes(input.artist));
    return { items, total: items.length, totalPages: 1 };
  },
  async getThread(slug) {
    return demoData.threads.find((thread) => thread.slug === slug) ?? null;
  },
  async createThread(input) {
    const category = demoData.categories.find((item) => item.slug === input.categorySlug);
    const item: ForumThread = {
      id: `demo-${Date.now()}`,
      slug: `demo-${Date.now()}`,
      categoryId: category?.id ?? input.categorySlug,
      title: input.title,
      body: input.body,
      authorId: demoData.users[0].id,
      views: 0,
      replies: 0,
      reactions: 0,
      lastActivityAt: new Date().toISOString(),
      createdAt: new Date().toISOString(),
      status: "publish",
    };
    return { item, status: "publish" };
  },
  async listReplies(slug) {
    const thread = demoData.threads.find((item) => item.slug === slug);
    const items = thread ? demoData.posts.filter((post) => post.threadId === thread.id) : [];
    return { items, total: items.length, totalPages: 1 };
  },
  async createReply(_slug, body) {
    const item: ForumPost = {
      id: `demo-${Date.now()}`,
      threadId: "demo",
      authorId: demoData.users[0].id,
      body,
      reactions: 0,
      createdAt: new Date().toISOString(),
      status: "published",
    };
    return { item, pending: false };
  },
  async getProfile(username) {
    return (
      (demoData.users.find((user) => user.username === username) as PublicProfile | undefined) ??
      null
    );
  },
  async updateProfile(input) {
    return { ...demoData.users[0], ...input };
  },
  async report(input) {
    const report: Report = {
      id: `demo-${Date.now()}`,
      reporterId: demoData.users[0].id,
      status: "pending",
      createdAt: new Date().toISOString(),
      ...input,
    };
    reports.push(report);
    return report;
  },
  async listReports() {
    return { items: reports, total: reports.length, totalPages: reports.length ? 1 : 0 };
  },
  async resolveReport(id, action, note) {
    const report = reports.find((item) => item.id === id);
    if (!report) throw new Error("Report not found.");
    report.status = action;
    report.resolutionNote = note;
    return report;
  },
};
