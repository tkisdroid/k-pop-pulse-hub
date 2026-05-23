import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from "react";
import { locales, localeMeta, type LocaleCode } from "@/i18n";

interface I18nCtx {
  lang: LocaleCode;
  setLang: (l: LocaleCode) => void;
  t: (key: string) => string;
  rtl: boolean;
}

const Ctx = createContext<I18nCtx | null>(null);
const KEY = "kpopblog.lang";

export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<LocaleCode>("en");
  useEffect(() => {
    if (typeof window === "undefined") return;
    const saved = localStorage.getItem(KEY) as LocaleCode | null;
    if (saved && saved in locales) setLangState(saved);
  }, []);
  const setLang = useCallback((l: LocaleCode) => {
    setLangState(l);
    if (typeof window !== "undefined") localStorage.setItem(KEY, l as string);
  }, []);
  const t = useCallback((key: string) => (locales[lang] as Record<string, string>)[key] ?? (locales.en as Record<string, string>)[key] ?? key, [lang]);
  const rtl = !!localeMeta[lang as string]?.rtl;
  return <Ctx.Provider value={{ lang, setLang, t, rtl }}>{children}</Ctx.Provider>;
}

export function useI18n() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useI18n outside provider");
  return v;
}
