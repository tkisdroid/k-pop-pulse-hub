import { useCallback, useEffect, useState, type ReactNode } from "react";
import { locales, localeMeta, type LocaleCode } from "@/i18n";
import { Ctx } from "./useI18n";
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
  const t = useCallback(
    (key: string) =>
      (locales[lang] as Record<string, string>)[key] ??
      (locales.en as Record<string, string>)[key] ??
      key,
    [lang],
  );
  const rtl = !!localeMeta[lang as string]?.rtl;
  return <Ctx.Provider value={{ lang, setLang, t, rtl }}>{children}</Ctx.Provider>;
}
