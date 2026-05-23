import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/corrections")({
  head: () => buildHead({ title: "Corrections", canonical: "/corrections" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Corrections</h1>
      <div className="space-y-3 text-muted-foreground"><p>Spotted an error? Submit a correction via /submit. Editors review every request.</p></div>
    </div>
  ),
});
