/**
 * Daily K-pop quiz service.
 *
 * - Deterministic question rotation per UTC date (everyone sees the same set).
 * - Persists today's progress + completion in localStorage.
 * - Awards gamification points on completion.
 */
import { gamification } from "@/services/gamification";

export interface QuizQuestion {
  id: string;
  question: string;
  options: string[];
  answer: number; // index into options
  explanation?: string;
  category?: string;
}

export interface QuizState {
  date: string; // YYYY-MM-DD UTC
  questionIds: string[];
  answers: (number | null)[]; // user's choice per question
  currentIndex: number;
  completedAt?: string;
  score?: number;
}

const QUESTIONS: QuizQuestion[] = [
  { id: "q1", category: "Groups", question: "Which group released 'Dynamite' in 2020?", options: ["BTS", "EXO", "NCT", "Stray Kids"], answer: 0, explanation: "BTS released their first all-English single 'Dynamite' in August 2020." },
  { id: "q2", category: "Groups", question: "BLACKPINK debuted in what year?", options: ["2014", "2015", "2016", "2017"], answer: 2, explanation: "BLACKPINK debuted under YG Entertainment in August 2016." },
  { id: "q3", category: "Members", question: "Who is the leader of TWICE?", options: ["Nayeon", "Jihyo", "Mina", "Sana"], answer: 1, explanation: "Jihyo has been the leader of TWICE since debut." },
  { id: "q4", category: "Agencies", question: "Which agency manages NewJeans?", options: ["JYP", "SM", "ADOR (HYBE)", "YG"], answer: 2, explanation: "NewJeans is under ADOR, a sub-label of HYBE." },
  { id: "q5", category: "Songs", question: "Which song is by aespa?", options: ["Next Level", "Cupid", "Ditto", "Tomboy"], answer: 0, explanation: "'Next Level' is one of aespa's signature hits." },
  { id: "q6", category: "History", question: "Which group is known as the 'Nation's Girl Group' from the 2nd generation?", options: ["Girls' Generation", "f(x)", "2NE1", "T-ara"], answer: 0, explanation: "Girls' Generation (SNSD) earned the 'Nation's Girl Group' title." },
  { id: "q7", category: "Members", question: "Which BTS member was born in 1992?", options: ["RM", "Jin", "Suga", "J-Hope"], answer: 1, explanation: "Jin is the oldest member of BTS, born December 4, 1992." },
  { id: "q8", category: "Groups", question: "Stray Kids belongs to which agency?", options: ["JYP", "HYBE", "SM", "Starship"], answer: 0, explanation: "Stray Kids debuted under JYP Entertainment in 2018." },
  { id: "q9", category: "Songs", question: "'Pink Venom' is a song by which group?", options: ["TWICE", "BLACKPINK", "ITZY", "IVE"], answer: 1, explanation: "'Pink Venom' was the pre-release single from BORN PINK (2022)." },
  { id: "q10", category: "Members", question: "Who is the youngest member (maknae) of BLACKPINK?", options: ["Jisoo", "Jennie", "Rosé", "Lisa"], answer: 3, explanation: "Lisa, born March 27, 1997, is the maknae of BLACKPINK." },
  { id: "q11", category: "Groups", question: "How many members does SEVENTEEN have?", options: ["11", "13", "15", "17"], answer: 1, explanation: "SEVENTEEN has 13 members across 3 units." },
  { id: "q12", category: "Songs", question: "Which song made IVE go viral globally in 2023?", options: ["Love Dive", "After Like", "I AM", "Eleven"], answer: 2, explanation: "'I AM' from the album I've IVE took the group to new heights." },
  { id: "q13", category: "Agencies", question: "Which is one of the 'Big 4' K-pop agencies?", options: ["Starship", "Cube", "HYBE", "FNC"], answer: 2, explanation: "The current Big 4 are typically SM, YG, JYP, and HYBE." },
  { id: "q14", category: "History", question: "What year did 'Gangnam Style' by PSY release?", options: ["2010", "2011", "2012", "2013"], answer: 2, explanation: "'Gangnam Style' was released in July 2012." },
  { id: "q15", category: "Members", question: "Karina is the leader of which group?", options: ["IVE", "aespa", "(G)I-DLE", "LE SSERAFIM"], answer: 1, explanation: "Karina leads aespa, debuted under SM in 2020." },
  { id: "q16", category: "Groups", question: "LE SSERAFIM debuted under which label?", options: ["ADOR", "Source Music (HYBE)", "JYP", "SM"], answer: 1, explanation: "LE SSERAFIM debuted under Source Music, a HYBE label, in 2022." },
  { id: "q17", category: "Songs", question: "'God's Menu' is by which group?", options: ["ATEEZ", "Stray Kids", "TXT", "ENHYPEN"], answer: 1, explanation: "'God's Menu' (神메뉴) is one of Stray Kids' biggest hits." },
  { id: "q18", category: "Fandoms", question: "What is the official fandom name of BTS?", options: ["MOA", "ARMY", "ONCE", "BLINK"], answer: 1, explanation: "BTS's fandom is called ARMY since 2013." },
  { id: "q19", category: "Fandoms", question: "TWICE's fandom is called?", options: ["ONCE", "BLINK", "MIDZY", "MY"], answer: 0, explanation: "TWICE fans are called ONCE." },
  { id: "q20", category: "Songs", question: "'Cupid' is a hit single by which group?", options: ["FIFTY FIFTY", "STAYC", "Kep1er", "NMIXX"], answer: 0, explanation: "FIFTY FIFTY's 'Cupid' became a global viral hit in 2023." },
];

const QUESTIONS_PER_DAY = 5;
const POINTS_PER_CORRECT = 4;
const POINTS_BONUS_PERFECT = 10;
const STATE_KEY = "kpopblog:quiz:state";
const HISTORY_KEY = "kpopblog:quiz:history";

function todayKey(): string {
  const d = new Date();
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, "0")}-${String(d.getUTCDate()).padStart(2, "0")}`;
}

function hashStr(s: string): number {
  let h = 2166136261;
  for (let i = 0; i < s.length; i++) {
    h ^= s.charCodeAt(i);
    h = (h * 16777619) >>> 0;
  }
  return h >>> 0;
}

function dailyQuestionIds(date: string): string[] {
  const seed = hashStr(date);
  const indexed = QUESTIONS.map((q, i) => ({ q, k: hashStr(`${date}:${q.id}`) ^ seed }));
  indexed.sort((a, b) => a.k - b.k);
  return indexed.slice(0, QUESTIONS_PER_DAY).map((x) => x.q.id);
}

function readState(): QuizState | null {
  if (typeof localStorage === "undefined") return null;
  try {
    const raw = localStorage.getItem(STATE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as QuizState;
    if (parsed.date !== todayKey()) return null;
    return parsed;
  } catch {
    return null;
  }
}

function writeState(s: QuizState) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(STATE_KEY, JSON.stringify(s));
  listeners.forEach((l) => l(s));
}

type Listener = (s: QuizState) => void;
const listeners = new Set<Listener>();

export interface QuizHistoryEntry {
  date: string;
  score: number;
  total: number;
}

function readHistory(): QuizHistoryEntry[] {
  if (typeof localStorage === "undefined") return [];
  try {
    return JSON.parse(localStorage.getItem(HISTORY_KEY) ?? "[]") as QuizHistoryEntry[];
  } catch {
    return [];
  }
}

function appendHistory(entry: QuizHistoryEntry) {
  if (typeof localStorage === "undefined") return;
  const next = [entry, ...readHistory().filter((h) => h.date !== entry.date)].slice(0, 30);
  localStorage.setItem(HISTORY_KEY, JSON.stringify(next));
}

export const quiz = {
  questionsPerDay: QUESTIONS_PER_DAY,
  todayKey,

  todaysQuestions(): QuizQuestion[] {
    const ids = dailyQuestionIds(todayKey());
    return ids.map((id) => QUESTIONS.find((q) => q.id === id)!).filter(Boolean);
  },

  state(): QuizState {
    const existing = readState();
    if (existing) return existing;
    const fresh: QuizState = {
      date: todayKey(),
      questionIds: dailyQuestionIds(todayKey()),
      answers: Array(QUESTIONS_PER_DAY).fill(null),
      currentIndex: 0,
    };
    return fresh;
  },

  subscribe(l: Listener) {
    listeners.add(l);
    l(this.state());
    return () => {
      listeners.delete(l);
    };
  },

  answer(index: number, choice: number): QuizState {
    const s = this.state();
    if (s.completedAt) return s;
    const answers = [...s.answers];
    answers[index] = choice;
    const next: QuizState = { ...s, answers, currentIndex: Math.min(index + 1, QUESTIONS_PER_DAY) };
    if (answers.every((a) => a !== null)) {
      const qs = this.todaysQuestions();
      const score = answers.reduce<number>((acc, a, i) => acc + (a === qs[i].answer ? 1 : 0), 0);
      next.completedAt = new Date().toISOString();
      next.score = score;
      const points = score * POINTS_PER_CORRECT + (score === QUESTIONS_PER_DAY ? POINTS_BONUS_PERFECT : 0);
      if (points > 0) gamification.award(points, "points", `Daily quiz: ${score}/${QUESTIONS_PER_DAY}`);
      appendHistory({ date: next.date, score, total: QUESTIONS_PER_DAY });
    }
    writeState(next);
    return next;
  },

  reset(): QuizState {
    if (typeof localStorage !== "undefined") localStorage.removeItem(STATE_KEY);
    const fresh = this.state();
    writeState(fresh);
    return fresh;
  },

  history: readHistory,
};
