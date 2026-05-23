/**
 * Build-time AI content generator.
 *
 * Usage:
 *   LOVABLE_API_KEY=... bun run scripts/ai/generate.ts \
 *     --kind=article --count=5 --locale=en
 *
 * Calls the Lovable AI Gateway, validates every response with the
 * matching Zod schema, then merges into src/data/ai-generated/<locale>.json.
 *
 * This runs OFFLINE / on demand — it does NOT require Lovable Cloud or
 * Supabase to be enabled. Only requires `LOVABLE_API_KEY` in the env.
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

const DEFAULT_MODEL = "google/gemini-3-flash-preview";
const GATEWAY = "https://ai.gateway.lovable.dev/v1/chat/completions";

function arg(name: string, fallback?: string) {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

async function callAi(kind: Kind, locale: Locale, model: string): Promise<unknown> {
  const apiKey = process.env.LOVABLE_API_KEY;
  if (!apiKey) throw new Error("LOVABLE_API_KEY env var is required");

  const res = await fetch(GATEWAY, {
    method: "POST",
    headers: { Authorization: `Bearer ${apiKey}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      model,
      messages: [
        { role: "system", content: SYSTEM_PROMPT },
        { role: "user", content: userPromptFor(kind, locale) },
      ],
      response_format: { type: "json_object" },
    }),
  });
  if (!res.ok) throw new Error(`Gateway ${res.status}: ${await res.text()}`);
  const data = await res.json();
  const text = data.choices?.[0]?.message?.content;
  if (!text) throw new Error("Empty AI response");
  return JSON.parse(text);
}

async function main() {
  const kind = (arg("kind", "article") as Kind);
  const count = Number(arg("count", "3"));
  const locale = (arg("locale", "en") as Locale);
  const model = arg("model", DEFAULT_MODEL)!;

  if (!SCHEMAS[kind]) throw new Error(`Unknown --kind=${kind}. Allowed: ${Object.keys(SCHEMAS).join(",")}`);

  const here = dirname(fileURLToPath(import.meta.url));
  const outPath = join(here, "..", "..", "src", "data", "ai-generated", `${locale}.json`);
  mkdirSync(dirname(outPath), { recursive: true });

  let bundle: z.infer<typeof AiBundleSchema> = existsSync(outPath)
    ? AiBundleSchema.parse(JSON.parse(readFileSync(outPath, "utf8")))
    : { generatedAt: new Date().toISOString(), model, locale, articles: [], artists: [], members: [], comebacks: [], charts: [], forumThreads: [] };

  console.log(`→ Generating ${count}× ${kind} (${locale}) via ${model}`);
  for (let i = 0; i < count; i++) {
    try {
      const raw = await callAi(kind, locale, model);
      const parsed = SCHEMAS[kind].parse(raw);
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (bundle[FIELD[kind]] as any[]).push(parsed);
      console.log(`  ✓ [${i + 1}/${count}] ${("slug" in parsed && parsed.slug) || ("title" in parsed && parsed.title) || "ok"}`);
    } catch (err) {
      console.error(`  ✗ [${i + 1}/${count}]`, err instanceof Error ? err.message : err);
    }
    await new Promise((r) => setTimeout(r, 800)); // gentle rate limit
  }

  bundle.generatedAt = new Date().toISOString();
  bundle.model = model;
  writeFileSync(outPath, JSON.stringify(bundle, null, 2));
  console.log(`✔ wrote ${outPath}`);
}

main().catch((e) => { console.error(e); process.exit(1); });
