import { createContext, useContext } from "react";

export type Theme = "light" | "dark";
export const Ctx = createContext<{ theme: Theme; toggle: () => void } | null>(null);

export function useTheme() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useTheme outside provider");
  return v;
}
