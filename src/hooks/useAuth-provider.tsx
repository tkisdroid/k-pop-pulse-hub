import { useCallback, useEffect, useState, type ReactNode } from "react";
import { authProvider } from "@/services/auth";
import type { User, UserRole } from "@/types";
import { Ctx, type AuthCtx } from "./useAuth";

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
  const signUp = useCallback(
    (i: { email: string; username: string; displayName: string; password: string }) =>
      authProvider.signUp(i),
    [],
  );
  const signInWithProvider = useCallback(
    (p: Parameters<AuthCtx["signInWithProvider"]>[0]) => authProvider.signInWithProvider(p),
    [],
  );
  const signOut = useCallback(() => authProvider.signOut(), []);

  return (
    <Ctx.Provider
      value={{ user, loading, signIn, signInDemo, signUp, signInWithProvider, signOut }}
    >
      {children}
    </Ctx.Provider>
  );
}
