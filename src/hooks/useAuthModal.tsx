import { createContext, useContext, useState, type ReactNode } from "react";

interface AuthModalCtx {
  open: boolean;
  show: (reason?: string) => void;
  hide: () => void;
  reason: string;
}
const Ctx = createContext<AuthModalCtx | null>(null);

export function AuthModalProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  return (
    <Ctx.Provider value={{ open, reason, show: (r = "Log in to continue") => { setReason(r); setOpen(true); }, hide: () => setOpen(false) }}>
      {children}
    </Ctx.Provider>
  );
}
export function useAuthModal() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useAuthModal outside provider");
  return v;
}
