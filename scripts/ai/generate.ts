/**
 * Build-time AI content generator.
 *
 * Supports two providers — pick with --provider=openai|gemini.
 *
 * Usage:
 *   OPENAI_API_KEY=sk-... bun run ai:generate -- \
 *     --provider=openai --model=gpt-4o-mini --kind=article --count=5 --locale=en
 *
 *   GEMINI_API_KEY=...   bun run ai:generate -- \
 *     --provider=gemini --model=gemini-2.5-flash --kind=article --count=5 --locale=en
 *
 * Every response is Zod-validated before being merged into
 * src/data/ai-generated/<locale>.json. No Lovable Cloud / Supabase needed.
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { z } from "zod";
import {
  AiArticleSchema, AiArtistSchema, AiMemberSchema,
  AiComebackSchema, AiChartSchema, AiForumThreadSchema,
  AiBundleSchema, type Locale,
} from "../../src/schemas/ai";
import { SYSTEM_PROMPT, userPromptFor } from "../../src/services/ai/promptTemplates";

const SCHEMAS = {
  article: AiArticleSchema,
  artist: AiArtistSchema,
  member: AiMemberSchema,
  comeback: AiComebackSchema,
  chart: AiChartSchema,
  forum_thread: AiForumThreadSchema,
} as const;
type Kind = keyof typeof SCHEMAS;

const FIELD: Record<Kind, keyof z.infer<typeof AiBundleSchema>> = {
  article: "articles", artist: "artists", member: "members",
  comeback: "comebacks", chart: "charts", forum_thread: "forumThreads",
};

type Provider = "openai" | "gemini";
const DEFAULT_MODEL: Record<Provider, string> = {
  openai: "gpt-4o-mini",
  gemini: "gemini-2.5-flash",
};

function arg(name: string, fallback?: string) {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

/* ---------------- OpenAI ---------------- */
async function callOpenAi(prompt: string, system: string, model: string): Promise<unknown> {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey) throw new Error("OPENAI_API_KEY env var is required");
  const res = await fetch("https://api.openai.com/v1/chat/completions", {
    method: "POST",
    headers: { Authorization: `Bearer ${apiKey}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      model,
      messages: [{ role: "system", content: system }, { role: "user", content: prompt }],
      response_format: { type: "json_object" },
      temperature: 0.8,
    }),
  });
  if (!res.ok) throw new Error(`OpenAI ${res.status}: ${await res.text()}`);
  const data = await res.json();
  const text = data.choices?.[0]?.message?.content;
  if (!text) throw new Error("Empty OpenAI response");
  return JSON.parse(text);
}

/* ---------------- Gemini (Google AI Studio) ---------------- */
async function callGemini(prompt: string, system: string, model: string): Promise<unknown> {
  const apiKey = process.env.GEMINI_API_KEY;
  if (!apiKey) throw new Error("GEMINI_API_KEY env var is required");
  const url = `https://generativelanguage.googleapis.com/v1beta/models/${encodeURIComponent(model)}:generateContent?key=${apiKey}`;
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      systemInstruction: { parts: [{ text: system }] },
      contents: [{ role: "user", parts: [{ text: prompt }] }],
      generationConfig: { responseMimeType: "application/json", temperature: 0.8 },
    }),
  });
  if (!res.ok) throw new Error(`Gemini ${res.status}: ${await res.text()}`);
  const data = await res.json();
  const text = data.candidates?.[0]?.content?.parts?.[0]?.text;
  if (!text) throw new Error("Empty Gemini response");
  return JSON.parse(text);
}

async function callAi(provider: Provider, kind: Kind, locale: Locale, model: string) {
  const prompt = userPromptFor(kind, locale);
  return provider === "openai"
    ? callOpenAi(prompt, SYSTEM_PROMPT, model)
    : callGemini(prompt, SYSTEM_PROMPT, model);
}

async function main() {
  const provider = (arg("provider", "gemini") as Provider);
  if (provider !== "openai" && provider !== "gemini") {
    throw new Error(`Unknown --provider=${provider}. Use 'openai' or 'gemini'.`);
  }
  const kind = (arg("kind", "article") as Kind);
  const count = Number(arg("count", "3"));
  const locale = (arg("locale", "en") as Locale);
  const model = arg("model", DEFAULT_MODEL[provider])!;

  if (!SCHEMAS[kind]) throw new Error(`Unknown --kind=${kind}. Allowed: ${Object.keys(SCHEMAS).join(",")}`);

  const here = dirname(fileURLToPath(import.meta.url));
  const outPath = join(here, "..", "..", "src", "data", "ai-generated", `${locale}.json`);
  mkdirSync(dirname(outPath), { recursive: true });

  let bundle: z.infer<typeof AiBundleSchema> = existsSync(outPath)
    ? AiBundleSchema.parse(JSON.parse(readFileSync(outPath, "utf8")))
    : { generatedAt: new Date().toISOString(), model: `${provider}/${model}`, locale, articles: [], artists: [], members: [], comebacks: [], charts: [], forumThreads: [] };

  console.log(`→ Generating ${count}× ${kind} (${locale}) via ${provider}/${model}`);
  for (let i = 0; i < count; i++) {
    try {
      const raw = await callAi(provider, kind, locale, model);
      const parsed = SCHEMAS[kind].parse(raw);
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (bundle[FIELD[kind]] as any[]).push(parsed);
      console.log(`  ✓ [${i + 1}/${count}] ${("slug" in parsed && parsed.slug) || ("title" in parsed && parsed.title) || "ok"}`);
    } catch (err) {
      console.error(`  ✗ [${i + 1}/${count}]`, err instanceof Error ? err.message : err);
    }
    await new Promise((r) => setTimeout(r, 600));
  }

  bundle.generatedAt = new Date().toISOString();
  bundle.model = `${provider}/${model}`;
  writeFileSync(outPath, JSON.stringify(bundle, null, 2));
  console.log(`✔ wrote ${outPath}`);
}

main().catch((e) => { console.error(e); process.exit(1); });
