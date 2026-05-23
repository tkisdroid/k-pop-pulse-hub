import { demoData } from "@/data/demo";
export const notificationProvider = {
  name: "demo",
  list: async (userId: string) => demoData.notifications.filter((n) => n.userId === userId),
};
