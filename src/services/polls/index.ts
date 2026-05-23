import { demoData } from "@/data/demo";
export const pollProvider = {
  name: "demo",
  list: async () => demoData.polls,
  get: async (slug: string) => demoData.polls.find((p) => p.slug === slug) ?? null,
  vote: async (_pollId: string, _optionId: string) => ({ ok: true, demo: true }),
};
