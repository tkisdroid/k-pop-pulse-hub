/**
 * AI provider entry-point.
 *
 * Selection logic:
 *   - If VITE_AI_ENDPOINT is set, use the runtime provider (server fn proxy).
 *   - Otherwise, use the static provider (reads JSON written at build-time
 *     by `scripts/ai/generate.ts`).
 *
 * UI code should import `aiProvider` from here, never the concrete impls.
 */
import { staticAiProvider } from "./staticAiProvider";
import { lovableAiProvider } from "./lovableAiProvider";
import type { AiProvider } from "./types";

const endpoint = (import.meta as any).env?.VITE_AI_ENDPOINT as string | undefined;

export const aiProvider: AiProvider = endpoint ? lovableAiProvider : staticAiProvider;

export type { AiProvider, AiContentKind, AiGenerateOptions } from "./types";
export { AiProviderUnavailableError } from "./types";
