import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/community-guidelines")({
  head: () => buildHead({ title: "Community Guidelines", canonical: "/community-guidelines" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Community Guidelines</h1>
      <div className="space-y-3 text-muted-foreground"><ul class="list-disc pl-5 space-y-1"><li>Be respectful — no harassment, hate speech, or fanwar baiting.</li><li>No sexual comments about minors.</li><li>No doxxing, private information, stalking or sasaeng behavior.</li><li>Rumors must be clearly labeled and include source links.</li><li>No impersonation of idols, agencies, journalists or moderators.</li><li>No spam, self-promo flooding or scam links.</li><li>Buying/selling is disabled by default.</li><li>Use spoiler tags for show results and sensitive content.</li><li>Repeat violations may lead to temporary or permanent bans.</li></ul></div>
    </div>
  ),
});
