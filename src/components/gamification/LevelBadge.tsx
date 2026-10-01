import { useEffect, useState } from "react";
import { gamification, type GamificationStats } from "@/services/gamification";

export function LevelBadge({ compact = false }: { compact?: boolean }) {
  const [s, setS] = useState<GamificationStats>(() => gamification.stats());
  useEffect(() => {
    const unsub = gamification.subscribe(setS);
    return () => {
      unsub();
    };
  }, []);
  const { current, next, pct } = gamification.progress(s.points);

  if (compact) {
    return (
      <span
        className="hidden md:inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-semibold border border-border bg-card"
        title={`${s.points} pts · ${current.title}`}
      >
        <span className="size-2 rounded-full" style={{ background: current.color }} />
        Lv {current.level}
      </span>
    );
  }

  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <div className="flex items-center justify-between mb-2">
        <div className="flex items-center gap-2">
          <span className="size-3 rounded-full" style={{ background: current.color }} />
          <span className="font-semibold">
            Lv {current.level} · {current.title}
          </span>
        </div>
        <span className="text-xs text-muted-foreground">{s.points} pts</span>
      </div>
      <div className="h-2 rounded-full bg-muted overflow-hidden">
        <div
          className="h-full rounded-full"
          style={{ width: `${pct}%`, background: current.color }}
        />
      </div>
      {next && (
        <div className="mt-1 text-[11px] text-muted-foreground">
          {next.minPoints - s.points} pts to {next.title}
        </div>
      )}
    </div>
  );
}
