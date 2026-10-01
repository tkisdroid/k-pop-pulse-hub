import type { Report } from "@/types";

const reports: Report[] = [];

export const moderationProvider = {
  name: "demo",
  listReports: async () => reports,
  report: async (input: Omit<Report, "id" | "status" | "createdAt">) => {
    const r: Report = {
      ...input,
      id: `r_${Date.now()}`,
      status: "pending",
      createdAt: new Date().toISOString(),
    };
    reports.push(r);
    return r;
  },
  resolve: async (id: string) => {
    const r = reports.find((x) => x.id === id);
    if (r) r.status = "resolved";
    return r;
  },
};
