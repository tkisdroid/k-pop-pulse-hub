import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/cookie-settings")({
  head: () => buildHead({ title: "Cookie Settings", canonical: "/cookie-settings" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Cookie Settings</h1>
      <div className="space-y-3 text-muted-foreground"><p>Manage essential, analytics and personalization cookies.</p></div>
    </div>
  ),
});
