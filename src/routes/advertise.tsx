import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/advertise")({
  head: () => buildHead({ title: "Advertise", canonical: "/advertise" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Advertise</h1>
      <div className="space-y-4 text-muted-foreground">
        <p>
          Reach a global K-pop audience across web and mobile. Display placements are served only
          through configured, consent-aware advertising slots.
        </p>
        <p>
          For campaign or partnership inquiries, submit the audience, region, dates, format, and
          budget through the private editorial review form. Sponsored editorial coverage is subject
          to review and clear labeling.
        </p>
        <a href="/submit" className="text-primary hover:underline">
          Send a partnership inquiry
        </a>
      </div>
    </div>
  ),
});
