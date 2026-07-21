import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useMemo, useState } from "react";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { AdSlot } from "@/components/ads/AdSlot";
import { Play, MessageSquare, Loader2 } from "lucide-react";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { cmsProvider } from "@/services/cms";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import type { Comment } from "@/types";

export const Route = createFileRoute("/watch/$videoId")({
  head: ({ params }) =>
    buildHead({
      title: "Watch",
      description: "Watch K-pop videos and join the conversation.",
      canonical: `/watch/${params.videoId}`,
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
  const { videoId } = Route.useParams();
  const { data, isLoading, error: runtimeError } = useRuntimeData();
  const { user } = useAuth();
  const { show } = useAuthModal();
  const video = data.videos.find((item) => item.id === videoId || item.slug === videoId);
  const artist = video ? data.artists.find((item) => item.id === video.artistId || item.slug === video.artistId || item.slug === video.artistSlug) : undefined;
  const related = useMemo(
    () => video ? data.videos.filter((item) => (item.artistId === video.artistId || item.artistSlug === video.artistSlug) && item.id !== video.id) : [],
    [data.videos, video]
  );
  const other = useMemo(
    () => video ? data.videos.filter((item) => item.artistId !== video.artistId && item.artistSlug !== video.artistSlug).slice(0, 6) : [],
    [data.videos, video]
  );

  const [comments, setComments] = useState<Comment[]>([]);
  const [body, setBody] = useState("");
  const [loadingComments, setLoadingComments] = useState(false);
  const [posting, setPosting] = useState(false);
  const [commentError, setCommentError] = useState<string | null>(null);
  const [commentNotice, setCommentNotice] = useState<string | null>(null);

  useEffect(() => {
    const slug = video?.slug;
    if (!slug || !cmsProvider.listVideoComments) {
      setComments([]);
      return;
    }
    let active = true;
    setLoadingComments(true);
    setCommentError(null);
    void cmsProvider.listVideoComments(slug)
      .then((items) => { if (active) setComments(items); })
      .catch((requestError) => { if (active) setCommentError(requestError instanceof Error ? requestError.message : "Comments could not be loaded."); })
      .finally(() => { if (active) setLoadingComments(false); });
    return () => { active = false; };
  }, [video?.slug]);

  async function addComment(e: React.FormEvent) {
    e.preventDefault();
    const trimmedBody = body.trim();
    if (!trimmedBody) return;
    if (!user) {
      show("Log in to comment");
      return;
    }
    if (!video?.slug || !cmsProvider.postVideoComment) return;
    setPosting(true);
    setCommentError(null);
    setCommentNotice(null);
    const result = await cmsProvider.postVideoComment(video.slug, trimmedBody);
    setPosting(false);
    if (!result.ok) {
      setCommentError(result.error ?? "Comment could not be saved.");
      return;
    }
    if (result.item && !result.pending) setComments((current) => [result.item!, ...current]);
    setBody("");
    setCommentNotice(result.pending ? "Your comment is awaiting moderation." : "Your comment was posted.");
  }

  if (isLoading) return <div className="mx-auto max-w-7xl px-4 py-12 text-muted-foreground">Loading video…</div>;
  if (runtimeError) return <div className="mx-auto max-w-7xl px-4 py-12 text-destructive">{runtimeError}</div>;
  if (!video) return <div className="mx-auto max-w-3xl px-4 py-16 text-center"><h1 className="font-display text-2xl font-bold">Video not found</h1><Link to="/videos" className="text-primary mt-4 inline-block">Browse all videos</Link></div>;

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
            Comments are saved to WordPress and may be reviewed by moderators.
          </p>

          <form onSubmit={addComment} className="mt-4 space-y-2 rounded-xl border border-border bg-card p-4">
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
              <Button type="submit" disabled={posting || !body.trim()}>{posting && <Loader2 className="size-3 animate-spin" />} Post comment</Button>
            </div>
          </form>
          {commentError && <p role="alert" className="mt-3 text-sm text-destructive">{commentError}</p>}
          {commentNotice && <p role="status" className="mt-3 text-sm text-primary">{commentNotice}</p>}

          <ul className="mt-4 space-y-3">
            {loadingComments && <li className="text-sm text-muted-foreground py-6 text-center">Loading comments…</li>}
            {comments.length === 0 && !loadingComments && (
              <li className="text-sm text-muted-foreground py-6 text-center border border-dashed border-border rounded-xl">
                Be the first to comment on this video.
              </li>
            )}
            {comments.map((c) => (
              <li key={c.id} className="p-3 rounded-xl bg-card border border-border">
                <div className="flex items-center justify-between">
                  <div className="font-semibold text-sm">{c.author?.displayName ?? "Community member"}</div>
                  <div className="text-xs text-muted-foreground">{new Date(c.createdAt).toLocaleString()}</div>
                </div>
                <p className="mt-1 text-sm whitespace-pre-wrap">{c.body}</p>
                {c.status === "pending" && <div className="mt-2 text-xs text-muted-foreground">Awaiting moderation</div>}
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
