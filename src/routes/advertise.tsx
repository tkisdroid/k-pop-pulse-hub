import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/advertise")({
  head: () => buildHead({ title: "Advertise", canonical: "/advertise" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Advertise</h1>
      <div className="space-y-3 text-muted-foreground"><p>Reach a global K-pop audience across web and mobile.</p><p>Sponsored content, display ad slots and newsletter placements are available. Contact partners@kpopblog.com.</p></div>
    </div>
  ),
});
