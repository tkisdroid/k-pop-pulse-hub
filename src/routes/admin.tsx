import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";
import { useAuth } from "@/hooks/useAuth";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/admin")({
  head: () => buildHead({ title: "Admin", canonical: "/admin" }),
  component: Admin,
});

const SECTIONS = [
  ["Overview", "Articles", "WordPress sync", "Forum moderation", "Reported posts", "Users", "Artist database", "Comeback events", "Translation queue", "Polls", "Badges", "Site settings", "Ad slots", "Newsletter", "Audit log"],
];

function Admin() {
  const { user } = useAuth();
  const nav = useNavigate();
  useEffect(() => { if (user && user.role !== "admin") nav({ to: "/" }); }, [user, nav]);
  if (!user) return <div className="p-8 text-center"><p className="mb-4">Admin access required.</p><Link to="/login" className="text-primary">Log in as admin</Link></div>;
  if (user.role !== "admin") return null;

  const stats = [
    ["Articles", demoData.articles.length], ["Artists", demoData.artists.length], ["Threads", demoData.threads.length], ["Users", demoData.users.length],
    ["Polls", demoData.polls.length], ["Comebacks", demoData.comebacks.length], ["Reports", 0], ["Pending translations", 0],
  ] as const;

  return (
    <div className="grid lg:grid-cols-[220px_1fr] min-h-[calc(100vh-200px)]">
      <aside className="border-r border-border bg-card/50 p-4">
        <h2 className="font-display font-bold mb-3">Admin</h2>
        <nav className="space-y-1 text-sm">{SECTIONS[0].map((s) => <button key={s} className="block w-full text-left px-2 py-1.5 rounded hover:bg-accent">{s}</button>)}</nav>
      </aside>
      <main className="p-6">
        <h1 className="font-display text-2xl font-bold mb-4">Overview</h1>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {stats.map(([l, v]) => <div key={l} className="p-4 rounded-xl bg-card border border-border"><div className="text-xs text-muted-foreground">{l}</div><div className="text-2xl font-bold">{v}</div></div>)}
        </div>
        <div className="mt-6 p-4 rounded-xl bg-card border border-border">
          <h3 className="font-display font-bold mb-2">WordPress sync</h3>
          <p className="text-sm text-muted-foreground">Not configured. Set <code className="text-xs bg-muted px-1 rounded">VITE_WORDPRESS_API_URL</code> to enable sync.</p>
        </div>
        <div className="mt-4 p-4 rounded-xl bg-card border border-border">
          <h3 className="font-display font-bold mb-2">Recent articles</h3>
          <div className="grid gap-2">
            {demoData.articles.slice(0, 5).map((a) => <Link key={a.id} to="/news/$slug" params={{ slug: a.slug }} className="text-sm hover:text-primary">{a.title}</Link>)}
          </div>
        </div>
      </main>
    </div>
  );
}
