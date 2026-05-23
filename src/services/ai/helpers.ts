/**
 * Generic AI helpers (summarize, translate, moderate) that hit a single
 * chat-completion endpoint. The endpoint MUST be a server function (TanStack
 * Start `createServerFn` or a WP REST route) that forwards to OpenAI or
 * Gemini using server-side keys.
 *
 * Endpoint contract:
 *   POST { task, payload } -> { text } | { bullets } | { flagged, score?, reasons? }
 *
 * Moderation settings (threshold + default block reason) come from the
 * WordPress admin (Settings → KpopBlog) and are injected via
 * window.kpopblogConfig.moderation. When the SPA runs outside WP, we fetch
 * them from /wp-json/kpopblog/v1/moderation/settings if a WP API base is
 * configured, otherwise we fall back to safe defaults.
 */
const ENDPOINT = (import.meta as any).env?.VITE_AI_CHAT_ENDPOINT as string | undefined;

type ModerationSettings = { enabled: boolean; threshold: number; defaultReason: string };

const FALLBACK_MOD: ModerationSettings = {
  enabled: true,
  threshold: 0.7,
  defaultReason: "Your comment was blocked by our automated moderation system. Please revise and try again.",
};

let modSettingsPromise: Promise<ModerationSettings> | null = null;

function injectedModSettings(): ModerationSettings | null {
  if (typeof window === "undefined") return null;
  const cfg = (window as any).kpopblogConfig;
  const m = cfg?.moderation;
  if (!m) return null;
  return {
    enabled: m.enabled !== false,
    threshold: typeof m.threshold === "number" ? m.threshold : FALLBACK_MOD.threshold,
    defaultReason: typeof m.defaultReason === "string" && m.defaultReason ? m.defaultReason : FALLBACK_MOD.defaultReason,
  };
}

async function loadModerationSettings(): Promise<ModerationSettings> {
  const injected = injectedModSettings();
  if (injected) return injected;
  if (modSettingsPromise) return modSettingsPromise;
  const wpApi = (import.meta as any).env?.VITE_WP_API_BASE as string | undefined;
  if (!wpApi) {
    modSettingsPromise = Promise.resolve(FALLBACK_MOD);
    return modSettingsPromise;
  }
  modSettingsPromise = fetch(`${wpApi.replace(/\/$/, "")}/kpopblog/v1/moderation/settings`)
    .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
    .then((j) => ({
      enabled: j.enabled !== false,
      threshold: typeof j.threshold === "number" ? j.threshold : FALLBACK_MOD.threshold,
      defaultReason: typeof j.defaultReason === "string" && j.defaultReason ? j.defaultReason : FALLBACK_MOD.defaultReason,
    }))
    .catch(() => FALLBACK_MOD);
  return modSettingsPromise;
}

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
  getModerationSettings: loadModerationSettings,

  async summarize(text: string, opts?: { bullets?: number; locale?: string }): Promise<string[]> {
    const remote = await callChat<{ bullets: string[] }>("summarize", {
      text: text.replace(/<[^>]+>/g, "").slice(0, 8000),
      bullets: opts?.bullets ?? 3,
      locale: opts?.locale ?? "en",
    });
    if (remote?.bullets?.length) return remote.bullets;
    const sentences = text.replace(/<[^>]+>/g, "").split(/(?<=[.!?])\s+/).filter(Boolean);
    return sentences.slice(0, opts?.bullets ?? 3);
  },

  async translate(text: string, targetLang: string): Promise<{ text: string; machine: boolean }> {
    const remote = await callChat<{ text: string }>("translate", { text, target: targetLang });
    if (remote?.text) return { text: remote.text, machine: true };
    return { text: `[${targetLang}] ${text}`, machine: true };
  },

  async moderate(text: string): Promise<{ allowed: boolean; reasons: string[]; reason: string; score: number }> {
    const settings = await loadModerationSettings();
    if (!settings.enabled) return { allowed: true, reasons: [], reason: "", score: 0 };

    const remote = await callChat<{ flagged: boolean; score?: number; reasons?: string[] }>("moderate", {
      text,
      threshold: settings.threshold,
    });

    let flagged: boolean;
    let reasons: string[];
    let score: number;

    if (remote) {
      score = typeof remote.score === "number" ? remote.score : remote.flagged ? 1 : 0;
      reasons = remote.reasons ?? [];
      // Honor the admin-configured threshold even if the model said "flagged".
      flagged = remote.flagged && score >= settings.threshold;
    } else {
      // Local fallback: basic profanity / spam regex.
      const banned = /\b(slur1|slur2|kys|fuck you|nigger|faggot)\b/i;
      const spammy = /(https?:\/\/\S+){3,}|(.)\1{8,}/i;
      reasons = [];
      if (banned.test(text)) reasons.push("hateful language");
      if (spammy.test(text)) reasons.push("spam");
      score = reasons.length ? 1 : 0;
      flagged = reasons.length > 0;
    }

    const reason = flagged
      ? reasons.length
        ? `${settings.defaultReason} (${reasons.join(", ")})`
        : settings.defaultReason
      : "";

    return { allowed: !flagged, reasons, reason, score };
  },
};
