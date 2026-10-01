import { createContext, useContext } from "react";

interface AuthModalCtx {
  open: boolean;
  show: (reason?: string) => void;
  hide: () => void;
  reason: string;
}
export const Ctx = createContext<AuthModalCtx | null>(null);

export function useAuthModal() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useAuthModal outside provider");
  return v;
}
