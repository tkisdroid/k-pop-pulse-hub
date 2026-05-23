/**
 * Runtime AI provider that calls a server function which proxies the
 * Lovable AI Gateway. Requires a backend (Supabase Edge Function or
 * TanStack Start server function) to host the gateway call — the gateway
 * MUST NOT be called from the client (the API key must stay server-side).
 *
 * Until a backend is wired, `canGenerate` is false and `generate()` throws.
 * Wire-up steps live in `src/services/ai/README.md`.
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

const SCHEMA_FOR = {
  article: AiArticleSchema,
  artist: AiArtistSchema,
  member: AiMemberSchema,
  comeback: AiComebackSchema,
  chart: AiChartSchema,
  forum_thread: AiForumThreadSchema,
} as const;

type Kind = keyof typeof SCHEMA_FOR;

async function callGateway(kind: Kind, opts: AiGenerateOptions | undefined) {
  if (!ENDPOINT) {
    throw new AiProviderUnavailableError(
      "VITE_AI_ENDPOINT is not configured. Set it to your server " +
        "function URL (e.g. /api/ai/generate) after wiring the backend.",
    );
  }
  const res = await fetch(ENDPOINT, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ kind, ...opts }),
  });
  if (res.status === 429) throw new Error("Rate limited. Try again shortly.");
  if (res.status === 402) throw new Error("AI credits exhausted.");
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

export const lovableAiProvider: AiProvider = {
  canGenerate: Boolean(ENDPOINT),
  list: staticAiProvider.list, // runtime UI still reads the static cache
  async generate(kind: Kind, opts?: AiGenerateOptions) {
    return callGateway(kind, opts) as never;
  },
};
