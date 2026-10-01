/**
 * AI Provider contract.
 *
 * Two implementations:
 *   1. `staticAiProvider`  — reads from /src/data/ai-generated/*.json
 *      (populated by the build-time script in `scripts/ai/`).
 *   2. `lovableAiProvider` — calls a server function that proxies the
 *      Lovable AI Gateway. Requires a backend (Supabase Edge Function or
 *      TanStack Start server function). Stubbed until backend is wired.
 *
 * UI code should ONLY depend on this interface, never the concrete provider.
 */
import type {
  AiArticle,
  AiArtist,
  AiMember,
  AiComeback,
  AiChart,
  AiForumThread,
  Locale,
} from "@/schemas/ai";

export type AiContentKind = "article" | "artist" | "member" | "comeback" | "chart" | "forum_thread";

export interface AiGenerateOptions {
  locale?: Locale;
  /** Soft hint passed to the model; the response is still Zod-validated. */
  context?: Record<string, unknown>;
  /** Override the default model when calling the gateway. */
  model?: string;
}

export interface AiProvider {
  /** Get current state (build-time generated or runtime cached). */
  list(kind: "article", locale?: Locale): Promise<AiArticle[]>;
  list(kind: "artist", locale?: Locale): Promise<AiArtist[]>;
  list(kind: "member", locale?: Locale): Promise<AiMember[]>;
  list(kind: "comeback", locale?: Locale): Promise<AiComeback[]>;
  list(kind: "chart", locale?: Locale): Promise<AiChart[]>;
  list(kind: "forum_thread", locale?: Locale): Promise<AiForumThread[]>;

  /**
   * Request fresh AI content at runtime. Throws `AiProviderUnavailableError`
   * if no backend is wired. Always validates with the corresponding Zod
   * schema before returning.
   */
  generate(kind: "article", opts?: AiGenerateOptions): Promise<AiArticle>;
  generate(kind: "artist", opts?: AiGenerateOptions): Promise<AiArtist>;
  generate(kind: "member", opts?: AiGenerateOptions): Promise<AiMember>;
  generate(kind: "comeback", opts?: AiGenerateOptions): Promise<AiComeback>;
  generate(kind: "chart", opts?: AiGenerateOptions): Promise<AiChart>;
  generate(kind: "forum_thread", opts?: AiGenerateOptions): Promise<AiForumThread>;

  /** Whether `generate()` will succeed (backend reachable). */
  readonly canGenerate: boolean;
}

export class AiProviderUnavailableError extends Error {
  constructor(reason: string) {
    super(`AI provider unavailable: ${reason}`);
    this.name = "AiProviderUnavailableError";
  }
}
