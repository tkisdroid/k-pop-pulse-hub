import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";
import { AdSlot } from "@/components/ads/AdSlot";
import { Button } from "@/components/ui/button";
import { NotifyButton } from "@/components/notifications/NotifyButton";


export const Route = createFileRoute("/comebacks")({
  head: () => buildHead({ title: "Comeback Calendar", description: "Upcoming K-pop releases, debuts, tours and birthdays.", canonical: "/comebacks" }),
  component: Comebacks,
});

const TABS = ["Upcoming", "Trending", "New Releases", "All"] as const;

function Comebacks() {
  const { data, isLoading, error } = useRuntimeData();
  const [tab, setTab] = useState<typeof TABS[number]>("Upcoming");
  const [view, setView] = useState<"list" | "calendar">("list");
  if (isLoading) return <p className="py-20 text-center text-muted-foreground">Loading schedules…</p>;
  if (error) return <p className="py-20 text-center text-destructive" role="alert">{error}</p>;
  const events = [...data.comebacks].sort((a, b) => +new Date(a.releaseAt) - +new Date(b.releaseAt));
  const filtered = tab === "All" ? events : tab === "Upcoming" ? events.filter((e) => +new Date(e.releaseAt) > Date.now()) : events;

  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <SectionHeader eyebrow="Calendar" title="Comeback Schedule" subtitle="Times shown in your local timezone." />
      <AdSlot slotId="comebacks-top" variant="leaderboard" />
      <div className="flex flex-wrap gap-2 mb-4 justify-between">
        <div className="flex gap-2">{TABS.map((t) => <button key={t} onClick={() => setTab(t)} className={`px-3 py-1.5 rounded-full text-sm ${tab === t ? "bg-primary text-primary-foreground" : "bg-accent"}`}>{t}</button>)}</div>
        <div className="flex gap-2"><button onClick={() => setView("list")} className={`px-3 py-1.5 rounded-md text-sm ${view === "list" ? "bg-primary text-primary-foreground" : "bg-accent"}`}>List</button><button onClick={() => setView("calendar")} className={`px-3 py-1.5 rounded-md text-sm ${view === "calendar" ? "bg-primary text-primary-foreground" : "bg-accent"}`}>Calendar</button></div>
      </div>
      {view === "list" ? (
        <div className="grid gap-3">
          {filtered.length === 0 && <p className="py-16 text-center text-muted-foreground">No matching schedules yet.</p>}
          {filtered.map((c) => {
            const a = data.artists.find((x) => x.id === c.artistId || x.slug === c.artistId);
            const days = Math.ceil((+new Date(c.releaseAt) - Date.now()) / 86400000);
            return (
              <div key={c.id} className="flex items-center gap-4 p-4 rounded-xl bg-card border border-border">
                <div className="size-16 rounded-lg overflow-hidden shrink-0"><img src={c.image} alt={c.title} className="size-full object-cover" /></div>
                <div className="flex-1">
                  <div className="text-xs uppercase text-primary">{c.type}</div>
                  <div className="font-semibold">{c.title}</div>
                  <div className="text-xs text-muted-foreground">{a?.name ?? c.artistId} · {new Date(c.releaseAt).toLocaleString()}</div>
                </div>
                <div className="text-right space-y-1">
                  <div className="font-display text-2xl text-gradient font-bold">{days > 0 ? `${days}d` : "LIVE"}</div>
                  <div className="flex gap-1 justify-end">
                    <NotifyButton
                      reminder={{
                        id: `comeback:${c.id}`,
                        kind: "comeback",
                        title: `🎵 ${c.title}`,
                        body: `${a?.name ?? c.artistId} ${c.type} drops in 15 minutes`,
                        url: "/comebacks",
                        icon: c.image,
                        fireAt: c.releaseAt,
                        leadMinutes: 15,
                      }}
                    />
                    <Button size="sm" variant="secondary">Follow</Button>
                  </div>
                </div>

              </div>
            );
          })}
        </div>
      ) : (
        <div className="grid grid-cols-7 gap-1 text-xs">
          {Array.from({ length: 35 }).map((_, i) => {
            const d = new Date(); d.setDate(d.getDate() + i - 2);
            const ev = filtered.find((e) => new Date(e.releaseAt).toDateString() === d.toDateString());
            return (
              <div key={i} className={`min-h-20 p-2 rounded-md border ${ev ? "border-primary/60 bg-primary/10" : "border-border bg-card"}`}>
                <div className="font-semibold">{d.getDate()}</div>
                {ev && <div className="text-[10px] mt-1 line-clamp-2 text-primary">{ev.title}</div>}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
