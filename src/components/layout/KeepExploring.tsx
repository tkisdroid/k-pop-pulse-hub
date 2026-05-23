import { Link, useRouterState } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { ArrowRight, Flame, Newspaper, Music2, Vote, Calendar, Users, MessageSquare, PlayCircle, BarChart3, Sparkles } from "lucide-react";
import type { ComponentType } from "react";

type Item = { href: string; label: string; sub?: string; icon: ComponentType<{ className?: string }> };
type Section = { title: string; items: Item[] };

const HIDE_PREFIXES = ["/login", "/signup", "/onboarding", "/admin", "/moderation", "/newsletter"];

const HUB_LINKS: Item[] = [
  { href: "/trending", label: "Trending now", sub: "What fans are reading", icon: Flame },
  { href: "/latest", label: "Latest news", sub: "Fresh from the desk", icon: Newspaper },
  { href: "/artists", label: "All artists", sub: "Profiles & members", icon: Users },
  { href: "/charts", label: "Charts", sub: "This week's rankings", icon: BarChart3 },
  { href: "/comebacks", label: "Comebacks", sub: "Release calendar", icon: Calendar },
  { href: "/polls", label: "Polls", sub: "Cast your vote", icon: Vote },
  { href: "/videos", label: "Videos", sub: "Watch on-site", icon: PlayCircle },
  { href: "/community", label: "Community", sub: "Fan walls", icon: MessageSquare },
  { href: "/forum", label: "Forum", sub: "Deep discussions", icon: MessageSquare },
];

function pickHubs(exclude: string[], n = 4): Item[] {
  return HUB_LINKS.filter((l) => !exclude.includes(l.href)).slice(0, n);
}

function buildSections(pathname: string): Section[] | null {
  const segs = pathname.split("/").filter(Boolean);

  // Artist detail
  if (segs[0] === "artist" && segs[1]) {
    const artist = demoData.artists.find((a) => a.slug === segs[1]);
    if (!artist) return null;
    const related = demoData.articles.filter((a) => a.relatedArtistIds.includes(artist.id)).slice(0, 4);
    const videos = demoData.videos.filter((v) => v.artistId === artist.id).slice(0, 4);
    const similar = demoData.artists.filter((a) => a.id !== artist.id && a.type === artist.type).slice(0, 4);
    return [
      {
        title: `More about ${artist.name}`,
        items: [
          ...related.map((a) => ({ href: `/news/${a.slug}`, label: a.title, icon: Newspaper })),
          ...videos.map((v) => ({ href: `/watch/${v.id}`, label: v.title, sub: "Watch on-site", icon: PlayCircle })),
        ].slice(0, 4),
      },
      {
        title: "Similar artists",
        items: similar.map((a) => ({ href: `/artist/${a.slug}`, label: a.name, sub: a.fandomName, icon: Music2 })),
      },
      { title: "Keep exploring", items: pickHubs(["/artists"]) },
    ];
  }

  // News article
  if (segs[0] === "news" && segs[1]) {
    const article = demoData.articles.find((a) => a.slug === segs[1]);
    if (!article) return null;
    const artists = demoData.artists.filter((a) => article.relatedArtistIds.includes(a.id));
    const more = demoData.articles.filter((a) => a.id !== article.id && a.category === article.category).slice(0, 4);
    const videos = artists.flatMap((a) => demoData.videos.filter((v) => v.artistId === a.id)).slice(0, 2);
    return [
      {
        title: "Featured in this story",
        items: [
          ...artists.map((a) => ({ href: `/artist/${a.slug}`, label: a.name, sub: a.agency, icon: Music2 })),
          ...videos.map((v) => ({ href: `/watch/${v.id}`, label: v.title, sub: "Watch", icon: PlayCircle })),
        ].slice(0, 4),
      },
      {
        title: `More in ${article.category}`,
        items: more.length
          ? more.map((a) => ({ href: `/news/${a.slug}`, label: a.title, icon: Newspaper }))
          : pickHubs(["/latest"]),
      },
      { title: "Join the conversation", items: pickHubs(["/community", "/forum", "/polls"]).slice(0, 4) },
    ];
  }

  // Watch / video page
  if (segs[0] === "watch" && segs[1]) {
    const video = demoData.videos.find((v) => v.id === segs[1]);
    if (!video) return null;
    const artist = demoData.artists.find((a) => a.id === video.artistId);
    const more = demoData.videos.filter((v) => v.id !== video.id && v.artistId === video.artistId).slice(0, 4);
    const news = artist ? demoData.articles.filter((a) => a.relatedArtistIds.includes(artist.id)).slice(0, 3) : [];
    return [
      {
        title: artist ? `More from ${artist.name}` : "More videos",
        items: [
          ...(artist ? [{ href: `/artist/${artist.slug}`, label: `${artist.name} profile`, sub: "Members, news, videos", icon: Music2 } as Item] : []),
          ...more.map((v) => ({ href: `/watch/${v.id}`, label: v.title, icon: PlayCircle } as Item)),
        ].slice(0, 4),
      },
      {
        title: "Related reading",
        items: news.length
          ? news.map((a) => ({ href: `/news/${a.slug}`, label: a.title, icon: Newspaper }))
          : pickHubs(["/latest", "/videos"]),
      },
      { title: "Keep exploring", items: pickHubs(["/videos"]) },
    ];
  }

  // Member detail
  if (segs[0] === "member" && segs[1]) {
    const member = demoData.members.find((m) => m.slug === segs[1]);
    if (!member) return null;
    const artist = demoData.artists.find((a) => a.id === member.artistId);
    const groupmates = demoData.members.filter((m) => m.groupId === member.artistId && m.id !== member.id).slice(0, 4);
    return [
      ...(artist
        ? [{ title: `From ${artist.name}`, items: [{ href: `/artist/${artist.slug}`, label: `${artist.name} profile`, sub: artist.fandomName, icon: Music2 }] as Item[] }]
        : []),
      { title: "Groupmates", items: groupmates.map((m) => ({ href: `/member/${m.slug}`, label: m.stageName, sub: m.position.join(", "), icon: Users })) },
      { title: "Keep exploring", items: pickHubs([]) },
    ];
  }

  // Forum thread
  if (segs[0] === "thread" && segs[1]) {
    const thread = demoData.threads.find((t) => t.slug === segs[1]);
    if (!thread) return null;
    const cat = demoData.categories.find((c) => c.id === thread.categoryId);
    const more = demoData.threads.filter((t) => t.id !== thread.id && t.categoryId === thread.categoryId).slice(0, 4);
    return [
      ...(cat ? [{ title: `More in ${cat.name}`, items: [{ href: `/forum/${cat.slug}`, label: `Browse ${cat.name}`, icon: MessageSquare }, ...more.map((t) => ({ href: `/thread/${t.slug}`, label: t.title, icon: MessageSquare }))] as Item[] }] : []),
      { title: "Keep exploring", items: pickHubs(["/forum", "/community"]) },
    ];
  }

  // Polls detail
  if (segs[0] === "polls" && segs[1]) {
    const others = demoData.polls.filter((p) => p.slug !== segs[1]).slice(0, 4);
    return [
      { title: "More polls", items: others.map((p) => ({ href: `/polls/${p.slug}`, label: p.title, icon: Vote })) },
      { title: "Keep exploring", items: pickHubs(["/polls"]) },
    ];
  }

  // Hub / listing pages — cross-link
  const hubFallbacks: Record<string, string[]> = {
    "/": ["/"],
    "/trending": ["/trending"],
    "/latest": ["/latest"],
    "/artists": ["/artists"],
    "/charts": ["/charts"],
    "/comebacks": ["/comebacks"],
    "/polls": ["/polls"],
    "/videos": ["/videos"],
    "/community": ["/community"],
    "/forum": ["/forum"],
  };
  const exclude = hubFallbacks[pathname];
  if (exclude) {
    const featuredArtists = demoData.artists.slice(0, 4);
    const featuredArticles = demoData.articles.slice(0, 4);
    return [
      { title: "Explore the site", items: pickHubs(exclude, 6) },
      { title: "Featured artists", items: featuredArtists.map((a) => ({ href: `/artist/${a.slug}`, label: a.name, sub: a.fandomName, icon: Music2 })) },
      { title: "Top stories", items: featuredArticles.map((a) => ({ href: `/news/${a.slug}`, label: a.title, icon: Newspaper })) },
    ];
  }

  // Default — generic discovery
  return [{ title: "Keep exploring KpopBlog", items: pickHubs([], 6) }];
}

export function KeepExploring() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  if (HIDE_PREFIXES.some((p) => pathname.startsWith(p))) return null;
  const sections = buildSections(pathname);
  if (!sections || sections.length === 0) return null;

  return (
    <section aria-label="Keep exploring" className="mx-auto max-w-7xl px-4 w-full my-12">
      <div className="rounded-2xl border border-border bg-card/40 backdrop-blur p-6 lg:p-8">
        <div className="flex items-center gap-2 mb-6">
          <Sparkles className="h-4 w-4 text-primary" />
          <h2 className="font-display text-lg font-bold">Keep exploring</h2>
        </div>
        <div className="grid gap-6 lg:grid-cols-3">
          {sections.map((sec) => (
            <div key={sec.title}>
              <h3 className="text-xs uppercase tracking-wider text-muted-foreground mb-3">{sec.title}</h3>
              <ul className="space-y-2">
                {sec.items.map((it) => {
                  const Icon = it.icon;
                  return (
                    <li key={it.href + it.label}>
                      <Link
                        to={it.href}
                        className="group flex items-start gap-3 rounded-lg p-2 -mx-2 hover:bg-accent/50 transition"
                      >
                        <Icon className="h-4 w-4 mt-0.5 text-primary shrink-0" />
                        <div className="min-w-0 flex-1">
                          <div className="text-sm font-medium line-clamp-2 group-hover:text-primary transition">
                            {it.label}
                          </div>
                          {it.sub && <div className="text-xs text-muted-foreground truncate">{it.sub}</div>}
                        </div>
                        <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 transition mt-1" />
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
