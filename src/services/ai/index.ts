/**
 * AI provider entry-point.
 *
 * Backends: OpenAI and Google Gemini (direct APIs — NOT Lovable AI Gateway).
 *
 * Selection logic:
 *   - If VITE_AI_ENDPOINT is set, use the runtime provider (server fn proxy
 *     that calls OpenAI or Gemini). Provider chosen by VITE_AI_PROVIDER.
 *   - Otherwise, use the static provider (reads JSON written at build-time
 *     by `scripts/ai/generate.ts`).
 *
 * UI code should import `aiProvider` from here, never the concrete impls.
 */
import { staticAiProvider } from "./staticAiProvider";
import { runtimeAiProvider } from "./runtimeAiProvider";
import type { AiProvider } from "./types";

const endpoint = import.meta.env?.VITE_AI_ENDPOINT as string | undefined;

export const aiProvider: AiProvider = endpoint ? runtimeAiProvider : staticAiProvider;

export type { AiProvider, AiContentKind, AiGenerateOptions } from "./types";
export { AiProviderUnavailableError } from "./types";
