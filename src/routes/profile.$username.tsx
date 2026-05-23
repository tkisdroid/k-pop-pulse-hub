import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { gamification, BADGES, type ActivityEntry, type GamificationStats } from "@/services/gamification";
import { personalization } from "@/services/personalization";
import { Newspaper, MessageCircle, Vote, Heart, Flame, Trophy } from "lucide-react";

export const Route = createFileRoute("/profile/$username")({
  head: ({ params }) => buildHead({ title: `${params.username}`, canonical: `/profile/${params.username}` }),
  component: ProfilePage,
});

const STAT_ICON = {
  articlesRead: Newspaper,
  commentsPosted: MessageCircle,
  pollsVoted: Vote,
  artistsFollowed: Heart,
  streakDays: Flame,
  points: Trophy,
} as const;

function timeAgo(iso: string) {
  const s = Math.floor((Date.now() - +new Date(iso)) / 1000);
  if (s < 60) return `${s}s ago`;
  if (s < 3600) return `${Math.floor(s / 60)}m ago`;
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
  return `${Math.floor(s / 86400)}d ago`;
}

function ProfilePage() {
  const { username } = Route.useParams();
  const { user: me } = useAuth();
  let user = demoData.users.find((u) => u.username === username);
  if (username === "me" && me) user = me;
  if (!user) throw notFound();
  const isMe = me?.id === user.id;

  const [stats, setStats] = useState<GamificationStats>(() => gamification.stats());
  const [activity, setActivity] = useState<ActivityEntry[]>(() => gamification.activity());
  const [followedIds, setFollowedIds] = useState<string[]>(() =>
    isMe ? personalization.signals().followedArtists : user!.followedArtists,
  );

  useEffect(() => {
    if (!isMe) return;
    const u1 = gamification.subscribe(setStats);
    const u2 = gamification.subscribeActivity(setActivity);
    const sync = () => setFollowedIds(personalization.signals().followedArtists);
    window.addEventListener("storage", sync);
    return () => { u1(); u2(); window.removeEventListener("storage", sync); };
  }, [isMe]);

  const points = isMe ? stats.points : user.points;
  const { current, next, pct } = gamification.progress(points);
  const earnedBadgeIds = new Set(
    isMe ? gamification.earnedBadges().map((b) => b.id) : user.badges,
  );
  const followedArtists = followedIds
    .map((id) => demoData.artists.find((a) => a.id === id || a.slug === id))
    .filter(Boolean) as typeof demoData.artists;

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row gap-6 mb-8">
        <div className="relative">
          <img src={user.avatar} alt="" className="size-24 rounded-full" />
          <span
            className="absolute -bottom-1 -right-1 px-2 py-0.5 rounded-full text-[10px] font-bold text-background"
            style={{ background: current.color }}
          >
            LV {current.level}
          </span>
        </div>
        <div className="flex-1 min-w-0">
          <h1 className="font-display text-3xl font-bold">{user.displayName}</h1>
          <div className="text-sm text-muted-foreground">
            @{user.username} · {user.role} · Trust level {user.trustLevel}
          </div>
          <p className="mt-2 text-sm">{user.bio ?? "K-pop fan from around the world."}</p>
          <div className="mt-4">
            <div className="flex items-center justify-between text-xs mb-1">
              <span className="font-semibold" style={{ color: current.color }}>{current.title}</span>
              <span className="text-muted-foreground">
                {next ? `${points - current.minPoints} / ${next.minPoints - current.minPoints} XP to ${next.title}` : "Max level"}
              </span>
            </div>
            <Progress value={pct} />
          </div>
        </div>
        <div className="flex flex-col gap-2">
          {!isMe ? <Button>Follow</Button> : <Button variant="outline">Edit profile</Button>}
        </div>
      </div>

      {/* Stat cards */}
      <div className="grid gap-3 grid-cols-2 md:grid-cols-4 mb-8">
        {[
          { k: "points", v: points, label: "Points" },
          { k: "articlesRead", v: stats.articlesRead, label: "Articles read" },
          { k: "commentsPosted", v: stats.commentsPosted, label: "Comments" },
          { k: "pollsVoted", v: stats.pollsVoted, label: "Polls voted" },
        ].map(({ k, v, label }) => {
          const Icon = STAT_ICON[k as keyof typeof STAT_ICON] ?? Trophy;
          return (
            <div key={k} className="p-4 rounded-xl bg-card border border-border">
              <Icon className="size-4 text-primary mb-2" />
              <div className="text-2xl font-bold">{(isMe ? v : k === "points" ? user.points : "—").toString()}</div>
              <div className="text-xs text-muted-foreground">{label}</div>
            </div>
          );
        })}
      </div>

      {/* Badges */}
      <section className="mb-8">
        <h2 className="font-display text-xl font-bold mb-3">Badges</h2>
        <div className="grid gap-3 grid-cols-2 sm:grid-cols-3 md:grid-cols-4">
          {BADGES.map((b) => {
            const earned = earnedBadgeIds.has(b.id);
            return (
              <div
                key={b.id}
                className={`p-3 rounded-xl border text-center ${earned ? "border-primary/40 bg-primary/5" : "border-border bg-muted/30 opacity-50"}`}
              >
                <div className="text-3xl">{b.emoji}</div>
                <div className="text-sm font-semibold mt-1">{b.label}</div>
                <div className="text-[11px] text-muted-foreground">{b.description}</div>
              </div>
            );
          })}
        </div>
      </section>

      {/* Two-column: followed artists + activity */}
      <div className="grid gap-8 md:grid-cols-2">
        <section>
          <h2 className="font-display text-xl font-bold mb-3">Followed artists ({followedArtists.length})</h2>
          {followedArtists.length === 0 ? (
            <div className="p-4 rounded-xl border border-dashed border-border text-sm text-muted-foreground text-center">
              Not following anyone yet. <Link to="/artists" className="text-primary hover:underline">Browse artists →</Link>
            </div>
          ) : (
            <ul className="grid gap-2">
              {followedArtists.map((a) => (
                <li key={a.id}>
                  <Link
                    to="/artist/$slug"
                    params={{ slug: a.slug }}
                    className="flex items-center gap-3 p-2 rounded-lg hover:bg-accent/40"
                  >
                    <img src={a.imageUrl} alt="" className="size-10 rounded-full object-cover" />
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-semibold truncate">{a.name}</div>
                      <div className="text-xs text-muted-foreground truncate">{a.koreanName ?? a.company ?? ""}</div>
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section>
          <h2 className="font-display text-xl font-bold mb-3">Recent activity</h2>
          {!isMe ? (
            <div className="p-4 rounded-xl border border-dashed border-border text-sm text-muted-foreground text-center">
              Activity is private to the user.
            </div>
          ) : activity.length === 0 ? (
            <div className="p-4 rounded-xl border border-dashed border-border text-sm text-muted-foreground text-center">
              No activity yet. Read articles or comment to earn points.
            </div>
          ) : (
            <ul className="space-y-2">
              {activity.slice(0, 20).map((a) => {
                const Icon = STAT_ICON[a.kind] ?? Trophy;
                return (
                  <li key={a.id} className="flex items-center gap-3 p-2 rounded-lg bg-card border border-border">
                    <div className="size-8 rounded-full bg-accent grid place-items-center shrink-0">
                      <Icon className="size-4 text-primary" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="text-sm">{a.label}</div>
                      <div className="text-[11px] text-muted-foreground">{timeAgo(a.at)}</div>
                    </div>
                    {a.points > 0 && (
                      <span className="text-xs font-semibold text-primary">+{a.points}</span>
                    )}
                  </li>
                );
              })}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}
