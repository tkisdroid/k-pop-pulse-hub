import en from "./locales/en";
import type { LocaleDict } from "./locales/en";

// Create lightweight stubs for non-English locales (real translations later).
const langs = [
  "ko",
  "ja",
  "zh-CN",
  "zh-TW",
  "es",
  "pt-BR",
  "id",
  "th",
  "vi",
  "hi",
  "fr",
  "de",
  "tr",
  "ar",
  "ru",
  "fil",
] as const;
const stubs: Record<string, LocaleDict> = {};
langs.forEach((l) => {
  stubs[l] = { ...en };
});

export const locales = {
  en,
  ...stubs,
};

export type LocaleCode = keyof typeof locales;

export const localeMeta: Record<string, { label: string; rtl?: boolean }> = {
  en: { label: "English" },
  ko: { label: "한국어" },
  ja: { label: "日本語" },
  "zh-CN": { label: "简体中文" },
  "zh-TW": { label: "繁體中文" },
  es: { label: "Español" },
  "pt-BR": { label: "Português (BR)" },
  id: { label: "Bahasa Indonesia" },
  th: { label: "ภาษาไทย" },
  vi: { label: "Tiếng Việt" },
  hi: { label: "हिन्दी" },
  fr: { label: "Français" },
  de: { label: "Deutsch" },
  tr: { label: "Türkçe" },
  ar: { label: "العربية", rtl: true },
  ru: { label: "Русский" },
  fil: { label: "Filipino" },
};
