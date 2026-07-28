import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { buildHead } from "@/components/layout/seo";
import { LocalTime } from "@/components/layout/LocalTime";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { FollowArtistButton } from "@/components/artists/FollowArtistButton";
import { ShareButtons } from "@/components/articles/ShareButtons";

export const Route = createFileRoute("/artist/$slug")({
  head: ({ params }) => buildHead({ title: "Artist", canonical: `/artist/${params.slug}` }),
  component: ArtistPage,
});

const TABS = ["Overview", "News", "Videos", "Members", "Comebacks", "Forum", "Polls"] as const;

function ArtistPage() {
  const { slug } = Route.useParams();
  const { data, isLoading, error } = useRuntimeData();
  const [tab, setTab] = useState<typeof TABS[number]>("Overview");
  if (isLoading) return <div className="mx-auto max-w-7xl px-4 py-12 text-muted-foreground">Loading artist…</div>;
  if (error) return <div className="mx-auto max-w-7xl px-4 py-12 text-destructive">{error}</div>;

  const artist = data.artists.find((item) => item.slug === slug);
  if (!artist) return <div className="mx-auto max-w-7xl px-4 py-12 text-muted-foreground">Artist not found.</div>;

  const artistKeys = new Set([artist.id, artist.slug]);
  const members = data.members.filter((m) => artistKeys.has(m.groupId));
  const news = data.articles.filter((a) => a.relatedArtistIds.some((id) => artistKeys.has(id)));
  const videos = data.videos.filter((v) => artistKeys.has(v.artistId));
  const comebacks = data.comebacks.filter((c) => artistKeys.has(c.artistId));
  const threads = data.threads.slice(0, 4);

  return (
    <div>
      <div className="relative h-64 md:h-80 overflow-hidden">
        <img src={artist.image} alt={artist.name} fetchPriority="high" decoding="async" width={1920} height={640} className="size-full object-cover" />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-transparent" />
      </div>
      <div className="mx-auto max-w-7xl px-4 -mt-20 relative">
        <div className="flex flex-col md:flex-row md:items-end gap-4">
          <div className="size-32 md:size-40 rounded-2xl overflow-hidden border-4 border-background shrink-0"><img src={artist.image} alt={artist.name} loading="lazy" decoding="async" width={160} height={160} className="size-full object-cover" /></div>
          <div className="flex-1">
            <div className="text-xs uppercase tracking-wider text-primary">{artist.type.replace("_", " ")} · {artist.agency}</div>
            <h1 className="font-display text-4xl font-bold">{artist.name}</h1>
            <p className="text-sm text-muted-foreground">Fandom: {artist.fandomName} · Debut <LocalTime value={artist.debutDate} mode="date" /> · {artist.followerCount.toLocaleString()} followers</p>
          </div>
          <div className="flex flex-wrap gap-2"><FollowArtistButton artist={artist} /><ShareButtons title={`${artist.name} on KpopBlog`} url={typeof window !== "undefined" ? window.location.href : `/artist/${artist.slug}`} /></div>
        </div>
        <div className="mt-6 flex gap-1 overflow-x-auto scrollbar-hide border-b border-border">
          {TABS.map((t) => (
            <button key={t} onClick={() => setTab(t)} className={`shrink-0 px-4 py-2 text-sm border-b-2 ${tab === t ? "border-primary text-primary" : "border-transparent text-muted-foreground"}`}>{t}</button>
          ))}
        </div>

        <div className="py-6">
          {tab === "Overview" && (
            <div className="grid gap-6 lg:grid-cols-3">
              <div className="lg:col-span-2">
                <h2 className="font-display text-xl font-bold mb-2">About</h2>
                <p className="text-muted-foreground">{artist.bio}</p>
              </div>
              <div className="space-y-3">
                <div className="p-4 rounded-xl bg-card border border-border">
                  <div className="text-xs uppercase text-muted-foreground">Status</div>
                  <div className="font-semibold">{artist.status}</div>
                </div>
                <div className="p-4 rounded-xl bg-card border border-border">
                  <div className="text-xs uppercase text-muted-foreground">Next event</div>
                  <div className="font-semibold">{comebacks[0]?.title ?? "TBA"}</div>
                </div>
              </div>
            </div>
          )}
          {tab === "News" && <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{news.map((a) => <ArticleCard key={a.id} article={a} />)}</div>}
          {tab === "Videos" && (
            videos.length === 0 ? (
              <div className="text-muted-foreground py-8">No videos yet for this artist.</div>
            ) : (
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {videos.map((v) => (
                  <Link key={v.id} to="/watch/$videoId" params={{ videoId: v.id }} className="rounded-xl overflow-hidden bg-card border border-border group">
                    <div className="aspect-video relative">
                      <img src={v.thumbnail} alt={v.title} className="size-full object-cover" loading="lazy" />
                      <div className="absolute inset-0 grid place-items-center bg-black/30 group-hover:bg-black/50 transition">
                        <span className="size-12 rounded-full bg-primary/90 grid place-items-center text-primary-foreground text-xl">▶</span>
                      </div>
                      <div className="absolute bottom-2 right-2 px-1.5 py-0.5 rounded bg-black/70 text-white text-xs">{v.duration}</div>
                    </div>
                    <div className="p-3">
                      <div className="text-xs text-primary uppercase">{v.category}</div>
                      <div className="font-semibold text-sm line-clamp-2">{v.title}</div>
                    </div>
                  </Link>
                ))}
              </div>
            )
          )}
          {tab === "Members" && (
            <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
              {members.map((m) => (
                <Link key={m.id} to="/member/$slug" params={{ slug: m.slug }} className="rounded-xl overflow-hidden bg-card border border-border">
                  <div className="aspect-square overflow-hidden"><img src={m.image} alt={m.stageName} loading="lazy" decoding="async" width={300} height={300} className="size-full object-cover" /></div>
                  <div className="p-3">
                    <div className="font-display font-bold">{m.stageName}</div>
                    <div className="text-xs text-muted-foreground">{m.position.join(", ")} · {m.nationality}</div>
                  </div>
                </Link>
              ))}
            </div>
          )}
          {tab === "Comebacks" && (
            <div className="grid gap-3">
              {comebacks.map((c) => <div key={c.id} className="p-3 rounded-xl bg-card border border-border"><div className="font-semibold">{c.title}</div><div className="text-xs text-muted-foreground">{c.type} · {new Date(c.releaseAt).toDateString()}</div></div>)}
            </div>
          )}
          {tab === "Forum" && (
            <div className="grid gap-2">
              {threads.map((t) => <Link key={t.id} to="/thread/$threadSlug" params={{ threadSlug: t.slug }} className="p-3 rounded-xl bg-card border border-border">{t.title}</Link>)}
            </div>
          )}
          {tab === "Polls" && <div className="grid gap-3 sm:grid-cols-2">{data.polls.slice(0, 2).map((p) => <Link key={p.id} to="/polls/$slug" params={{ slug: p.slug }} className="p-4 rounded-xl bg-card border border-border"><div className="font-semibold">{p.title}</div></Link>)}</div>}
        </div>
      </div>
    </div>
  );
}
