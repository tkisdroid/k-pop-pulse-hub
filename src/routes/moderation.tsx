import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";
import { useAuth } from "@/hooks/useAuth";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/moderation")({
  head: () => buildHead({ title: "Moderation", canonical: "/moderation" }),
  component: Mod,
});

function Mod() {
  const { user } = useAuth();
  const nav = useNavigate();
  useEffect(() => { if (user && user.role !== "admin" && user.role !== "moderator") nav({ to: "/" }); }, [user, nav]);
  if (!user) return <div className="p-8 text-center"><p className="mb-4">Moderator access required.</p><Link to="/login" className="text-primary">Log in</Link></div>;
  if (user.role !== "admin" && user.role !== "moderator") return null;
  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <h1 className="font-display text-3xl font-bold mb-4">Moderation dashboard</h1>
      <div className="grid gap-4 md:grid-cols-2">
        <section className="p-4 rounded-xl bg-card border border-border"><h2 className="font-bold mb-2">Reports queue</h2><p className="text-sm text-muted-foreground">No open reports.</p></section>
        <section className="p-4 rounded-xl bg-card border border-border"><h2 className="font-bold mb-2">Hidden posts</h2><p className="text-sm text-muted-foreground">No hidden posts.</p></section>
        <section className="p-4 rounded-xl bg-card border border-border"><h2 className="font-bold mb-2">Locked threads</h2><p className="text-sm text-muted-foreground">No locked threads.</p></section>
        <section className="p-4 rounded-xl bg-card border border-border"><h2 className="font-bold mb-2">User warnings</h2><Button size="sm">Issue warning</Button></section>
      </div>
      <div className="mt-6 p-4 rounded-xl bg-card border border-border">
        <h2 className="font-bold mb-2">Moderation log</h2>
        <p className="text-sm text-muted-foreground">No recent actions.</p>
      </div>
    </div>
  );
}
