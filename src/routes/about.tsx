import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/about")({
  head: () => buildHead({ title: "About KpopBlog", canonical: "/about" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">About KpopBlog</h1>
      <div className="space-y-3 text-muted-foreground"><p>KpopBlog is a global K-pop newsroom and fan community covering news, comebacks, artists and culture.</p><p>This is a demo build. Editorial team, contact info and partnerships will be detailed before launch.</p></div>
    </div>
  ),
});
