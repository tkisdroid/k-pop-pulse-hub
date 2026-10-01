import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/contact")({
  head: () => buildHead({ title: "Contact us", canonical: "/contact" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Contact us</h1>
      <div className="space-y-4 text-muted-foreground">
        <p>
          Send news tips, corrections, event details, translation requests, press inquiries, or
          partnership proposals through our authenticated submission form. Each request is stored
          privately in the WordPress editorial review queue.
        </p>
        <a
          href="/submit"
          className="inline-flex min-h-10 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
        >
          Open the submission form
        </a>
      </div>
    </div>
  ),
});
