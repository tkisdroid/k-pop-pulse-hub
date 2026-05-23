import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/contact")({
  head: () => buildHead({ title: "Contact us", canonical: "/contact" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Contact us</h1>
      <div className="space-y-3 text-muted-foreground"><p>Editorial: editorial@kpopblog.com</p><p>Partnerships: partners@kpopblog.com</p><p>Press: press@kpopblog.com</p></div>
    </div>
  ),
});
