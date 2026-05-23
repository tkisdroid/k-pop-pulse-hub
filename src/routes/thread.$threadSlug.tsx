import { createFileRoute, notFound } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { Lock, Pin, Flag, Languages } from "lucide-react";

export const Route = createFileRoute("/thread/$threadSlug")({
  loader: ({ params }) => {
    const t = demoData.threads.find((x) => x.slug === params.threadSlug);
    if (!t) throw notFound();
    return t;
  },
  head: ({ loaderData }) => buildHead({ title: loaderData?.title ?? "Thread", canonical: `/thread/${loaderData?.slug}` }),
  component: ThreadPage,
});

function ThreadPage() {
  const t = Route.useLoaderData();
  const author = demoData.users.find((u) => u.id === t.authorId)!;
  const posts = demoData.posts.filter((p) => p.threadId === t.id);
  const { user } = useAuth();
  const { show } = useAuthModal();
  const isMod = user?.role === "admin" || user?.role === "moderator";
  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <div className="flex items-center gap-2 mb-2 text-xs flex-wrap">
        {t.pinned && <span className="px-1.5 py-0.5 rounded bg-primary text-primary-foreground"><Pin className="inline size-3" /> PINNED</span>}
        {t.locked && <span className="px-1.5 py-0.5 rounded bg-muted"><Lock className="inline size-3" /> LOCKED</span>}
        {t.flair && <span className="px-1.5 py-0.5 rounded bg-accent">{t.flair}</span>}
        {t.rumor && <span className="px-1.5 py-0.5 rounded bg-destructive text-destructive-foreground">RUMOR — verify sources</span>}
      </div>
      <h1 className="font-display text-3xl font-bold">{t.title}</h1>
      <div className="mt-3 flex items-center gap-3 text-sm">
        <img src={author.avatar} alt="" className="size-8 rounded-full" />
        <span className="font-medium">{author.displayName}</span>
        <span className="text-muted-foreground">· {new Date(t.createdAt).toLocaleDateString()}</span>
      </div>
      <div className="mt-4 p-4 rounded-xl bg-card border border-border">
        <p>{t.body}</p>
        <div className="mt-3 flex gap-2 text-xs">
          <Button size="sm" variant="outline">♥ React ({t.reactions})</Button>
          <Button size="sm" variant="outline"><Languages className="size-3" /> Translate</Button>
          <Button size="sm" variant="ghost" onClick={() => (user ? null : show())}><Flag className="size-3" /> Report</Button>
        </div>
      </div>

      {isMod && (
        <div className="mt-4 p-3 rounded-xl bg-accent/50 border border-border">
          <div className="text-xs font-semibold mb-2">Moderator tools</div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="secondary">Pin</Button>
            <Button size="sm" variant="secondary">Lock</Button>
            <Button size="sm" variant="secondary">Mark official</Button>
            <Button size="sm" variant="secondary">Mark rumor</Button>
            <Button size="sm" variant="destructive">Hide</Button>
          </div>
        </div>
      )}

      <h2 className="font-display text-xl font-bold mt-8 mb-3">{posts.length} replies</h2>
      <div className="space-y-3">
        {posts.map((p) => {
          const u = demoData.users.find((x) => x.id === p.authorId)!;
          return (
            <div key={p.id} className="p-3 rounded-xl bg-card border border-border">
              <div className="flex items-center gap-2 text-sm mb-1">
                <img src={u.avatar} alt="" className="size-7 rounded-full" />
                <span className="font-medium">{u.displayName}</span>
                <span className="text-xs text-muted-foreground">· {new Date(p.createdAt).toLocaleTimeString()}</span>
              </div>
              <p className="text-sm">{p.body}</p>
              <div className="mt-2 flex gap-2 text-xs text-muted-foreground">
                <button>♥ {p.reactions}</button>
                <button>Reply</button>
                <button>Quote</button>
              </div>
            </div>
          );
        })}
      </div>

      <div className="mt-8">
        {user ? (
          <form onSubmit={(e) => e.preventDefault()}>
            <textarea className="w-full p-3 rounded-md bg-background border border-input min-h-24" placeholder="Write a reply..." />
            <div className="mt-2 flex justify-end gap-2"><Button variant="ghost">Spoiler tag</Button><Button>Post reply</Button></div>
          </form>
        ) : (
          <div className="p-4 rounded-md bg-muted/40 flex items-center justify-between"><span className="text-sm">Log in to reply.</span><Button size="sm" onClick={() => show("Log in to reply")}>Log in</Button></div>
        )}
      </div>
    </div>
  );
}
