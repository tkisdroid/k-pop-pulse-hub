import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { AdSlot } from "@/components/ads/AdSlot";
import { Play, MessageSquare, ThumbsUp, Trash2 } from "lucide-react";

type LocalComment = {
  id: string;
  videoId: string;
  author: string;
  body: string;
  likes: number;
  createdAt: string;
};

const STORAGE_KEY = "kpopblog:videoComments";

function loadComments(): LocalComment[] {
  if (typeof window === "undefined") return [];
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? "[]");
  } catch {
    return [];
  }
}

function saveComments(c: LocalComment[]) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(c));
}

export const Route = createFileRoute("/watch/$videoId")({
  loader: ({ params }) => {
    const video = demoData.videos.find((v) => v.id === params.videoId);
    if (!video) throw notFound();
    const artist = demoData.artists.find((a) => a.id === video.artistId);
    return { video, artist };
  },
  head: ({ loaderData }) =>
    buildHead({
      title: loaderData?.video.title ?? "Watch",
      description: `Watch ${loaderData?.video.title} on ${loaderData?.artist?.name ?? "K-Pop Blog"} and join the conversation.`,
      canonical: `/watch/${loaderData?.video.id}`,
      ogImage: loaderData?.video.thumbnail,
    }),
  component: WatchPage,
  notFoundComponent: () => (
    <div className="mx-auto max-w-3xl px-4 py-16 text-center">
      <h1 className="font-display text-2xl font-bold">Video not found</h1>
      <Link to="/videos" className="text-primary mt-4 inline-block">Browse all videos</Link>
    </div>
  ),
});

function WatchPage() {
  const { video, artist } = Route.useLoaderData();
  const related = useMemo(
    () => demoData.videos.filter((v) => v.artistId === video.artistId && v.id !== video.id),
    [video.id, video.artistId]
  );
  const other = useMemo(
    () => demoData.videos.filter((v) => v.artistId !== video.artistId).slice(0, 6),
    [video.artistId]
  );

  const [allComments, setAllComments] = useState<LocalComment[]>([]);
  const [name, setName] = useState("");
  const [body, setBody] = useState("");

  useEffect(() => {
    setAllComments(loadComments());
    const savedName = localStorage.getItem("kpopblog:commentName") ?? "";
    setName(savedName);
  }, []);

  const comments = allComments
    .filter((c) => c.videoId === video.id)
    .sort((a, b) => +new Date(b.createdAt) - +new Date(a.createdAt));

  function addComment(e: React.FormEvent) {
    e.preventDefault();
    const trimmedBody = body.trim();
    const author = (name.trim() || "Anonymous").slice(0, 40);
    if (!trimmedBody) return;
    localStorage.setItem("kpopblog:commentName", author);
    const next: LocalComment = {
      id: `lc_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
      videoId: video.id,
      author,
      body: trimmedBody.slice(0, 1000),
      likes: 0,
      createdAt: new Date().toISOString(),
    };
    const updated = [next, ...allComments];
    setAllComments(updated);
    saveComments(updated);
    setBody("");
  }

  function likeComment(id: string) {
    const updated = allComments.map((c) => (c.id === id ? { ...c, likes: c.likes + 1 } : c));
    setAllComments(updated);
    saveComments(updated);
  }

  function deleteComment(id: string) {
    const updated = allComments.filter((c) => c.id !== id);
    setAllComments(updated);
    saveComments(updated);
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-6 grid gap-8 lg:grid-cols-[1fr_340px]">
      <div className="min-w-0">
        <div className="aspect-video rounded-xl overflow-hidden bg-black ring-1 ring-border">
          <iframe
            key={video.id}
            src={`https://www.youtube-nocookie.com/embed/${video.youtubeId}?rel=0&modestbranding=1`}
            title={video.title}
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowFullScreen
            referrerPolicy="strict-origin-when-cross-origin"
            className="size-full"
          />
        </div>

        <div className="mt-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <div className="text-xs uppercase tracking-wider text-primary">{video.category}</div>
            <h1 className="font-display text-2xl font-bold">{video.title}</h1>
            {artist && (
              <Link to="/artist/$slug" params={{ slug: artist.slug }} className="mt-1 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-primary">
                <span className="size-6 rounded-full overflow-hidden"><img src={artist.image} alt="" className="size-full object-cover" /></span>
                {artist.name} · {artist.followerCount.toLocaleString()} followers
              </Link>
            )}
          </div>
          <a
            href={`https://www.youtube.com/watch?v=${video.youtubeId}`}
            target="_blank"
            rel="noopener noreferrer"
            className="text-xs text-muted-foreground underline"
          >
            Open on YouTube
          </a>
        </div>

        <AdSlot slotId={`watch-${video.id}-inline`} variant="in-article" className="my-6" />

        <section className="mt-6">
          <h2 className="font-display text-xl font-bold flex items-center gap-2">
            <MessageSquare className="size-5" /> Comments ({comments.length})
          </h2>
          <p className="text-xs text-muted-foreground mt-1">
            Comments are posted in-site and stored on your device. Be respectful — moderators review all reports.
          </p>

          <form onSubmit={addComment} className="mt-4 space-y-2 rounded-xl border border-border bg-card p-4">
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Display name (optional)"
              maxLength={40}
            />
            <Textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder={`Share your thoughts on "${video.title}"…`}
              rows={3}
              maxLength={1000}
              required
            />
            <div className="flex items-center justify-between">
              <span className="text-xs text-muted-foreground">{body.length}/1000</span>
              <Button type="submit" disabled={!body.trim()}>Post comment</Button>
            </div>
          </form>

          <ul className="mt-4 space-y-3">
            {comments.length === 0 && (
              <li className="text-sm text-muted-foreground py-6 text-center border border-dashed border-border rounded-xl">
                Be the first to comment on this video.
              </li>
            )}
            {comments.map((c) => (
              <li key={c.id} className="p-3 rounded-xl bg-card border border-border">
                <div className="flex items-center justify-between">
                  <div className="font-semibold text-sm">{c.author}</div>
                  <div className="text-xs text-muted-foreground">{new Date(c.createdAt).toLocaleString()}</div>
                </div>
                <p className="mt-1 text-sm whitespace-pre-wrap">{c.body}</p>
                <div className="mt-2 flex items-center gap-3 text-xs text-muted-foreground">
                  <button onClick={() => likeComment(c.id)} className="inline-flex items-center gap-1 hover:text-primary">
                    <ThumbsUp className="size-3.5" /> {c.likes}
                  </button>
                  <button onClick={() => deleteComment(c.id)} className="inline-flex items-center gap-1 hover:text-destructive">
                    <Trash2 className="size-3.5" /> Delete
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </section>
      </div>

      <aside className="space-y-6">
        {artist && (
          <div className="rounded-xl border border-border bg-card p-4">
            <div className="text-xs uppercase text-muted-foreground">More from</div>
            <div className="font-display text-lg font-bold">{artist.name}</div>
            <div className="mt-3 space-y-3">
              {related.map((v) => (
                <Link key={v.id} to="/watch/$videoId" params={{ videoId: v.id }} className="flex gap-3 group">
                  <div className="relative w-32 aspect-video rounded-md overflow-hidden shrink-0">
                    <img src={v.thumbnail} alt="" className="size-full object-cover" loading="lazy" />
                    <div className="absolute inset-0 grid place-items-center bg-black/30 opacity-0 group-hover:opacity-100 transition">
                      <Play className="size-8 text-white" />
                    </div>
                    <div className="absolute bottom-1 right-1 px-1 py-0.5 rounded bg-black/70 text-white text-[10px]">{v.duration}</div>
                  </div>
                  <div className="min-w-0">
                    <div className="text-sm font-medium line-clamp-2 group-hover:text-primary">{v.title}</div>
                    <div className="text-xs text-muted-foreground mt-1">{v.category}</div>
                  </div>
                </Link>
              ))}
              {related.length === 0 && <div className="text-xs text-muted-foreground">No other videos yet.</div>}
            </div>
          </div>
        )}

        <AdSlot slotId={`watch-${video.id}-sidebar`} variant="rectangle" />

        <div className="rounded-xl border border-border bg-card p-4">
          <div className="text-xs uppercase text-muted-foreground mb-2">Explore other artists</div>
          <div className="space-y-2">
            {other.map((v) => (
              <Link key={v.id} to="/watch/$videoId" params={{ videoId: v.id }} className="block text-sm hover:text-primary">
                {v.title}
              </Link>
            ))}
          </div>
        </div>
      </aside>
    </div>
  );
}
