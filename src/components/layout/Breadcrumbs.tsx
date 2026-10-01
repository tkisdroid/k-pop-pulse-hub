import { Link, useRouterState } from "@tanstack/react-router";
import { ChevronRight, Home } from "lucide-react";
import { useRuntimeData } from "@/services/cms/runtimeData";

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
  return decodeURIComponent(slug)
    .replace(/-/g, " ")
    .replace(/\b\w/g, (m) => m.toUpperCase());
}

export function Breadcrumbs() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const { data } = useRuntimeData();
  if (pathname === "/" || HIDE_PREFIXES.some((p) => pathname.startsWith(p))) return null;

  const resolveLabel = (prevSeg: string | undefined, seg: string) => {
    if (LABELS[seg]) return LABELS[seg];
    const match =
      prevSeg === "artist"
        ? data.artists.find((item) => item.slug === seg)?.name
        : prevSeg === "member"
          ? data.members.find((item) => item.slug === seg)?.stageName
          : prevSeg === "news"
            ? data.articles.find((item) => item.slug === seg)?.title
            : prevSeg === "watch"
              ? data.videos.find((item) => item.id === seg || item.slug === seg)?.title
              : prevSeg === "polls"
                ? data.polls.find((item) => item.slug === seg)?.title
                : prevSeg === "thread"
                  ? data.threads.find((item) => item.slug === seg)?.title
                  : undefined;
    return match ? (match.length > 48 ? `${match.slice(0, 45)}…` : match) : humanize(seg);
  };

  const segs = pathname.split("/").filter(Boolean);
  const crumbs = segs.map((seg, i) => {
    const href = "/" + segs.slice(0, i + 1).join("/");
    const label = resolveLabel(segs[i - 1], seg);
    return { href, label };
  });

  return (
    <nav
      aria-label="Breadcrumb"
      className="mx-auto max-w-7xl px-4 w-full pt-3 text-xs text-muted-foreground"
    >
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
              <Link to={c.href} className="hover:text-foreground transition truncate max-w-[40vw]">
                {c.label}
              </Link>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
