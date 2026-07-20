import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useState } from "react";
import { useAuth } from "@/hooks/useAuth";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { communityProvider } from "@/services/community";
import type { Report } from "@/types";

export const Route = createFileRoute("/moderation")({
  head: () => buildHead({ title: "Moderation", canonical: "/moderation" }),
  component: Moderation,
});

function Moderation() {
  const { user, loading: authLoading } = useAuth();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const canModerate = Boolean(user?.capabilities?.moderateCommunity);

  const load = useCallback(async () => {
    if (!canModerate) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      setReports((await communityProvider.listReports()).items);
    } catch (loadError) {
      setError((loadError as Error).message);
    } finally {
      setLoading(false);
    }
  }, [canModerate]);

  useEffect(() => {
    void load();
  }, [load]);

  async function close(report: Report, action: "resolved" | "dismissed") {
    const note = window.prompt("Optional moderation note:") ?? "";
    try {
      await communityProvider.resolveReport(report.id, action, note);
      setReports((current) => current.filter((item) => item.id !== report.id));
    } catch (closeError) {
      setError((closeError as Error).message);
    }
  }

  if (authLoading)
    return <div className="p-8 text-center text-muted-foreground">Checking access…</div>;
  if (!user)
    return (
      <div className="p-8 text-center">
        <p className="mb-4">Moderator access required.</p>
        <Link to="/login" className="text-primary">
          Log in
        </Link>
      </div>
    );
  if (!canModerate)
    return (
      <div className="p-8 text-center">
        <h1 className="text-2xl font-bold">Access denied</h1>
        <p className="mt-2 text-muted-foreground">
          Your WordPress account does not have community moderation permission.
        </p>
      </div>
    );

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <h1 className="mb-4 font-display text-3xl font-bold">Moderation dashboard</h1>
      {error && (
        <div
          className="mb-4 rounded-md border border-destructive/40 p-3 text-sm text-destructive"
          role="alert"
        >
          {error}{" "}
          <Button size="sm" variant="ghost" onClick={() => void load()}>
            Retry
          </Button>
        </div>
      )}
      {loading ? (
        <p className="py-12 text-center text-muted-foreground">Loading reports…</p>
      ) : (
        <section className="rounded-xl border border-border bg-card p-4">
          <h2 className="mb-3 font-bold">Open reports ({reports.length})</h2>
          {reports.length === 0 ? (
            <p className="text-sm text-muted-foreground">No open reports.</p>
          ) : (
            <div className="space-y-3">
              {reports.map((report) => (
                <article key={report.id} className="rounded-md border border-border p-3">
                  <div className="text-sm font-medium">
                    {report.targetType} #{report.targetId}
                  </div>
                  <p className="my-2 whitespace-pre-wrap text-sm">{report.reason}</p>
                  <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span>Reporter #{report.reporterId}</span>
                    <span>{new Date(report.createdAt).toLocaleString()}</span>
                    <Button
                      size="sm"
                      className="ml-auto"
                      onClick={() => void close(report, "resolved")}
                    >
                      Resolve
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => void close(report, "dismissed")}
                    >
                      Dismiss
                    </Button>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
      )}
    </div>
  );
}
