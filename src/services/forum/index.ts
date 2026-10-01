import { demoData } from "@/data/demo";
export const forumProvider = {
  name: "demo",
  listCategories: async () => demoData.categories,
  getCategory: async (slug: string) => demoData.categories.find((c) => c.slug === slug) ?? null,
  listThreads: async (categoryId?: string) =>
    (categoryId
      ? demoData.threads.filter((t) => t.categoryId === categoryId)
      : demoData.threads
    ).sort(
      (a, b) =>
        (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0) ||
        +new Date(b.lastActivityAt) - +new Date(a.lastActivityAt),
    ),
  getThread: async (slug: string) => demoData.threads.find((t) => t.slug === slug) ?? null,
  listPosts: async (threadId: string) => demoData.posts.filter((p) => p.threadId === threadId),
  createThread: async (_input: { categoryId: string; title: string; body: string }) => ({
    ok: true,
    demo: true,
  }),
  createPost: async (_input: { threadId: string; body: string; parentId?: string }) => ({
    ok: true,
    demo: true,
  }),
};
