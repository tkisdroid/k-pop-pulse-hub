import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/privacy")({
  head: () => buildHead({ title: "Privacy Policy", canonical: "/privacy" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">Privacy Policy</h1>
      <div className="space-y-6 text-muted-foreground">
        <p>Last updated: October 1, 2026</p>
        <section className="space-y-2">
          <h2 className="text-xl font-semibold text-foreground">Information we process</h2>
          <p>KpopBlog processes account details you provide, profile preferences, artist follows, notification choices, comments, community posts, reports, editorial submissions, and newsletter subscription status. WordPress also records the technical information needed to authenticate requests, prevent abuse, and operate the service.</p>
        </section>
        <section className="space-y-2">
          <h2 className="text-xl font-semibold text-foreground">How information is used</h2>
          <p>We use this information to provide accounts and community features, deliver requested notifications and newsletters, review submissions and reports, protect the service, and maintain an audit trail for administrative actions.</p>
        </section>
        <section className="space-y-2">
          <h2 className="text-xl font-semibold text-foreground">Advertising and consent</h2>
          <p>Advertising and optional measurement technologies are not loaded until the applicable consent choice is available. You can revisit your choice through the consent controls shown on the site.</p>
          <p>When you allow analytics, we count public page views using a random browser identifier that expires after 90 days. Our traffic statistics store a salted hash of that identifier, the public page path without query parameters, and the visit date and time for up to 90 days. We do not store IP addresses or user agents in these statistics. Rejecting analytics removes the browser identifier and stops further collection.</p>
        </section>
        <section className="space-y-2">
          <h2 className="text-xl font-semibold text-foreground">Retention and your choices</h2>
          <p>Content and account information are retained while needed to operate the service, meet moderation and security obligations, or honor a request you made. Newsletter unsubscribe links remain available in each message. WordPress account holders can request an export or erasure through the site administrator, subject to information that must be retained for security or legal reasons.</p>
        </section>
        <section className="space-y-2">
          <h2 className="text-xl font-semibold text-foreground">Questions and requests</h2>
          <p>Use the authenticated submission form for privacy questions or account requests so the editorial team can review them without publishing the contents.</p>
          <a href="/submit" className="text-primary hover:underline">Open the submission form</a>
        </section>
      </div>
    </div>
  ),
});
