import { useCallback, useEffect, useState, type ReactNode } from "react";
import { Ctx, type Theme } from "./useTheme";
const KEY = "kpopblog.theme";

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setTheme] = useState<Theme>("dark");
  useEffect(() => {
    if (typeof window === "undefined") return;
    const saved =
      (localStorage.getItem(KEY) as Theme | null) ??
      (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
    setTheme(saved);
  }, []);
  useEffect(() => {
    if (typeof document === "undefined") return;
    document.documentElement.classList.toggle("dark", theme === "dark");
    if (typeof window !== "undefined") localStorage.setItem(KEY, theme);
  }, [theme]);
  const toggle = useCallback(() => setTheme((t) => (t === "dark" ? "light" : "dark")), []);
  return <Ctx.Provider value={{ theme, toggle }}>{children}</Ctx.Provider>;
}
