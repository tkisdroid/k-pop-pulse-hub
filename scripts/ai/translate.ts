/**
 * Translate an existing en.json bundle into other supported locales.
 *
 * Usage:
 *   OPENAI_API_KEY=... bun run ai:translate -- --provider=openai
 *   GEMINI_API_KEY=... bun run ai:translate -- --provider=gemini --locales=ko,ja
 *
 * Only human-readable text fields are translated; slugs, dates, URLs,
 * and IDs are preserved exactly. Output is re-validated with Zod.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { AiBundleSchema, LocaleSchema, type Locale } from "../../src/schemas/ai";

const ALL_LOCALES: Locale[] = [
  "ko",
  "ja",
  "zh-CN",
  "zh-TW",
  "es",
  "pt-BR",
  "fr",
  "de",
  "ru",
  "id",
  "th",
  "vi",
  "fil",
  "hi",
  "ar",
  "tr",
];

type Provider = "openai" | "gemini";
const DEFAULT_MODEL: Record<Provider, string> = {
  openai: "gpt-4o-mini",
  gemini: "gemini-2.5-flash",
};

const SYSTEM = `You translate K-pop editorial copy. Preserve markdown, slugs, URLs, hashtags, numbers and dates EXACTLY. Output ONLY the translated text, no commentary, no quotes.`;

const TEXT_FIELDS = new Set([
  "title",
  "subtitle",
  "excerpt",
  "content",
  "bio",
  "description",
  "body",
  "name",
  "fullName",
  "fandomName",
  "agency",
  "nationality",
  "stageName",
  "position",
  "facts",
  "tags",
  "flair",
  "trackTitle",
]);

function arg(name: string, fallback?: string) {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

async function openaiTranslate(text: string, target: Locale, model: string): Promise<string> {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey) throw new Error("OPENAI_API_KEY env var is required");
  const res = await fetch("https://api.openai.com/v1/chat/completions", {
    method: "POST",
    headers: { Authorization: `Bearer ${apiKey}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      model,
      messages: [
        { role: "system", content: SYSTEM },
        { role: "user", content: `Translate to ${target}:\n\n${text}` },
      ],
      temperature: 0.3,
    }),
  });
  if (!res.ok) throw new Error(`OpenAI ${res.status}: ${await res.text()}`);
  const data = await res.json();
  return data.choices?.[0]?.message?.content?.trim() ?? text;
}

async function geminiTranslate(text: string, target: Locale, model: string): Promise<string> {
  const apiKey = process.env.GEMINI_API_KEY;
  if (!apiKey) throw new Error("GEMINI_API_KEY env var is required");
  const url = `https://generativelanguage.googleapis.com/v1beta/models/${encodeURIComponent(model)}:generateContent?key=${apiKey}`;
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      systemInstruction: { parts: [{ text: SYSTEM }] },
      contents: [{ role: "user", parts: [{ text: `Translate to ${target}:\n\n${text}` }] }],
      generationConfig: { temperature: 0.3 },
    }),
  });
  if (!res.ok) throw new Error(`Gemini ${res.status}: ${await res.text()}`);
  const data = await res.json();
  return data.candidates?.[0]?.content?.parts?.[0]?.text?.trim() ?? text;
}

async function deepTranslate(
  value: unknown,
  target: Locale,
  translate: (t: string) => Promise<string>,
  parentKey = "",
): Promise<unknown> {
  if (typeof value === "string") {
    if (!TEXT_FIELDS.has(parentKey) || value.length < 2) return value;
    return await translate(value);
  }
  if (Array.isArray(value)) {
    const out: unknown[] = [];
    for (const v of value) out.push(await deepTranslate(v, target, translate, parentKey));
    return out;
  }
  if (value && typeof value === "object") {
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(value)) {
      out[k] = await deepTranslate(v, target, translate, k);
    }
    return out;
  }
  return value;
}

async function main() {
  const provider = arg("provider", "gemini") as Provider;
  if (provider !== "openai" && provider !== "gemini") {
    throw new Error(`Unknown --provider=${provider}. Use 'openai' or 'gemini'.`);
  }
  const model = arg("model", DEFAULT_MODEL[provider])!;

  const here = dirname(fileURLToPath(import.meta.url));
  const srcPath = join(here, "..", "..", "src", "data", "ai-generated", "en.json");
  if (!existsSync(srcPath)) throw new Error(`Missing ${srcPath}. Run ai:generate first.`);
  const source = AiBundleSchema.parse(JSON.parse(readFileSync(srcPath, "utf8")));

  const requested = arg("locales", ALL_LOCALES.join(","))!
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean)
    .map((s) => LocaleSchema.parse(s)) as Locale[];

  const translate = (t: string, target: Locale) =>
    provider === "openai" ? openaiTranslate(t, target, model) : geminiTranslate(t, target, model);

  for (const target of requested) {
    console.log(`→ Translating en → ${target} via ${provider}/${model}`);
    const translated = await deepTranslate(source, target, (t) => translate(t, target));
    const bundle = AiBundleSchema.parse({
      ...(translated as object),
      locale: target,
      generatedAt: new Date().toISOString(),
      model: `${provider}/${model}`,
    });
    const outPath = join(here, "..", "..", "src", "data", "ai-generated", `${target}.json`);
    mkdirSync(dirname(outPath), { recursive: true });
    writeFileSync(outPath, JSON.stringify(bundle, null, 2));
    console.log(`  ✔ ${outPath}`);
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
