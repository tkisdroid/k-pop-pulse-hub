import { useState } from "react";
import { Sparkles, Loader2 } from "lucide-react";
import { aiHelpers } from "@/services/ai/helpers";

export function ArticleSummary({ text, locale = "en" }: { text: string; locale?: string }) {
  const [bullets, setBullets] = useState<string[] | null>(null);
  const [loading, setLoading] = useState(false);

  async function run() {
    setLoading(true);
    try {
      setBullets(await aiHelpers.summarize(text, { bullets: 3, locale }));
    } finally {
      setLoading(false);
    }
  }

  if (!bullets) {
    return (
      <button
        onClick={run}
        disabled={loading}
        className="mt-4 w-full text-left rounded-xl border border-dashed border-primary/40 bg-primary/5 hover:bg-primary/10 px-4 py-3 text-sm flex items-center gap-2"
      >
        {loading ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4 text-primary" />}
        <span className="font-medium">AI TL;DR</span>
        <span className="text-muted-foreground">— summarize this article in 3 bullets</span>
      </button>
    );
  }

  return (
    <div className="mt-4 rounded-xl border border-primary/30 bg-primary/5 p-4">
      <div className="flex items-center gap-2 text-sm font-semibold mb-2">
        <Sparkles className="size-4 text-primary" /> AI TL;DR
        {!aiHelpers.configured && <span className="text-[10px] text-muted-foreground font-normal">(local fallback)</span>}
      </div>
      <ul className="space-y-1.5 text-sm">
        {bullets.map((b, i) => (
          <li key={i} className="flex gap-2"><span className="text-primary">•</span><span>{b}</span></li>
        ))}
      </ul>
    </div>
  );
}
