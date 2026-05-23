import type { User, UserRole } from "@/types";
import { demoData } from "@/data/demo";

const STORAGE_KEY = "kpopblog.session.v1";

export interface AuthProvider {
  name: string;
  getCurrentUser(): User | null;
  signIn(email: string, password: string): Promise<User>;
  signInDemo(role: UserRole): Promise<User>;
  signUp(input: { email: string; username: string; displayName: string }): Promise<User>;
  signInWithProvider(provider: "google" | "apple" | "x" | "kakao" | "naver" | "discord"): Promise<{ pending: true; message: string }>;
  signOut(): Promise<void>;
  onChange(cb: (user: User | null) => void): () => void;
}

const listeners = new Set<(u: User | null) => void>();
function emit(u: User | null) { listeners.forEach((l) => l(u)); }

function persist(u: User | null) {
  if (typeof window === "undefined") return;
  if (u) localStorage.setItem(STORAGE_KEY, JSON.stringify(u));
  else localStorage.removeItem(STORAGE_KEY);
}

function read(): User | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as User) : null;
  } catch { return null; }
}

export const demoAuthProvider: AuthProvider = {
  name: "demo",
  getCurrentUser: read,
  async signIn(email) {
    const u = demoData.users.find((x) => x.email === email) ?? demoData.users[0];
    persist(u); emit(u); return u;
  },
  async signInDemo(role) {
    const u = demoData.users.find((x) => x.role === role) ?? demoData.users[0];
    persist(u); emit(u); return u;
  },
  async signUp({ email, username, displayName }) {
    const u: User = {
      id: `u_${Date.now()}`,
      email, username, displayName,
      role: "member", trustLevel: 1, points: 0, badges: [], followedArtists: [],
      createdAt: new Date().toISOString(),
    };
    persist(u); emit(u); return u;
  },
  async signInWithProvider(provider) {
    return { pending: true, message: `${provider} login requires Supabase. Connection will be enabled after credentials are provided.` };
  },
  async signOut() { persist(null); emit(null); },
  onChange(cb) { listeners.add(cb); return () => listeners.delete(cb); },
};
