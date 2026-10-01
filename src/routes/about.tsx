import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/about")({
  head: () => buildHead({ title: "About KpopBlog", canonical: "/about" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">About KpopBlog</h1>
      <div className="space-y-3 text-muted-foreground">
        <p>
          KpopBlog is a global K-pop newsroom and fan community covering news, comebacks, artists
          and culture.
        </p>
        <p>
          Editorial content, member accounts, and community moderation are operated through the
          site's WordPress publishing system.
        </p>
        <h2 className="font-display text-xl font-semibold text-foreground">How we publish</h2>
        <p>
          Our publishing process combines source collection with AI-assisted summaries and rewrites.
          News coverage links to its sources so readers can check the underlying announcements and
          reporting. Artist profiles and release schedules use the information available at the time
          of publication; article dates show when coverage was published or updated.
        </p>
        <p>
          Editorial coverage and member discussions are separate. Community posts reflect their
          authors' views and follow our community guidelines. To report a factual error, include the
          affected URL and a reliable source through the correction form.
        </p>
        <div className="flex flex-wrap gap-4">
          <a href="/corrections" className="text-primary hover:underline">
            Corrections
          </a>
          <a href="/community-guidelines" className="text-primary hover:underline">
            Community guidelines
          </a>
          <a href="/contact" className="text-primary hover:underline">
            Contact the team
          </a>
        </div>
      </div>
    </div>
  ),
});
