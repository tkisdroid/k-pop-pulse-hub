import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/copyright")({
  head: () => buildHead({ title: "DMCA / Copyright", canonical: "/copyright" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">DMCA / Copyright</h1>
      <div className="space-y-3 text-muted-foreground"><p>KpopBlog respects intellectual property. To file a takedown notice, email copyright@kpopblog.com.</p></div>
    </div>
  ),
});
