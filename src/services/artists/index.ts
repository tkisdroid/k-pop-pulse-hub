import { demoData } from "@/data/demo";
export const artistProvider = {
  name: "demo",
  list: async (opts?: { type?: string; search?: string; generation?: number }) => {
    let out = [...demoData.artists];
    if (opts?.type) out = out.filter((a) => a.type === opts.type);
    if (opts?.generation) out = out.filter((a) => a.generation === opts.generation);
    if (opts?.search) {
      const q = opts.search.toLowerCase();
      out = out.filter((a) => a.name.toLowerCase().includes(q) || a.agency.toLowerCase().includes(q));
    }
    return out;
  },
  get: async (slug: string) => demoData.artists.find((a) => a.slug === slug) ?? null,
  getMembers: async (artistId: string) => demoData.members.filter((m) => m.groupId === artistId),
  getMember: async (slug: string) => demoData.members.find((m) => m.slug === slug) ?? null,
};
