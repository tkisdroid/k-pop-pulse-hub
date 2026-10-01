import { demoData } from "@/data/demo";
export const comebackProvider = {
  name: "demo",
  list: async () =>
    demoData.comebacks.sort((a, b) => +new Date(a.releaseAt) - +new Date(b.releaseAt)),
  upcoming: async (limit = 6) =>
    demoData.comebacks.filter((c) => +new Date(c.releaseAt) > Date.now()).slice(0, limit),
};
