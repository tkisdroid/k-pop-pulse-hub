import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/member/$slug")({
  loader: ({ params }) => {
    const m = demoData.members.find((x) => x.slug === params.slug);
    if (!m) throw notFound();
    return m;
  },
  head: ({ loaderData }) => buildHead({ title: loaderData?.stageName ?? "Member", canonical: `/member/${loaderData?.slug}` }),
  component: MemberPage,
});

function MemberPage() {
  const m = Route.useLoaderData();
  const group = demoData.artists.find((a) => a.id === m.groupId)!;
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 grid gap-6 md:grid-cols-3">
      <div>
        <div className="rounded-2xl overflow-hidden"><img src={m.image} alt={m.stageName} className="w-full aspect-square object-cover" /></div>
      </div>
      <div className="md:col-span-2">
        <div className="text-xs uppercase text-primary"><Link to="/artist/$slug" params={{ slug: group.slug }}>{group.name}</Link></div>
        <h1 className="font-display text-4xl font-bold">{m.stageName}</h1>
        <p className="text-muted-foreground">{m.fullName} · {m.koreanName}</p>
        <div className="mt-4 grid grid-cols-2 gap-2 text-sm">
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">Birthday</div>{new Date(m.birthday).toLocaleDateString()}</div>
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">Nationality</div>{m.nationality}</div>
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">Position</div>{m.position.join(", ")}</div>
          <div className="p-3 rounded-md bg-card border border-border"><div className="text-xs text-muted-foreground">MBTI</div>{m.mbti}</div>
        </div>
        <h2 className="font-display text-xl font-bold mt-6 mb-2">Facts</h2>
        <ul className="list-disc pl-5 text-sm text-muted-foreground space-y-1">{m.facts.map((f: string, i: number) => <li key={i}>{f}</li>)}</ul>
        <div className="mt-6 flex gap-2"><Button>Follow</Button><Button variant="outline">Suggest correction</Button></div>
      </div>
    </div>
  );
}
