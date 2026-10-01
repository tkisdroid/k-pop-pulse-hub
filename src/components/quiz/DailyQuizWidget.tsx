import { useEffect, useState } from "react";
import { Link } from "@tanstack/react-router";
import { Brain, Check, RotateCcw, Sparkles, Trophy, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { quiz, type QuizQuestion, type QuizState } from "@/services/quiz";

interface Props {
  compact?: boolean;
  className?: string;
}

export function DailyQuizWidget({ compact = false, className }: Props) {
  const [state, setState] = useState<QuizState>(() => quiz.state());
  const [reveal, setReveal] = useState<number | null>(null);
  const questions = quiz.todaysQuestions();

  useEffect(() => {
    const unsub = quiz.subscribe(setState);
    return () => {
      unsub();
    };
  }, []);

  const total = questions.length;
  const idx = Math.min(state.currentIndex, total - 1);
  const current: QuizQuestion | undefined = questions[idx];
  const isComplete = Boolean(state.completedAt);
  const answered = reveal !== null;

  function pick(choice: number) {
    if (answered || isComplete || !current) return;
    setReveal(choice);
    window.setTimeout(() => {
      quiz.answer(idx, choice);
      setReveal(null);
    }, 900);
  }

  function restart() {
    setReveal(null);
    quiz.reset();
  }

  const progressPct = Math.round((state.answers.filter((a) => a !== null).length / total) * 100);

  return (
    <div className={cn("rounded-2xl border border-border bg-card overflow-hidden", className)}>
      <div className="p-4 bg-gradient-to-br from-primary/10 via-card to-card border-b border-border">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <div className="size-8 grid place-items-center rounded-lg bg-primary/15 text-primary">
              <Brain className="size-4" />
            </div>
            <div>
              <Link
                to="/quiz"
                className="text-[11px] uppercase tracking-wider text-primary font-semibold hover:underline"
              >
                Daily K-pop Quiz →
              </Link>
              <div className="text-xs text-muted-foreground">
                {state.date} · {total} questions
              </div>
            </div>
          </div>
          {isComplete && (
            <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-primary/10 text-primary">
              <Trophy className="size-3" /> {state.score}/{total}
            </span>
          )}
        </div>
        <div className="mt-3 h-1.5 rounded-full bg-muted overflow-hidden">
          <div className="h-full bg-primary transition-all" style={{ width: `${progressPct}%` }} />
        </div>
      </div>

      <div className="p-4">
        {isComplete ? (
          <CompleteView state={state} questions={questions} onRestart={restart} compact={compact} />
        ) : current ? (
          <div>
            <div className="flex items-center justify-between text-xs text-muted-foreground mb-2">
              <span>
                Question {idx + 1} / {total}
              </span>
              {current.category && (
                <span className="px-2 py-0.5 rounded-full bg-accent text-foreground/80">
                  {current.category}
                </span>
              )}
            </div>
            <h3 className="font-semibold leading-snug mb-3">{current.question}</h3>
            <div className="grid gap-2">
              {current.options.map((opt, i) => {
                const isPick = reveal === i;
                const isCorrect = answered && i === current.answer;
                const isWrongPick = answered && isPick && i !== current.answer;
                return (
                  <button
                    key={i}
                    onClick={() => pick(i)}
                    disabled={answered}
                    className={cn(
                      "text-left px-3 py-2 rounded-lg border text-sm transition-colors",
                      "border-border bg-background hover:border-primary/50 hover:bg-accent",
                      isCorrect &&
                        "border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                      isWrongPick && "border-destructive bg-destructive/10 text-destructive",
                      answered && !isCorrect && !isWrongPick && "opacity-60",
                    )}
                  >
                    <span className="inline-flex items-center gap-2">
                      <span className="size-5 grid place-items-center rounded-md bg-muted text-[11px] font-semibold">
                        {String.fromCharCode(65 + i)}
                      </span>
                      <span className="flex-1">{opt}</span>
                      {isCorrect && <Check className="size-4" />}
                      {isWrongPick && <X className="size-4" />}
                    </span>
                  </button>
                );
              })}
            </div>
            <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
              <span className="inline-flex items-center gap-1">
                <Sparkles className="size-3" /> +4 pts per correct answer
              </span>
              <button
                onClick={restart}
                className="inline-flex items-center gap-1 hover:text-foreground"
                title="Reset today's quiz"
              >
                <RotateCcw className="size-3" /> Reset
              </button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

function CompleteView({
  state,
  questions,
  onRestart,
  compact,
}: {
  state: QuizState;
  questions: QuizQuestion[];
  onRestart: () => void;
  compact: boolean;
}) {
  const score = state.score ?? 0;
  const total = questions.length;
  const perfect = score === total;
  return (
    <div>
      <div className="text-center py-2">
        <div className="text-4xl font-display font-bold text-gradient">
          {score}/{total}
        </div>
        <p className="text-sm text-muted-foreground mt-1">
          {perfect
            ? "Perfect score! +10 bonus points 🎉"
            : score >= Math.ceil(total / 2)
              ? "Nice work, K-pop fan!"
              : "Come back tomorrow for a new set."}
        </p>
      </div>
      {!compact && (
        <ul className="mt-3 space-y-2 text-sm max-h-56 overflow-y-auto pr-1">
          {questions.map((q, i) => {
            const correct = state.answers[i] === q.answer;
            return (
              <li
                key={q.id}
                className={cn(
                  "rounded-lg border p-2",
                  correct
                    ? "border-emerald-500/40 bg-emerald-500/5"
                    : "border-destructive/40 bg-destructive/5",
                )}
              >
                <div className="flex items-start gap-2">
                  {correct ? (
                    <Check className="size-4 mt-0.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                  ) : (
                    <X className="size-4 mt-0.5 text-destructive shrink-0" />
                  )}
                  <div className="min-w-0">
                    <div className="font-medium leading-snug">{q.question}</div>
                    <div className="text-xs text-muted-foreground mt-0.5">
                      Answer: <span className="text-foreground">{q.options[q.answer]}</span>
                    </div>
                    {q.explanation && (
                      <div className="text-xs text-muted-foreground mt-0.5">{q.explanation}</div>
                    )}
                  </div>
                </div>
              </li>
            );
          })}
        </ul>
      )}
      <div className="mt-4 flex items-center justify-between">
        <span className="text-xs text-muted-foreground">New quiz daily at 00:00 UTC</span>
        <Button size="sm" variant="outline" onClick={onRestart}>
          <RotateCcw className="size-3" /> Play again
        </Button>
      </div>
    </div>
  );
}
