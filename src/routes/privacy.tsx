import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/privacy")({
  head: () => buildHead({ title: "Privacy Policy", canonical: "/privacy" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Privacy Policy</h1>
      <div className="space-y-3 text-muted-foreground"><p>We respect your privacy. This placeholder will be replaced with the full policy before launch.</p></div>
    </div>
  ),
});
