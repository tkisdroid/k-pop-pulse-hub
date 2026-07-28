import { Link, useRouterState } from "@tanstack/react-router";
import { Search, Menu, Sun, Moon, X } from "lucide-react";
import { NotificationCenter } from "./NotificationCenter";
import { LevelBadge } from "@/components/gamification/LevelBadge";
import { useEffect, useState } from "react";
import { useTheme } from "@/hooks/useTheme";
import { useI18n } from "@/hooks/useI18n";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { LanguageSwitcher } from "./LanguageSwitcher";
import { Button } from "@/components/ui/button";
import { UserMenu } from "./UserMenu";
import { getBranding } from "@/services/cms/branding";

const NAV = [
  { to: "/", key: "nav.home" },
  { to: "/latest", key: "nav.latest" },
  { to: "/trending", key: "nav.trending" },
  { to: "/artists", key: "nav.artists" },
  { to: "/comebacks", key: "nav.comebacks" },
  { to: "/forum", key: "nav.forum" },
  { to: "/polls", key: "nav.polls" },
  { to: "/videos", key: "nav.videos" },
  { to: "/charts", key: "nav.charts" },
  { to: "/community", key: "nav.community" },
] as const;

export function Header() {
  const { theme, toggle } = useTheme();
  const { t } = useI18n();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const path = useRouterState({ select: (s) => s.location.pathname });
  const branding = getBranding();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Close mobile menu on route change
  useEffect(() => { setOpen(false); }, [path]);

  return (
    <header
      className={`sticky top-0 z-40 transition-all duration-300 ${
        scrolled ? "glass-strong border-b border-border shadow-[0_8px_24px_-16px_color-mix(in_oklab,var(--foreground)_25%,transparent)]" : "glass border-b border-transparent"
      }`}
    >
      <div className={`mx-auto max-w-7xl px-4 flex items-center gap-1.5 sm:gap-4 transition-all duration-300 ${scrolled ? "py-2" : "py-3"}`}>
        <button className="lg:hidden p-1.5 sm:p-2 press" onClick={() => setOpen((o) => !o)} aria-label="Menu">
          {open ? <X className="size-5" /> : <Menu className="size-5" />}
        </button>
        <Link to="/" className="flex items-center gap-2 shrink-0 group">
          {branding.logoUrl ? (
            <img
              src={branding.logoUrl}
              alt=""
              className="size-8 rounded-lg object-cover transition-transform duration-300 group-hover:rotate-6 group-hover:scale-110"
            />
          ) : (
            <div className="size-8 rounded-lg gradient-neon transition-transform duration-300 group-hover:rotate-6 group-hover:scale-110" />
          )}
          {/* Wordmark drops below 360px so the action buttons always fit on one line */}
          <span className="hidden min-[360px]:inline font-display text-base sm:text-xl font-bold">
            {branding.siteName}
            {branding.accentWord ? <span className="text-gradient">{branding.accentWord}</span> : null}
          </span>
        </Link>
        <nav className="hidden lg:flex items-center gap-1 ml-4 overflow-x-auto scrollbar-hide">
          {NAV.map((n) => {
            const active = path === n.to;
            return (
              <Link
                key={n.to}
                to={n.to as any}
                data-active={active}
                className={`link-underline px-3 py-1.5 rounded-md text-sm font-medium transition-colors hover:text-foreground ${active ? "text-primary" : "text-muted-foreground"}`}
              >
                {t(n.key)}
              </Link>
            );
          })}
        </nav>
        <div className="ml-auto flex items-center gap-1">
          <Link to="/search" className="p-1.5 sm:p-2 rounded-md hover:bg-accent press transition-colors" aria-label="Search"><Search className="size-5" /></Link>
          <LanguageSwitcher />
          <button onClick={toggle} className="p-1.5 sm:p-2 rounded-md hover:bg-accent press transition-colors" aria-label="Theme">
            {theme === "dark" ? <Sun className="size-5" /> : <Moon className="size-5" />}
          </button>
          {user ? (
            <>
              <LevelBadge compact />
              <NotificationCenter />
              <UserMenu />
            </>
          ) : (
            <>
              <Button variant="ghost" size="sm" className="press px-2 sm:px-3" onClick={() => show("Log in to KpopBlog")}>{t("nav.login")}</Button>
              <Button size="sm" className="hidden sm:inline-flex press" asChild>
                <Link to="/signup">{t("nav.signup")}</Link>
              </Button>
            </>
          )}
        </div>
      </div>
      {open && (
        <div className="lg:hidden border-t border-border bg-background animate-in fade-in slide-in-from-top-2 duration-200">
          <nav className="px-4 py-2 grid gap-1">
            {NAV.map((n) => (
              <Link key={n.to} to={n.to as any} className="px-3 py-2 rounded-md hover:bg-accent transition-colors">
                {t(n.key)}
              </Link>
            ))}
            <Link to="/submit" className="px-3 py-2 rounded-md hover:bg-accent transition-colors">{t("nav.submit")}</Link>
          </nav>
        </div>
      )}
    </header>
  );
}
