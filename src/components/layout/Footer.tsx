import { Link } from "@tanstack/react-router";
import { useI18n } from "@/hooks/useI18n";
import { NewsletterCTA } from "@/components/newsletter/NewsletterCTA";

const COLS = [
  { title: "About", links: [["About", "/about"], ["Contact", "/contact"], ["Advertise", "/advertise"], ["Submit News Tip", "/submit"]] },
  { title: "Community", links: [["Forum", "/forum"], ["Polls", "/polls"], ["Community Wall", "/community"], ["Community Guidelines", "/community-guidelines"]] },
  { title: "Legal", links: [["Privacy Policy", "/privacy"], ["Terms", "/terms"], ["DMCA / Copyright", "/copyright"], ["Corrections", "/corrections"], ["Cookie Settings", "/cookie-settings"]] },
] as const;

export function Footer() {
  const { t } = useI18n();
  return (
    <footer className="mt-16 border-t border-border bg-card/30">
      <div className="mx-auto max-w-7xl px-4 py-12 grid gap-8 md:grid-cols-4">
        <div>
          <Link to="/" className="flex items-center gap-2">
            <div className="size-8 rounded-lg gradient-neon" />
            <span className="font-display text-xl font-bold">Kpop<span className="text-gradient">Blog</span></span>
          </Link>
          <p className="mt-3 text-sm text-muted-foreground">{t("site.tagline")}</p>
        </div>
        {COLS.map((c) => (
          <div key={c.title}>
            <h4 className="font-semibold mb-3">{c.title}</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              {c.links.map(([l, h]) => (
                <li key={h}><Link to={h as any} className="hover:text-foreground">{l}</Link></li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <div className="border-t border-border">
        <div className="mx-auto max-w-7xl px-4 py-4 text-xs text-muted-foreground flex flex-wrap justify-between gap-2">
          <span>© {new Date().getFullYear()} KpopBlog. All trademarks belong to their respective owners.</span>
          <span>kpopblog.com — demo build</span>
        </div>
      </div>
    </footer>
  );
}
