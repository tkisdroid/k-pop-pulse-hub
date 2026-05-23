/**
 * Fandom level & badge system.
 *
 * Points are awarded for engagement: reading, reacting, commenting,
 * voting in polls, following artists. Persisted locally so the UI works
 * offline; when WordPress sync is enabled, mirror to user_meta via the
 * plugin's /wp-json/kpopblog/v1/profile/points endpoint.
 */
export interface FandomLevel {
  level: number;
  title: string;
  minPoints: number;
  color: string;
}

export const LEVELS: FandomLevel[] = [
  { level: 1, title: "Newbie Fan", minPoints: 0, color: "#94a3b8" },
  { level: 2, title: "Stan", minPoints: 50, color: "#60a5fa" },
  { level: 3, title: "Super Fan", minPoints: 200, color: "#a78bfa" },
  { level: 4, title: "Bias Wrecker", minPoints: 500, color: "#f472b6" },
  { level: 5, title: "Ultimate Stan", minPoints: 1500, color: "#facc15" },
  { level: 6, title: "K-pop Sage", minPoints: 5000, color: "#fb7185" },
];

export interface BadgeDef {
  id: string;
  label: string;
  description: string;
  emoji: string;
  earn: (stats: GamificationStats) => boolean;
}

export interface GamificationStats {
  points: number;
  articlesRead: number;
  commentsPosted: number;
  pollsVoted: number;
  artistsFollowed: number;
  streakDays: number;
}

export const BADGES: BadgeDef[] = [
  { id: "first-read", label: "First Read", description: "Read your first article", emoji: "📰", earn: (s) => s.articlesRead >= 1 },
  { id: "chatty", label: "Chatty", description: "Posted 10 comments", emoji: "💬", earn: (s) => s.commentsPosted >= 10 },
  { id: "pollster", label: "Pollster", description: "Voted in 5 polls", emoji: "🗳️", earn: (s) => s.pollsVoted >= 5 },
  { id: "loyal", label: "Loyal", description: "Following 3+ artists", emoji: "💖", earn: (s) => s.artistsFollowed >= 3 },
  { id: "streak-7", label: "7-day Streak", description: "Visited 7 days in a row", emoji: "🔥", earn: (s) => s.streakDays >= 7 },
  { id: "centurion", label: "Centurion", description: "Earned 100+ points", emoji: "🏆", earn: (s) => s.points >= 100 },
];

const KEY = "kpopblog:gamification";

type Listener = (s: GamificationStats) => void;
const listeners = new Set<Listener>();

function defaults(): GamificationStats {
  return { points: 0, articlesRead: 0, commentsPosted: 0, pollsVoted: 0, artistsFollowed: 0, streakDays: 1 };
}

function read(): GamificationStats {
  if (typeof localStorage === "undefined") return defaults();
  try {
    return { ...defaults(), ...JSON.parse(localStorage.getItem(KEY) ?? "{}") };
  } catch {
    return defaults();
  }
}

function write(s: GamificationStats) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(KEY, JSON.stringify(s));
  listeners.forEach((l) => l(s));
}

export const gamification = {
  stats: read,
  subscribe(l: Listener) {
    listeners.add(l);
    l(read());
    return () => listeners.delete(l);
  },
  award(points: number, key?: keyof GamificationStats) {
    const s = read();
    s.points += points;
    if (key && key !== "points") s[key] = (s[key] as number) + 1;
    write(s);
  },
  levelOf(points: number): FandomLevel {
    return [...LEVELS].reverse().find((l) => points >= l.minPoints) ?? LEVELS[0];
  },
  progress(points: number): { current: FandomLevel; next: FandomLevel | null; pct: number } {
    const current = this.levelOf(points);
    const next = LEVELS.find((l) => l.minPoints > points) ?? null;
    if (!next) return { current, next, pct: 100 };
    const pct = Math.min(100, ((points - current.minPoints) / (next.minPoints - current.minPoints)) * 100);
    return { current, next, pct };
  },
  earnedBadges(): BadgeDef[] {
    const s = read();
    return BADGES.filter((b) => b.earn(s));
  },
};
