import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/corrections")({
  head: () => buildHead({ title: "Corrections", canonical: "/corrections" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Corrections</h1>
      <div className="space-y-4 text-muted-foreground"><p>Spotted an error? Use the private submission form, choose “Artist correction” for profile facts or “News tip” for an article, and include the affected URL, the incorrect statement, the proposed correction, and a reliable source.</p><p>Requests enter the WordPress editorial review queue and are not published automatically.</p><a href="/submit" className="text-primary hover:underline">Submit a correction</a></div>
    </div>
  ),
});
