import { Link } from "@tanstack/react-router";
import { Home, Newspaper, MessageSquare, Users, User } from "lucide-react";
import { useRouterState } from "@tanstack/react-router";

const ITEMS = [
  { to: "/", icon: Home, label: "Home" },
  { to: "/latest", icon: Newspaper, label: "Latest" },
  { to: "/forum", icon: MessageSquare, label: "Forum" },
  { to: "/artists", icon: Users, label: "Artists" },
  { to: "/profile/me", icon: User, label: "Profile" },
] as const;

export function MobileBottomNav() {
  const path = useRouterState({ select: (s) => s.location.pathname });
  return (
    <nav className="lg:hidden fixed bottom-0 left-0 right-0 z-40 glass border-t border-border">
      <div className="grid grid-cols-5">
        {ITEMS.map((i) => {
          const active = path === i.to || (i.to !== "/" && path.startsWith(i.to));
          const Icon = i.icon;
          return (
            <Link key={i.to} to={i.to as any} className={`flex flex-col items-center gap-1 py-2 text-xs ${active ? "text-primary" : "text-muted-foreground"}`}>
              <Icon className="size-5" />
              <span>{i.label}</span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
