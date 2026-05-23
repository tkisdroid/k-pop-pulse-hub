/**
 * Static AI provider: serves AI-generated content from JSON files written
 * at build-time by `scripts/ai/generate.ts`. Falls back to empty arrays
 * when no bundle has been generated yet for a given locale.
 *
 * All reads are Zod-validated; corrupted files surface a console.error
 * and behave as empty (so the UI never renders unverified data).
 */
import { AiBundleSchema, type Locale } from "@/schemas/ai";
import {
  AiProviderUnavailableError,
  type AiProvider,
} from "./types";

// Eager import — Vite tree-shakes locales that aren't referenced.
const bundleModules = import.meta.glob<{ default: unknown }>(
  "@/data/ai-generated/*.json",
  { eager: true },
);

type Kind =
  | "article" | "artist" | "member"
  | "comeback" | "chart" | "forum_thread";

const KIND_TO_FIELD: Record<Kind, keyof ReturnType<typeof AiBundleSchema.parse>> = {
  article: "articles",
  artist: "artists",
  member: "members",
  comeback: "comebacks",
  chart: "charts",
  forum_thread: "forumThreads",
};

function loadBundle(locale: Locale) {
  const path = `/src/data/ai-generated/${locale}.json`;
  const mod = bundleModules[path];
  if (!mod) return null;
  const parsed = AiBundleSchema.safeParse(mod.default);
  if (!parsed.success) {
    // eslint-disable-next-line no-console
    console.error(`[ai] invalid bundle ${path}:`, parsed.error.issues.slice(0, 3));
    return null;
  }
  return parsed.data;
}

export const staticAiProvider: AiProvider = {
  canGenerate: false,

  async list(kind: Kind, locale: Locale = "en") {
    const bundle = loadBundle(locale) ?? loadBundle("en");
    if (!bundle) return [] as never;
    return bundle[KIND_TO_FIELD[kind]] as never;
  },

  async generate(kind: Kind) {
    throw new AiProviderUnavailableError(
      `Static provider cannot generate '${kind}' at runtime. ` +
        `Run \`bun run ai:generate\` to refresh build-time data, ` +
        `or enable Lovable Cloud / Supabase to use the runtime provider.`,
    );
  },
};
