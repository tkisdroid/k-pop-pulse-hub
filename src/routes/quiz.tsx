import { useEffect, useMemo, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Crown, Flame, Medal, TrendingUp, Trophy } from "lucide-react";
import { buildHead } from "@/components/layout/seo";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { DailyQuizWidget } from "@/components/quiz/DailyQuizWidget";
import { quiz, type QuizHistoryEntry } from "@/services/quiz";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/quiz")({
  head: () =>
    buildHead({
      title: "Daily K-pop Quiz · My history & leaderboard",
      description: "Play today's K-pop quiz, track your streak, and see how fans rank globally.",
      canonical: "/quiz",
    }),
  component: QuizPage,
});

const FANDOM_NICKS = [
  "ARMYforever",
  "BLINKbias",
  "ONCEinabluemoon",
  "MOAvibes",
  "MIDZYqueen",
  "STAYday",
  "ATINYwave",
  "ENGENEgo",
  "CARATfine",
  "NCTzenLove",
  "BUDDYfan",
  "MYstical",
  "FEARLESSone",
  "BUNNIESxoxo",
  "TWSpark",
  "Ribbit99",
  "DalpangE",
  "MelodySeoul",
  "BiasWrecker",
  "MaknaeLine",
  "BoraHae",
  "VisualKing",
  "K-popDad",
  "StanLife",
  "VocalQueen",
];

interface RankRow {
  name: string;
  score: number;
  total: number;
  streak: number;
  isYou?: boolean;
}

function hashStr(s: string) {
  let h = 2166136261;
  for (let i = 0; i < s.length; i++) {
    h ^= s.charCodeAt(i);
    h = (h * 16777619) >>> 0;
  }
  return h >>> 0;
}

function seededLeaderboard(date: string, total: number, you?: RankRow): RankRow[] {
  const rows: RankRow[] = FANDOM_NICKS.map((name) => {
    const h = hashStr(`${date}:${name}`);
    // bias toward 3-5 correct to feel realistic
    const r = (h % 1000) / 1000;
    const score = r < 0.55 ? total : r < 0.8 ? total - 1 : r < 0.93 ? total - 2 : h % (total + 1);
    const streak = (h >> 7) % 21;
    return { name, score, total, streak };
  });
  if (you) rows.push(you);
  return rows
    .sort((a, b) => b.score - a.score || b.streak - a.streak || a.name.localeCompare(b.name))
    .slice(0, 25);
}

function computeStreak(history: QuizHistoryEntry[]): number {
  if (!history.length) return 0;
  const sorted = [...history].sort((a, b) => (a.date < b.date ? 1 : -1));
  let streak = 0;
  const today = new Date();
  for (let i = 0; ; i++) {
    const d = new Date(
      Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate() - i),
    );
    const key = `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, "0")}-${String(d.getUTCDate()).padStart(2, "0")}`;
    const found = sorted.find((h) => h.date === key);
    if (!found) break;
    streak++;
  }
  return streak;
}

function QuizPage() {
  const [history, setHistory] = useState<QuizHistoryEntry[]>(() => quiz.history());
  const [state, setState] = useState(() => quiz.state());

  useEffect(() => {
    const unsub = quiz.subscribe(() => {
      setHistory(quiz.history());
      setState(quiz.state());
    });
    return () => {
      unsub();
    };
  }, []);

  const total = quiz.questionsPerDay;
  const today = quiz.todayKey();

  const totalGames = history.length;
  const totalCorrect = history.reduce((acc, h) => acc + h.score, 0);
  const accuracy = totalGames ? Math.round((totalCorrect / (totalGames * total)) * 100) : 0;
  const bestScore = history.reduce((m, h) => Math.max(m, h.score), 0);
  const streak = computeStreak(history);

  const yourRow: RankRow | undefined =
    state.completedAt && state.score !== undefined
      ? { name: "You", score: state.score, total, streak, isYou: true }
      : undefined;
  const leaderboard = seededLeaderboard(today, total, yourRow);
  const yourRank = leaderboard.findIndex((r) => r.isYou) + 1;

  // 30-day calendar grid
  const calendarDays = useMemo(() => {
    const out: { key: string; entry?: QuizHistoryEntry; label: string }[] = [];
    const t = new Date();
    for (let i = 29; i >= 0; i--) {
      const d = new Date(Date.UTC(t.getUTCFullYear(), t.getUTCMonth(), t.getUTCDate() - i));
      const key = `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, "0")}-${String(d.getUTCDate()).padStart(2, "0")}`;
      out.push({
        key,
        entry: history.find((h) => h.date === key),
        label: `${d.getUTCMonth() + 1}/${d.getUTCDate()}`,
      });
    }
    return out;
  }, [history]);

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 space-y-10">
      <header className="space-y-2">
        <Link to="/" className="text-xs text-muted-foreground hover:text-foreground">
          ← Home
        </Link>
        <h1 className="font-display text-3xl md:text-4xl font-bold">Daily K-pop Quiz</h1>
        <p className="text-muted-foreground max-w-2xl">
          A new 5-question challenge drops every day at 00:00 UTC. Play, track your streak, and
          climb the global leaderboard.
        </p>
      </header>

      <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          {/* Stats */}
          <div className="grid gap-3 sm:grid-cols-4">
            <StatCard
              icon={<Flame className="size-4" />}
              label="Streak"
              value={`${streak} day${streak === 1 ? "" : "s"}`}
            />
            <StatCard
              icon={<Trophy className="size-4" />}
              label="Best score"
              value={`${bestScore}/${total}`}
            />
            <StatCard
              icon={<TrendingUp className="size-4" />}
              label="Accuracy"
              value={`${accuracy}%`}
            />
            <StatCard
              icon={<Medal className="size-4" />}
              label="Games played"
              value={`${totalGames}`}
            />
          </div>

          {/* History calendar */}
          <div>
            <SectionHeader eyebrow="Last 30 days" title="Your quiz calendar" />
            <div className="rounded-2xl border border-border bg-card p-4">
              <div className="grid grid-cols-10 sm:grid-cols-15 gap-1.5">
                {calendarDays.map((d) => {
                  const score = d.entry?.score;
                  const intensity =
                    score === undefined
                      ? 0
                      : score === total
                        ? 5
                        : Math.max(1, Math.round((score / total) * 4));
                  return (
                    <div
                      key={d.key}
                      title={`${d.key}${score !== undefined ? ` · ${score}/${total}` : " · not played"}`}
                      className={cn(
                        "aspect-square rounded-md border border-border/60 flex items-end justify-end p-1 text-[9px] font-semibold",
                        intensity === 0 && "bg-muted/40 text-muted-foreground",
                        intensity === 1 && "bg-primary/10 text-primary",
                        intensity === 2 && "bg-primary/25 text-primary-foreground/80",
                        intensity === 3 && "bg-primary/45 text-primary-foreground",
                        intensity === 4 && "bg-primary/70 text-primary-foreground",
                        intensity === 5 && "bg-primary text-primary-foreground",
                      )}
                    >
                      {score !== undefined ? score : ""}
                    </div>
                  );
                })}
              </div>
              <div className="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground">
                <span>Less</span>
                {[0, 1, 2, 3, 4, 5].map((i) => (
                  <span
                    key={i}
                    className={cn(
                      "size-3 rounded-sm border border-border/60",
                      i === 0 && "bg-muted/40",
                      i === 1 && "bg-primary/10",
                      i === 2 && "bg-primary/25",
                      i === 3 && "bg-primary/45",
                      i === 4 && "bg-primary/70",
                      i === 5 && "bg-primary",
                    )}
                  />
                ))}
                <span>Perfect</span>
              </div>
            </div>
          </div>

          {/* History list */}
          <div>
            <SectionHeader eyebrow="Recent games" title="History" />
            {history.length === 0 ? (
              <div className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                No games yet. Play today's quiz to start your streak!
              </div>
            ) : (
              <ul className="grid gap-2">
                {history.map((h) => (
                  <li
                    key={h.date}
                    className="flex items-center justify-between gap-4 px-4 py-3 rounded-xl bg-card border border-border"
                  >
                    <div>
                      <div className="font-medium">{h.date}</div>
                      <div className="text-xs text-muted-foreground">
                        {h.score === h.total
                          ? "Perfect score"
                          : h.score >= Math.ceil(h.total / 2)
                            ? "Nice work"
                            : "Keep practicing"}
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <div className="w-32 h-1.5 rounded-full bg-muted overflow-hidden">
                        <div
                          className="h-full bg-primary"
                          style={{ width: `${(h.score / h.total) * 100}%` }}
                        />
                      </div>
                      <span className="font-display font-bold text-lg w-12 text-right">
                        {h.score}/{h.total}
                      </span>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        {/* Sidebar */}
        <aside className="space-y-6">
          <DailyQuizWidget />

          <div>
            <SectionHeader eyebrow={`Today · ${today}`} title="Daily leaderboard" />
            <div className="rounded-2xl border border-border bg-card overflow-hidden">
              {!yourRow && (
                <div className="px-4 py-2 text-xs text-muted-foreground bg-muted/30 border-b border-border">
                  Finish today's quiz to appear on the board.
                </div>
              )}
              {yourRow && yourRank > 0 && (
                <div className="px-4 py-2 text-xs bg-primary/5 border-b border-border flex items-center gap-2">
                  <Crown className="size-3.5 text-primary" />
                  <span>
                    Your rank: <span className="font-semibold text-foreground">#{yourRank}</span> ·{" "}
                    {yourRow.score}/{total}
                  </span>
                </div>
              )}
              <ol className="divide-y divide-border">
                {leaderboard.map((row, i) => {
                  const rank = i + 1;
                  return (
                    <li
                      key={`${row.name}-${i}`}
                      className={cn(
                        "flex items-center gap-3 px-4 py-2.5 text-sm",
                        row.isYou && "bg-primary/10",
                      )}
                    >
                      <span
                        className={cn(
                          "size-7 grid place-items-center rounded-md font-display font-bold text-xs shrink-0",
                          rank === 1 && "bg-amber-400/20 text-amber-600 dark:text-amber-300",
                          rank === 2 && "bg-zinc-400/20 text-zinc-600 dark:text-zinc-300",
                          rank === 3 && "bg-orange-500/20 text-orange-600 dark:text-orange-300",
                          rank > 3 && "bg-muted text-muted-foreground",
                        )}
                      >
                        {rank}
                      </span>
                      <span
                        className={cn("flex-1 truncate font-medium", row.isYou && "text-primary")}
                      >
                        {row.name}
                        {row.isYou && (
                          <span className="ml-1 text-[10px] uppercase tracking-wide">you</span>
                        )}
                      </span>
                      {row.streak > 0 && (
                        <span className="inline-flex items-center gap-0.5 text-[11px] text-muted-foreground">
                          <Flame className="size-3" />
                          {row.streak}
                        </span>
                      )}
                      <span className="font-display font-bold w-10 text-right">
                        {row.score}/{row.total}
                      </span>
                    </li>
                  );
                })}
              </ol>
            </div>
            <p className="mt-2 text-[11px] text-muted-foreground">
              Rankings reset every day at 00:00 UTC. Connect a backend to enable global accounts.
            </p>
          </div>
        </aside>
      </section>
    </div>
  );
}

function StatCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className="rounded-xl border border-border bg-card p-3">
      <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
        {icon}
        {label}
      </div>
      <div className="font-display font-bold text-xl mt-0.5">{value}</div>
    </div>
  );
}
