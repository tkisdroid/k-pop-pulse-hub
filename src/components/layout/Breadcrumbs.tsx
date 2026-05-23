import { Link, useRouterState } from "@tanstack/react-router";
import { ChevronRight, Home } from "lucide-react";
import { demoData } from "@/data/demo";

const LABELS: Record<string, string> = {
  news: "News",
  artist: "Artists",
  artists: "Artists",
  member: "Members",
  watch: "Videos",
  videos: "Videos",
  charts: "Charts",
  comebacks: "Comebacks",
  polls: "Polls",
  community: "Community",
  forum: "Forum",
  thread: "Forum",
  trending: "Trending",
  latest: "Latest",
  category: "Categories",
  tag: "Tags",
  author: "Authors",
  search: "Search",
  about: "About",
  contact: "Contact",
  advertise: "Advertise",
  newsletter: "Newsletter",
  profile: "Profile",
  submit: "Submit",
  privacy: "Privacy",
  terms: "Terms",
  copyright: "Copyright",
  corrections: "Corrections",
  "cookie-settings": "Cookie settings",
  "community-guidelines": "Community guidelines",
};

const HIDE_PREFIXES = ["/login", "/signup", "/onboarding", "/admin", "/moderation"];

function humanize(slug: string) {
  return decodeURIComponent(slug).replace(/-/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
}

function resolveLabel(prevSeg: string | undefined, seg: string) {
  if (LABELS[seg]) return LABELS[seg];
  // Dynamic segment — try to resolve to a real name from demo data
  if (prevSeg === "artist") {
    const a = demoData.artists.find((x) => x.slug === seg);
    if (a) return a.name;
  }
  if (prevSeg === "member") {
    const m = demoData.members.find((x) => x.slug === seg);
    if (m) return m.stageName;
  }
  if (prevSeg === "news") {
    const a = demoData.articles.find((x) => x.slug === seg);
    if (a) return a.title.length > 48 ? a.title.slice(0, 45) + "…" : a.title;
  }
  if (prevSeg === "watch") {
    const v = demoData.videos.find((x) => x.id === seg);
    if (v) return v.title.length > 48 ? v.title.slice(0, 45) + "…" : v.title;
  }
  if (prevSeg === "polls") {
    const p = demoData.polls.find((x) => x.slug === seg);
    if (p) return p.question.length > 48 ? p.question.slice(0, 45) + "…" : p.question;
  }
  if (prevSeg === "thread") {
    const t = demoData.threads.find((x) => x.slug === seg);
    if (t) return t.title.length > 48 ? t.title.slice(0, 45) + "…" : t.title;
  }
  return humanize(seg);
}

export function Breadcrumbs() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  if (pathname === "/" || HIDE_PREFIXES.some((p) => pathname.startsWith(p))) return null;

  const segs = pathname.split("/").filter(Boolean);
  const crumbs = segs.map((seg, i) => {
    const href = "/" + segs.slice(0, i + 1).join("/");
    const label = resolveLabel(segs[i - 1], seg);
    return { href, label };
  });

  return (
    <nav aria-label="Breadcrumb" className="mx-auto max-w-7xl px-4 w-full pt-3 text-xs text-muted-foreground">
      <ol className="flex items-center gap-1.5 flex-wrap">
        <li className="flex items-center gap-1.5">
          <Link to="/" className="inline-flex items-center gap-1 hover:text-foreground transition">
            <Home className="h-3.5 w-3.5" /> Home
          </Link>
        </li>
        {crumbs.map((c, i) => (
          <li key={c.href} className="flex items-center gap-1.5">
            <ChevronRight className="h-3.5 w-3.5 opacity-50" />
            {i === crumbs.length - 1 ? (
              <span className="text-foreground/90 truncate max-w-[60vw]">{c.label}</span>
            ) : (
              <Link to={c.href} className="hover:text-foreground transition truncate max-w-[40vw]">{c.label}</Link>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
