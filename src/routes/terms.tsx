import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/terms")({
  head: () => buildHead({ title: "Terms of Service", canonical: "/terms" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Terms of Service</h1>
      <div className="space-y-6 text-muted-foreground">
        <p>Last updated: July 21, 2026</p>
        <section className="space-y-2"><h2 className="text-xl font-semibold text-foreground">Using KpopBlog</h2><p>You may browse public content without an account. Account features require accurate registration information and reasonable steps to protect your credentials. You are responsible for activity performed through your account.</p></section>
        <section className="space-y-2"><h2 className="text-xl font-semibold text-foreground">Community rules</h2><p>Do not post harassment, hate speech, threats, private personal information, impersonation, spam, scams, malicious links, or content that infringes another person&apos;s rights. Clearly label rumors and provide sources when making factual claims.</p></section>
        <section className="space-y-2"><h2 className="text-xl font-semibold text-foreground">Submissions and moderation</h2><p>You retain ownership of content you submit. You grant KpopBlog permission to store, display, format, moderate, and, when submitted for editorial review, edit and publish that content to operate the service. Content may be held for review, removed, or restricted when it violates these terms or creates a safety or security risk.</p></section>
        <section className="space-y-2"><h2 className="text-xl font-semibold text-foreground">Service availability</h2><p>Features may change or be temporarily unavailable for maintenance, security, or operational reasons. Do not rely on KpopBlog as the sole source for time-sensitive ticketing, travel, financial, legal, or safety decisions.</p></section>
        <section className="space-y-2"><h2 className="text-xl font-semibold text-foreground">Questions</h2><p>Use the submission form to ask about these terms or appeal a moderation decision.</p><a href="/submit" className="text-primary hover:underline">Open the submission form</a></section>
      </div>
    </div>
  ),
});
