import { useState, type ReactNode } from "react";
import { Ctx } from "./useAuthModal";

export function AuthModalProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  return (
    <Ctx.Provider
      value={{
        open,
        reason,
        show: (r = "Log in to continue") => {
          setReason(r);
          setOpen(true);
        },
        hide: () => setOpen(false),
      }}
    >
      {children}
    </Ctx.Provider>
  );
}
