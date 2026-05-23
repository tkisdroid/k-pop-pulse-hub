/**
 * Runtime AI provider that calls a server function which proxies OpenAI
 * or Google Gemini APIs. The provider keys MUST stay server-side — never
 * call OpenAI / Gemini directly from the browser.
 *
 * Wire-up (later):
 *   1. Add a TanStack Start server function or Supabase Edge Function at
 *      e.g. /api/ai/generate. Read `OPENAI_API_KEY` or `GEMINI_API_KEY`
 *      from `process.env` server-side, forward to the provider, return JSON.
 *   2. Set `VITE_AI_ENDPOINT=/api/ai/generate` in the project env.
 *
 * Until then, `canGenerate` is false and `generate()` throws.
 */
import {
  AiArticleSchema, AiArtistSchema, AiMemberSchema,
  AiComebackSchema, AiChartSchema, AiForumThreadSchema,
} from "@/schemas/ai";
import {
  AiProviderUnavailableError,
  type AiGenerateOptions,
  type AiProvider,
} from "./types";
import { staticAiProvider } from "./staticAiProvider";

const ENDPOINT = (import.meta as any).env?.VITE_AI_ENDPOINT as string | undefined;
const DEFAULT_PROVIDER =
  ((import.meta as any).env?.VITE_AI_PROVIDER as "openai" | "gemini" | undefined) ?? "gemini";

const SCHEMA_FOR = {
  article: AiArticleSchema,
  artist: AiArtistSchema,
  member: AiMemberSchema,
  comeback: AiComebackSchema,
  chart: AiChartSchema,
  forum_thread: AiForumThreadSchema,
} as const;

type Kind = keyof typeof SCHEMA_FOR;

async function callEndpoint(kind: Kind, opts: AiGenerateOptions | undefined) {
  if (!ENDPOINT) {
    throw new AiProviderUnavailableError(
      "VITE_AI_ENDPOINT is not configured. Wire a server function that " +
        "calls OpenAI or Gemini server-side, then set VITE_AI_ENDPOINT.",
    );
  }
  const res = await fetch(ENDPOINT, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ kind, provider: DEFAULT_PROVIDER, ...opts }),
  });
  if (res.status === 429) throw new Error("Rate limited. Try again shortly.");
  if (res.status === 402) throw new Error("AI quota exhausted.");
  if (!res.ok) throw new Error(`AI endpoint failed: ${res.status}`);
  const json = await res.json();
  const parsed = SCHEMA_FOR[kind].safeParse(json);
  if (!parsed.success) {
    throw new Error(
      `AI response failed schema validation:\n` +
        parsed.error.issues.slice(0, 5).map((i) => `  • ${i.path.join(".")} — ${i.message}`).join("\n"),
    );
  }
  return parsed.data;
}

export const runtimeAiProvider: AiProvider = {
  canGenerate: Boolean(ENDPOINT),
  list: staticAiProvider.list, // runtime UI still reads the static cache
  async generate(kind: Kind, opts?: AiGenerateOptions) {
    return callEndpoint(kind, opts) as never;
  },
};
