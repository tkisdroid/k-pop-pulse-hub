/**
 * Generic AI helpers (summarize, translate, moderate) that hit a single
 * chat-completion endpoint. The endpoint MUST be a server function (TanStack
 * Start `createServerFn` or a WP REST route) that forwards to OpenAI or
 * Gemini using server-side keys.
 *
 * Endpoint contract: POST { task, payload } -> { text } | { flagged, reasons }
 */
const ENDPOINT = (import.meta as any).env?.VITE_AI_CHAT_ENDPOINT as string | undefined;

async function callChat<T>(task: string, payload: unknown): Promise<T | null> {
  if (!ENDPOINT) return null;
  try {
    const res = await fetch(ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ task, payload }),
    });
    if (!res.ok) {
      if (res.status === 429) throw new Error("Rate limited. Try again shortly.");
      if (res.status === 402) throw new Error("AI quota exhausted.");
      throw new Error(`AI ${res.status}`);
    }
    return (await res.json()) as T;
  } catch (e) {
    console.warn("[ai/helpers]", e);
    return null;
  }
}

export const aiHelpers = {
  configured: Boolean(ENDPOINT),

  async summarize(text: string, opts?: { bullets?: number; locale?: string }): Promise<string[]> {
    const remote = await callChat<{ bullets: string[] }>("summarize", {
      text: text.replace(/<[^>]+>/g, "").slice(0, 8000),
      bullets: opts?.bullets ?? 3,
      locale: opts?.locale ?? "en",
    });
    if (remote?.bullets?.length) return remote.bullets;
    // Local fallback so the UI always works.
    const sentences = text.replace(/<[^>]+>/g, "").split(/(?<=[.!?])\s+/).filter(Boolean);
    return sentences.slice(0, opts?.bullets ?? 3);
  },

  async translate(text: string, targetLang: string): Promise<{ text: string; machine: boolean }> {
    const remote = await callChat<{ text: string }>("translate", { text, target: targetLang });
    if (remote?.text) return { text: remote.text, machine: true };
    return { text: `[${targetLang}] ${text}`, machine: true };
  },

  async moderate(text: string): Promise<{ allowed: boolean; reasons: string[] }> {
    const remote = await callChat<{ flagged: boolean; reasons?: string[] }>("moderate", { text });
    if (remote) return { allowed: !remote.flagged, reasons: remote.reasons ?? [] };
    // Local fallback: basic profanity / spam regex.
    const banned = /\b(slur1|slur2|kys|fuck you|nigger|faggot)\b/i;
    const spammy = /(https?:\/\/\S+){3,}|(.)\1{8,}/i;
    const reasons: string[] = [];
    if (banned.test(text)) reasons.push("hateful language");
    if (spammy.test(text)) reasons.push("spam");
    return { allowed: reasons.length === 0, reasons };
  },
};
