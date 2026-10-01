import { createContext, useContext } from "react";
import { type LocaleCode } from "@/i18n";

interface I18nCtx {
  lang: LocaleCode;
  setLang: (l: LocaleCode) => void;
  t: (key: string) => string;
  rtl: boolean;
}

export const Ctx = createContext<I18nCtx | null>(null);

export function useI18n() {
  const v = useContext(Ctx);
  if (!v) throw new Error("useI18n outside provider");
  return v;
}
