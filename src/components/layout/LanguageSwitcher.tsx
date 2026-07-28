import { useI18n } from "@/hooks/useI18n";
import { localeMeta, type LocaleCode } from "@/i18n";
import { Globe } from "lucide-react";
import { useState } from "react";

export function LanguageSwitcher() {
  const { lang, setLang } = useI18n();
  const [open, setOpen] = useState(false);
  return (
    <div className="relative">
      <button onClick={() => setOpen((o) => !o)} className="p-1.5 sm:p-2 rounded-md hover:bg-accent flex items-center gap-1 text-sm" aria-label="Language">
        <Globe className="size-5" />
        <span className="hidden md:inline text-xs">{(localeMeta[lang as string]?.label ?? "EN").slice(0, 2)}</span>
      </button>
      {open && (
        <div className="absolute right-0 mt-2 w-56 bg-popover border border-border rounded-lg shadow-lg max-h-96 overflow-y-auto z-50">
          {Object.entries(localeMeta).map(([code, meta]) => (
            <button
              key={code}
              onClick={() => { setLang(code as LocaleCode); setOpen(false); }}
              className={`w-full text-left px-3 py-2 text-sm hover:bg-accent ${lang === code ? "text-primary font-medium" : ""}`}
            >
              {meta.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
