/**
 * Translate an existing en.json bundle into every other supported locale.
 *
 * Usage:
 *   LOVABLE_API_KEY=... bun run scripts/ai/translate.ts [--locales=ko,ja]
 *
 * For each target locale this re-uses the source bundle, translates only
 * the human-readable text fields, keeps slugs/dates/IDs intact, then
 * re-validates with Zod before writing.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { AiBundleSchema, LocaleSchema, type Locale } from "../../src/schemas/ai";

const ALL_LOCALES: Locale[] = [
  "ko", "ja", "zh-CN", "zh-TW", "es", "pt-BR", "fr", "de",
  "ru", "id", "th", "vi", "fil", "hi", "ar", "tr",
];
const GATEWAY = "https://ai.gateway.lovable.dev/v1/chat/completions";
const MODEL = "google/gemini-3-flash-preview";

// Fields that should be translated; everything else is preserved verbatim.
const TEXT_FIELDS = new Set([
  "title", "subtitle", "excerpt", "content", "bio", "description",
  "body", "name", "fullName", "fandomName", "agency", "nationality",
  "stageName", "position", "facts", "tags", "flair", "trackTitle",
]);

function arg(name: string, fallback?: string) {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

async function translateText(text: string, target: Locale): Promise<string> {
  const apiKey = process.env.LOVABLE_API_KEY;
  if (!apiKey) throw new Error("LOVABLE_API_KEY env var is required");
  const res = await fetch(GATEWAY, {
    method: "POST",
    headers: { Authorization: `Bearer ${apiKey}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      model: MODEL,
      messages: [
        { role: "system", content: `You translate K-pop editorial copy. Preserve markdown, slugs, URLs, hashtags, and dates EXACTLY. Output ONLY the translated text, no commentary.` },
        { role: "user", content: `Translate to ${target}:\n\n${text}` },
      ],
    }),
  });
  if (!res.ok) throw new Error(`Gateway ${res.status}: ${await res.text()}`);
  const data = await res.json();
  return data.choices?.[0]?.message?.content?.trim() ?? text;
}

async function deepTranslate(value: unknown, target: Locale, parentKey = ""): Promise<unknown> {
  if (typeof value === "string") {
    if (!TEXT_FIELDS.has(parentKey)) return value;
    if (value.length < 2) return value;
    return await translateText(value, target);
  }
  if (Array.isArray(value)) {
    const out: unknown[] = [];
    for (const v of value) out.push(await deepTranslate(v, target, parentKey));
    return out;
  }
  if (value && typeof value === "object") {
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(value)) {
      out[k] = await deepTranslate(v, target, k);
    }
    return out;
  }
  return value;
}

async function main() {
  const here = dirname(fileURLToPath(import.meta.url));
  const srcPath = join(here, "..", "..", "src", "data", "ai-generated", "en.json");
  if (!existsSync(srcPath)) throw new Error(`Source bundle missing: ${srcPath}. Run ai:generate first.`);
  const source = AiBundleSchema.parse(JSON.parse(readFileSync(srcPath, "utf8")));

  const requested = (arg("locales", ALL_LOCALES.join(","))!)
    .split(",").map((s) => s.trim()).filter(Boolean)
    .map((s) => LocaleSchema.parse(s)) as Locale[];

  for (const target of requested) {
    console.log(`→ Translating en → ${target}`);
    const translated = await deepTranslate(source, target);
    const bundle = AiBundleSchema.parse({ ...(translated as object), locale: target, generatedAt: new Date().toISOString() });
    const outPath = join(here, "..", "..", "src", "data", "ai-generated", `${target}.json`);
    mkdirSync(dirname(outPath), { recursive: true });
    writeFileSync(outPath, JSON.stringify(bundle, null, 2));
    console.log(`  ✔ ${outPath}`);
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
