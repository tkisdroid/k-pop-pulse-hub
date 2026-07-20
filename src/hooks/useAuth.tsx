import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from "react";
import { authProvider } from "@/services/auth";
import type { User, UserRole } from "@/types";

interface AuthCtx {
  user: User | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<User>;
  signInDemo: (role: UserRole) => Promise<User>;
  signUp: (input: { email: string; username: string; displayName: string; password: string }) => Promise<User>;
  signInWithProvider: (p: "google" | "apple" | "x" | "kakao" | "naver" | "discord") => Promise<{ pending: true; message: string }>;
  signOut: () => Promise<void>;
}

const Ctx = createContext<AuthCtx | null>(null);

export function AuthProviderShell({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const initial = authProvider.init ? await authProvider.init() : authProvider.getCurrentUser();
      if (!cancelled) {
        setUser(initial);
        setLoading(false);
      }
    })();
    const unsubscribe = authProvider.onChange((u) => setUser(u));
    return () => {
      cancelled = true;
      unsubscribe();
    };
  }, []);

  const signIn = useCallback((e: string, p: string) => authProvider.signIn(e, p), []);
  const signInDemo = useCallback((r: UserRole) => authProvider.signInDemo(r), []);
  const signUp = useCallback((i: { email: string; username: string; displayName: string; password: string }) => authProvider.signUp(i), []);
  const signInWithProvider = useCallback((p: any) => authProvider.signInWithProvider(p), []);
  const signOut = useCallback(() => authProvider.signOut(), []);

  return <Ctx.Provider value={{ user, loading, signIn, signInDemo, signUp, signInWithProvider, signOut }}>{children}</Ctx.Provider>;
}

export function useAuth() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useAuth outside provider");
  return v;
}
