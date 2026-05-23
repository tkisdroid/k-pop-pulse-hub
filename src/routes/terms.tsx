import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/terms")({
  head: () => buildHead({ title: "Terms of Service", canonical: "/terms" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Terms of Service</h1>
      <div className="space-y-3 text-muted-foreground"><p>By using KpopBlog you agree to our community guidelines and posting rules.</p></div>
    </div>
  ),
});
