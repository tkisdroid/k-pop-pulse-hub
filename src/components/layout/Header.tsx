import { Link, useRouterState } from "@tanstack/react-router";
import { Search, Menu, Sun, Moon, Bell, X } from "lucide-react";
import { useState } from "react";
import { useTheme } from "@/hooks/useTheme";
import { useI18n } from "@/hooks/useI18n";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { LanguageSwitcher } from "./LanguageSwitcher";
import { Button } from "@/components/ui/button";
import { UserMenu } from "./UserMenu";

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
  const path = useRouterState({ select: (s) => s.location.pathname });

  return (
    <header className="sticky top-0 z-40 glass border-b border-border">
      <div className="mx-auto max-w-7xl px-4 py-3 flex items-center gap-4">
        <button className="lg:hidden p-2" onClick={() => setOpen((o) => !o)} aria-label="Menu">
          {open ? <X className="size-5" /> : <Menu className="size-5" />}
        </button>
        <Link to="/" className="flex items-center gap-2 shrink-0">
          <div className="size-8 rounded-lg gradient-neon" />
          <span className="font-display text-xl font-bold">Kpop<span className="text-gradient">Blog</span></span>
        </Link>
        <nav className="hidden lg:flex items-center gap-1 ml-4 overflow-x-auto scrollbar-hide">
          {NAV.map((n) => (
            <Link
              key={n.to}
              to={n.to as any}
              className={`px-3 py-1.5 rounded-md text-sm font-medium transition-colors hover:bg-accent ${path === n.to ? "text-primary" : "text-muted-foreground"}`}
            >
              {t(n.key)}
            </Link>
          ))}
        </nav>
        <div className="ml-auto flex items-center gap-1">
          <Link to="/search" className="p-2 rounded-md hover:bg-accent" aria-label="Search"><Search className="size-5" /></Link>
          <LanguageSwitcher />
          <button onClick={toggle} className="p-2 rounded-md hover:bg-accent" aria-label="Theme">
            {theme === "dark" ? <Sun className="size-5" /> : <Moon className="size-5" />}
          </button>
          {user ? (
            <>
              <button className="p-2 rounded-md hover:bg-accent relative" aria-label="Notifications">
                <Bell className="size-5" />
                <span className="absolute top-1.5 right-1.5 size-2 rounded-full bg-primary" />
              </button>
              <UserMenu />
            </>
          ) : (
            <>
              <Button variant="ghost" size="sm" onClick={() => show("Log in to KpopBlog")}>{t("nav.login")}</Button>
              <Button size="sm" className="hidden sm:inline-flex" asChild>
                <Link to="/signup">{t("nav.signup")}</Link>
              </Button>
            </>
          )}
        </div>
      </div>
      {open && (
        <div className="lg:hidden border-t border-border bg-background">
          <nav className="px-4 py-2 grid gap-1">
            {NAV.map((n) => (
              <Link key={n.to} to={n.to as any} onClick={() => setOpen(false)} className="px-3 py-2 rounded-md hover:bg-accent">
                {t(n.key)}
              </Link>
            ))}
            <Link to="/submit" onClick={() => setOpen(false)} className="px-3 py-2 rounded-md hover:bg-accent">{t("nav.submit")}</Link>
          </nav>
        </div>
      )}
    </header>
  );
}
