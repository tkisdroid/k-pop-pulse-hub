import { createFileRoute, Link } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/forum")({
  head: () => buildHead({ title: "K-pop Forum", description: "Fan community discussions.", canonical: "/forum" }),
  component: ForumIndex,
});

function ForumIndex() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-8 grid gap-8 lg:grid-cols-3">
      <div className="lg:col-span-2">
        <SectionHeader eyebrow="Forum" title="Categories" />
        <div className="grid gap-3 sm:grid-cols-2">
          {demoData.categories.map((c) => (
            <Link key={c.id} to="/forum/$categorySlug" params={{ categorySlug: c.slug }} className="p-4 rounded-xl bg-card border border-border hover:border-primary/40">
              <div className="flex items-center gap-3 mb-2"><div className="size-10 grid place-items-center rounded-lg bg-accent text-xl">{c.icon}</div><div className="font-display font-bold">{c.name}</div></div>
              <p className="text-sm text-muted-foreground">{c.description}</p>
              <div className="mt-3 text-xs text-muted-foreground">{c.threadCount} threads · {c.postCount} posts</div>
            </Link>
          ))}
        </div>
        <SectionHeader title="Latest threads" />
        <div className="grid gap-2">
          {demoData.threads.slice(0, 8).map((t) => (
            <Link key={t.id} to="/thread/$threadSlug" params={{ threadSlug: t.slug }} className="p-3 rounded-xl bg-card border border-border hover:border-primary/40">
              <div className="flex items-center gap-2 flex-wrap mb-1">
                {t.pinned && <span className="text-[10px] px-1.5 py-0.5 rounded bg-primary text-primary-foreground">PINNED</span>}
                {t.flair && <span className="text-[10px] px-1.5 py-0.5 rounded bg-accent">{t.flair}</span>}
                {t.rumor && <span className="text-[10px] px-1.5 py-0.5 rounded bg-destructive text-destructive-foreground">RUMOR</span>}
              </div>
              <div className="font-semibold">{t.title}</div>
              <div className="text-xs text-muted-foreground">{t.replies} replies · {t.views} views</div>
            </Link>
          ))}
        </div>
      </div>
      <aside className="space-y-4">
        <div className="p-4 rounded-xl bg-card border border-border">
          <h3 className="font-semibold mb-2">New here?</h3>
          <p className="text-sm text-muted-foreground mb-3">Welcome to the global K-pop community. Read the rules and say hi.</p>
          <Button asChild size="sm"><Link to="/community-guidelines">Read community rules</Link></Button>
        </div>
        <div className="p-4 rounded-xl bg-card border border-border">
          <h3 className="font-semibold mb-2">Start a discussion</h3>
          <p className="text-sm text-muted-foreground mb-3">Have something to share? Open a thread in any category.</p>
          <Button size="sm">Create thread</Button>
        </div>
        <div className="rounded-xl border border-dashed border-border bg-muted/40 p-4 text-center text-xs text-muted-foreground">Sidebar ad slot</div>
      </aside>
    </div>
  );
}
