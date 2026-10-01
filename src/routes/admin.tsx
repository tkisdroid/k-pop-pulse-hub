import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect } from "react";
import { useAuth } from "@/hooks/useAuth";
import { buildHead } from "@/components/layout/seo";
import { useRuntimeData } from "@/services/cms/runtimeData";

export const Route = createFileRoute("/admin")({
  head: () => buildHead({ title: "Admin", canonical: "/admin" }),
  component: Admin,
});

function Admin() {
  const { user } = useAuth();
  const { data, isLoading, error } = useRuntimeData();
  const nav = useNavigate();
  useEffect(() => { if (user && user.role !== "admin") nav({ to: "/" }); }, [user, nav]);
  if (!user) return <div className="p-8 text-center"><p className="mb-4">Admin access required.</p><Link to="/login" className="text-primary">Log in as admin</Link></div>;
  if (user.role !== "admin") return null;
  if (isLoading) return <div className="p-8 text-muted-foreground">Loading WordPress content…</div>;
  if (error) return <div className="p-8 text-destructive">{error}</div>;

  const stats = [
    ["Articles", data.articles.length], ["Artists", data.artists.length], ["Threads", data.threads.length], ["Videos", data.videos.length],
    ["Polls", data.polls.length], ["Comebacks", data.comebacks.length], ["Community posts", data.community.length], ["Members", data.members.length],
  ] as const;

  return (
    <div className="mx-auto min-h-[calc(100vh-200px)] max-w-7xl px-4 py-8">
      <main>
        <h1 className="font-display text-2xl font-bold mb-4">Overview</h1>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {stats.map(([l, v]) => <div key={l} className="p-4 rounded-xl bg-card border border-border"><div className="text-xs text-muted-foreground">{l}</div><div className="text-2xl font-bold">{v}</div></div>)}
        </div>
        <div className="mt-6 p-4 rounded-xl bg-card border border-border">
          <h3 className="font-display font-bold mb-2">WordPress sync</h3>
          <p className="text-sm text-muted-foreground">Content is synchronized from the WordPress REST API. Use the K-pop Pulse Hub menu in WordPress administration for full management.</p>
          {typeof window !== "undefined" && window.kpopblogConfig?.adminUrl && <div className="mt-3 flex flex-wrap gap-4 text-sm text-primary">
            <a href={window.kpopblogConfig.adminUrl}>Open WordPress administration</a>
            <a href={window.kpopblogConfig.adminUrl.replace("page=kpopblog-admin", "page=kpopblog-analytics")}>접속자 통계 보기</a>
          </div>}
        </div>
        <div className="mt-4 p-4 rounded-xl bg-card border border-border">
          <h3 className="font-display font-bold mb-2">Recent articles</h3>
          <div className="grid gap-2">
            {data.articles.slice(0, 5).map((a) => <Link key={a.id} to="/news/$slug" params={{ slug: a.slug }} className="text-sm hover:text-primary">{a.title}</Link>)}
          </div>
        </div>
      </main>
    </div>
  );
}
