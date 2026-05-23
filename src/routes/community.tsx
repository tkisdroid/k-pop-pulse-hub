import { createFileRoute } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";

export const Route = createFileRoute("/community")({
  head: () => buildHead({ title: "Community Wall", canonical: "/community" }),
  component: Community,
});

function Community() {
  const { user } = useAuth();
  const { show } = useAuthModal();
  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <SectionHeader eyebrow="Community" title="The Wall" subtitle="Quick fan thoughts from around the world." />
      <AdSlot slotId="community-top" variant="leaderboard" />
      <div className="mb-6 p-4 rounded-xl bg-card border border-border">
        {user ? (
          <form onSubmit={(e) => e.preventDefault()}>
            <textarea placeholder="Share a thought..." className="w-full p-3 rounded-md bg-background border border-input min-h-20" />
            <div className="flex justify-end mt-2"><Button>Post</Button></div>
          </form>
        ) : (
          <div className="flex items-center justify-between"><span className="text-sm">Log in to post.</span><Button size="sm" onClick={() => show()}>Log in</Button></div>
        )}
      </div>
      <div className="grid gap-3">
        {demoData.community.map((c) => {
          const u = demoData.users.find((x) => x.id === c.authorId)!;
          return (
            <div key={c.id} className="p-4 rounded-xl bg-card border border-border">
              <div className="flex items-center gap-2 mb-2">
                <img src={u.avatar} alt="" className="size-7 rounded-full" />
                <div className="text-sm"><span className="font-semibold">{u.displayName}</span> <span className="text-xs text-muted-foreground">· {c.language.toUpperCase()}</span></div>
              </div>
              <p className="text-sm">{c.body}</p>
              <div className="mt-2 flex gap-3 text-xs text-muted-foreground">
                <button>♥ {c.reactions}</button><button>Translate</button><button className="ml-auto hover:text-destructive">Report</button>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
