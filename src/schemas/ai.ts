/**
 * Zod schemas for AI-generated content.
 *
 * These schemas are the **contract** between any AI model output
 * (build-time scripts, runtime server functions, future Supabase edge
 * functions) and the UI. All AI output MUST pass through `Schema.parse(...)`
 * before being persisted or rendered. Reject malformed responses; never
 * render unverified AI output.
 *
 * Mirrors the runtime types in `src/types/index.ts`. When you add a field
 * to a type, add it here too (or vice-versa).
 */
import { z } from "zod";

/* ------------------------------------------------------------------ */
/* Primitives                                                          */
/* ------------------------------------------------------------------ */

export const LocaleSchema = z.enum([
  "en", "ko", "ja", "zh-CN", "zh-TW", "es", "pt-BR", "fr", "de",
  "ru", "id", "th", "vi", "fil", "hi", "ar", "tr",
]);
export type Locale = z.infer<typeof LocaleSchema>;

export const ISODateSchema = z
  .string()
  .regex(/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/, {
    message: "Must be ISO date (YYYY-MM-DD) or ISO datetime",
  });

export const SlugSchema = z
  .string()
  .min(1)
  .max(120)
  .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, "lowercase-kebab-case only");

export const UrlOrHashSchema = z.string().min(1).max(2048);

/* ------------------------------------------------------------------ */
/* Article (news / editorial)                                          */
/* ------------------------------------------------------------------ */

export const AiArticleSchema = z.object({
  slug: SlugSchema,
  title: z.string().min(8).max(140),
  subtitle: z.string().max(220).optional(),
  excerpt: z.string().min(40).max(320),
  /** Markdown body. 500–6000 chars; renderers must sanitize. */
  content: z.string().min(500).max(6000),
  category: z.enum([
    "news", "comeback", "interview", "review", "opinion",
    "chart", "behind", "global", "rumor",
  ]),
  tags: z.array(z.string().min(1).max(40)).min(1).max(12),
  relatedArtistSlugs: z.array(SlugSchema).max(8).default([]),
  language: LocaleSchema.default("en"),
  /** AI must NOT fabricate quotes attributed to real people. */
  containsRealQuotes: z.literal(false).default(false),
  readingTime: z.number().int().min(1).max(30),
});
export type AiArticle = z.infer<typeof AiArticleSchema>;

/* ------------------------------------------------------------------ */
/* Artist + Members                                                    */
/* ------------------------------------------------------------------ */

export const AiArtistSchema = z.object({
  slug: SlugSchema,
  name: z.string().min(1).max(80),
  koreanName: z.string().max(40).optional(),
  type: z.enum(["boy_group", "girl_group", "soloist", "coed", "band"]),
  agency: z.string().min(1).max(120),
  debutDate: ISODateSchema,
  fandomName: z.string().max(60).optional(),
  generation: z.union([z.literal(1), z.literal(2), z.literal(3), z.literal(4), z.literal(5)]),
  status: z.enum(["active", "hiatus", "disbanded", "pre_debut"]),
  nationality: z.string().min(2).max(60),
  bio: z.string().min(120).max(1200),
  socialLinks: z.record(z.string(), UrlOrHashSchema).optional(),
});
export type AiArtist = z.infer<typeof AiArtistSchema>;

export const AiMemberSchema = z.object({
  slug: SlugSchema,
  stageName: z.string().min(1).max(40),
  fullName: z.string().min(1).max(120),
  koreanName: z.string().max(40).optional(),
  birthday: ISODateSchema,
  nationality: z.string().min(2).max(60),
  groupSlug: SlugSchema,
  position: z.array(z.string().min(1).max(40)).min(1).max(5),
  mbti: z.string().regex(/^[IE][NS][TF][JP]$/).optional(),
  facts: z.array(z.string().min(10).max(220)).min(3).max(10),
});
export type AiMember = z.infer<typeof AiMemberSchema>;

/* ------------------------------------------------------------------ */
/* Comeback / Schedule                                                 */
/* ------------------------------------------------------------------ */

export const AiComebackSchema = z.object({
  artistSlug: SlugSchema,
  title: z.string().min(2).max(120),
  type: z.enum(["album", "single", "mv", "teaser", "concert", "debut", "birthday", "event"]),
  releaseAt: ISODateSchema,
  description: z.string().max(500).optional(),
});
export type AiComeback = z.infer<typeof AiComebackSchema>;

/* ------------------------------------------------------------------ */
/* Charts                                                              */
/* ------------------------------------------------------------------ */

export const AiChartEntrySchema = z.object({
  rank: z.number().int().min(1).max(100),
  artistSlug: SlugSchema,
  trackTitle: z.string().min(1).max(120),
  previousRank: z.number().int().min(0).max(100).nullable(),
  weeksOnChart: z.number().int().min(1).max(260),
});

export const AiChartSchema = z.object({
  chartId: z.enum(["weekly-global", "weekly-domestic", "monthly-streaming", "rising"]),
  weekStartDate: ISODateSchema,
  entries: z.array(AiChartEntrySchema).min(10).max(100),
});
export type AiChart = z.infer<typeof AiChartSchema>;

/* ------------------------------------------------------------------ */
/* Forum / Community seed                                              */
/* ------------------------------------------------------------------ */

export const AiForumThreadSchema = z.object({
  slug: SlugSchema,
  categorySlug: SlugSchema,
  title: z.string().min(8).max(160),
  /** Markdown OP body. */
  body: z.string().min(80).max(3000),
  flair: z.string().max(30).optional(),
  rumor: z.boolean().default(false),
  language: LocaleSchema.default("en"),
  relatedArtistSlugs: z.array(SlugSchema).max(6).default([]),
});
export type AiForumThread = z.infer<typeof AiForumThreadSchema>;

/* ------------------------------------------------------------------ */
/* Bundle shapes (used by build-time generator output files)           */
/* ------------------------------------------------------------------ */

export const AiBundleSchema = z.object({
  /** When this bundle was generated. */
  generatedAt: ISODateSchema,
  /** Model identifier used by the generator (for traceability). */
  model: z.string().min(1).max(120),
  /** Primary locale of the bundle. Translations live in sibling files. */
  locale: LocaleSchema,
  articles: z.array(AiArticleSchema).default([]),
  artists: z.array(AiArtistSchema).default([]),
  members: z.array(AiMemberSchema).default([]),
  comebacks: z.array(AiComebackSchema).default([]),
  charts: z.array(AiChartSchema).default([]),
  forumThreads: z.array(AiForumThreadSchema).default([]),
});
export type AiBundle = z.infer<typeof AiBundleSchema>;

/** Helper: safe-parse with structured error reporting. */
export function parseAiBundle(input: unknown): AiBundle {
  const result = AiBundleSchema.safeParse(input);
  if (!result.success) {
    const issues = result.error.issues
      .slice(0, 10)
      .map((i) => `  • ${i.path.join(".")} — ${i.message}`)
      .join("\n");
    throw new Error(`AI bundle failed validation:\n${issues}`);
  }
  return result.data;
}
