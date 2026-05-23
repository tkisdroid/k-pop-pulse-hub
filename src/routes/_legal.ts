import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

const PAGES: Record<string, { title: string; body: string[] }> = {
  about: { title: "About KpopBlog", body: ["KpopBlog is a global K-pop newsroom and fan community covering news, comebacks, artists and culture.", "This is a demo build. Editorial team, contact info and partnerships will be detailed before launch."] },
  contact: { title: "Contact us", body: ["Editorial: editorial@kpopblog.com", "Partnerships: partners@kpopblog.com", "Press: press@kpopblog.com"] },
  advertise: { title: "Advertise", body: ["Reach a global K-pop audience across web and mobile.", "Sponsored content, display ad slots and newsletter placements are available. Contact partners@kpopblog.com."] },
  privacy: { title: "Privacy Policy", body: ["We respect your privacy. This placeholder will be replaced with the full policy before launch.", "We collect minimal data: account info, preferences and activity needed to deliver community features."] },
  terms: { title: "Terms of Service", body: ["By using KpopBlog you agree to our community guidelines and posting rules.", "We reserve the right to moderate content that violates these rules."] },
  "community-guidelines": { title: "Community Guidelines", body: [
    "Be respectful — no harassment, hate speech, bigotry, or fanwar baiting.",
    "No sexual comments about minors.",
    "No doxxing, private information, stalking or sasaeng behavior.",
    "Rumors must be clearly labeled and include source links.",
    "No impersonation of idols, agencies, journalists or moderators.",
    "No spam, self-promo flooding or scam links.",
    "Buying/selling is disabled by default.",
    "Use spoiler tags for show results and sensitive content.",
    "Repeat violations may lead to temporary or permanent bans.",
  ] },
  copyright: { title: "DMCA / Copyright", body: ["KpopBlog respects intellectual property. To file a takedown notice, email copyright@kpopblog.com with the required information."] },
  corrections: { title: "Corrections", body: ["Spotted an error? Submit a correction via /submit. Editors review every request."] },
  "cookie-settings": { title: "Cookie Settings", body: ["Manage essential, analytics and personalization cookies. Detailed controls will be added before launch."] },
};

function makePage(slug: string) {
  return createFileRoute(("/" + slug) as any)({
    head: () => buildHead({ title: PAGES[slug].title, canonical: "/" + slug }),
    component: () => {
      const p = PAGES[slug];
      return (
        <div className="mx-auto max-w-3xl px-4 py-10">
          <h1 className="font-display text-4xl font-bold mb-6">{p.title}</h1>
          <div className="space-y-3 text-muted-foreground">{p.body.map((para, i) => <p key={i}>{para}</p>)}</div>
        </div>
      );
    },
  });
}

export { makePage };
