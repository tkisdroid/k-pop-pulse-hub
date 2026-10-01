import { createContext, useContext } from "react";
import type { User, UserRole } from "@/types";

export interface AuthCtx {
  user: User | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<User>;
  signInDemo: (role: UserRole) => Promise<User>;
  signUp: (input: {
    email: string;
    username: string;
    displayName: string;
    password: string;
  }) => Promise<User>;
  signInWithProvider: (
    p: "google" | "apple" | "x" | "kakao" | "naver" | "discord",
  ) => Promise<{ pending: true; message: string }>;
  signOut: () => Promise<void>;
}

export const Ctx = createContext<AuthCtx | null>(null);

export function useAuth() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useAuth outside provider");
  return v;
}
