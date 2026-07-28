import { createFileRoute, Link } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";
import { LocalTime } from "@/components/layout/LocalTime";
import { Button } from "@/components/ui/button";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { FollowArtistButton } from "@/components/artists/FollowArtistButton";

export const Route = createFileRoute("/member/$slug")({
  head: ({ params }) => buildHead({ title: "Member", canonical: `/member/${params.slug}` }),
  component: MemberPage,
});

function MemberPage() {
  const { slug } = Route.useParams();
  const { data, isLoading, error } = useRuntimeData();
  if (isLoading) return <div className="mx-auto max-w-5xl px-4 py-12 text-muted-foreground">Loading member…</div>;
  if (error) return <div className="mx-auto max-w-5xl px-4 py-12 text-destructive">{error}</div>;

  const m = data.members.find((item) => item.slug === slug);
  if (!m) return <div className="mx-auto max-w-5xl px-4 py-12 text-muted-foreground">Member not found.</div>;

  const group = data.artists.find((artist) => artist.id === m.groupId || artist.slug === m.groupId);
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 grid gap-6 md:grid-cols-3">
      <div>
        <div className="rounded-2xl overflow-hidden"><img src={m.image} alt={m.stageName} fetchPriority="high" decoding="async" width={800} height={800} className="w-full aspect-square object-cover" /></div>
      </div>
      <div className="md:col-span-2">
        <div className="text-xs uppercase text-primary">{group ? <Link to="/artist/$slug" params={{ slug: group.slug }}>{group.name}</Link> : "Independent artist"}</div>
        <h1 className="font-display text-4xl font-bold">{m.stageName}</h1>
        <p className="text-muted-foreground">{m.fullName} · {m.koreanName}</p>
        <div className="mt-4 grid grid-cols-2 gap-2 text-sm">
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">Birthday</div><LocalTime value={m.birthday} mode="date" /></div>
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">Nationality</div>{m.nationality}</div>
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">Position</div>{m.position.join(", ")}</div>
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">MBTI</div>{m.mbti}</div>
        </div>
        <h2 className="font-display text-xl font-bold mt-6 mb-2">Facts</h2>
        <ul className="list-disc pl-5 text-sm text-muted-foreground space-y-1">{m.facts.map((f: string, i: number) => <li key={i}>{f}</li>)}</ul>
        <div className="mt-6 flex gap-2">{group && <FollowArtistButton artist={group} />}<Button variant="outline" asChild><Link to="/submit">Suggest correction</Link></Button></div>
      </div>
    </div>
  );
}
