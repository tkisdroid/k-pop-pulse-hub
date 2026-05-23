import { createFileRoute, notFound } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/profile/$username")({
  head: ({ params }) => buildHead({ title: `${params.username}`, canonical: `/profile/${params.username}` }),
  component: ProfilePage,
});

function ProfilePage() {
  const { username } = Route.useParams();
  const { user: me } = useAuth();
  let user = demoData.users.find((u) => u.username === username);
  if (username === "me" && me) user = me;
  if (!user) throw notFound();
  const isMe = me?.id === user.id;
  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <div className="flex flex-col md:flex-row gap-6 mb-6">
        <img src={user.avatar} alt="" className="size-24 rounded-full" />
        <div className="flex-1">
          <h1 className="font-display text-3xl font-bold">{user.displayName}</h1>
          <div className="text-sm text-muted-foreground">@{user.username} · {user.role} · Trust level {user.trustLevel}</div>
          <p className="mt-2 text-sm">{user.bio ?? "K-pop fan from around the world."}</p>
          <div className="mt-3 flex flex-wrap gap-1">
            {user.badges.map((b) => {
              const badge = demoData.badges.find((x) => x.id === b);
              if (!badge) return null;
              return <span key={b} className="px-2 py-0.5 text-xs rounded-full bg-accent">{badge.icon} {badge.name}</span>;
            })}
          </div>
        </div>
        <div className="flex flex-col gap-2">{!isMe ? <Button>Follow</Button> : <Button variant="outline">Edit profile</Button>}</div>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl bg-card border border-border"><div className="text-xs text-muted-foreground">Points</div><div className="text-2xl font-bold">{user.points}</div></div>
        <div className="p-4 rounded-xl bg-card border border-border"><div className="text-xs text-muted-foreground">Followed artists</div><div className="text-2xl font-bold">{user.followedArtists.length}</div></div>
        <div className="p-4 rounded-xl bg-card border border-border"><div className="text-xs text-muted-foreground">Country / Language</div><div className="font-medium">{user.country ?? "Global"} · {user.language?.toUpperCase() ?? "EN"}</div></div>
      </div>
      <div className="mt-8 grid gap-2">
        <h2 className="font-display text-xl font-bold">Activity</h2>
        <div className="text-sm text-muted-foreground">Posts, threads, comments and bookmarks will appear here once Supabase is connected.</div>
      </div>
    </div>
  );
}
