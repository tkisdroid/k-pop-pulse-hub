/**
 * Prompt templates used by both the build-time generator and the
 * runtime server function. Keep these in one place so the model's
 * behavior is predictable across surfaces.
 *
 * RULES the model MUST follow (enforced again by Zod):
 *   - No real-person quotes. Treat all artist names as fictional.
 *   - All slugs lowercase-kebab-case, ASCII only.
 *   - Dates ISO-8601.
 *   - Markdown only in body fields; no HTML tags.
 *   - Output MUST be a single JSON object matching the requested schema.
 */
import type { AiContentKind } from "./types";
import type { Locale } from "@/schemas/ai";

export const SYSTEM_PROMPT = `You are a senior editorial assistant for a fictional K-pop community site called KpopBlog.
You generate clean, factual-sounding, but fully fictional content for demo purposes.
Hard rules:
- Treat all artist and member names as fictional. Do not reference real K-pop idols, real songs, or real events.
- Never fabricate quotes attributed to a real person.
- Output ONLY a single JSON object that matches the requested schema. No prose, no markdown code fences, no commentary.
- Use lowercase-kebab-case for any slug field.
- Use ISO-8601 for any date field.
- Body/markdown fields may use markdown formatting but never raw HTML.`;

export function userPromptFor(
  kind: AiContentKind,
  locale: Locale,
  context: Record<string, unknown> = {},
): string {
  const ctx = JSON.stringify(context);
  const langLine = `Write in language: ${locale}.`;
  switch (kind) {
    case "article":
      return `Generate ONE fictional K-pop news article.
${langLine}
Categories allowed: news | comeback | interview | review | opinion | chart | behind | global | rumor.
Body must be 600-2000 chars of clean markdown with at least 3 paragraphs and one subheading.
Context: ${ctx}`;
    case "artist":
      return `Generate ONE fictional K-pop artist profile (group or soloist).
${langLine}
Bio must be 150-400 chars and read like a real artist database entry. Context: ${ctx}`;
    case "member":
      return `Generate ONE fictional group member profile.
${langLine}
Provide 5-8 short "facts" each 15-180 chars. Context: ${ctx}`;
    case "comeback":
      return `Generate ONE fictional upcoming comeback/release event.
${langLine}
releaseAt must be a date within the next 90 days. Context: ${ctx}`;
    case "chart":
      return `Generate ONE fictional weekly chart with 10-30 entries, ranks 1..N, no ties.
${langLine}
Context: ${ctx}`;
    case "forum_thread":
      return `Generate ONE fictional community forum thread (opening post only).
${langLine}
Tone should match a fan discussion (curious, enthusiastic, respectful). Body 120-800 chars. Context: ${ctx}`;
  }
}
