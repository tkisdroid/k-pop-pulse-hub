/**
 * Newsletter client. Talks to the WordPress plugin's
 * /wp-json/kpopblog/v1/newsletter/* endpoints when available, and falls
 * back to localStorage so the CTA works in standalone preview / demo.
 *
 * Admin-editable copy (CTA, success/confirm messages, opt-in label,
 * default frequency, topics, double-opt-in) is fetched from
 * /newsletter/settings and cached for 5 minutes.
 */

export type NewsletterSettings = {
  cta: { eyebrow: string; heading: string; subheading: string; button: string };
  signupOptInLabel: string;
  successMessage: string;
  confirmMessage: string;
  defaultFrequency: "daily" | "weekly" | "monthly";
  availableTopics: string[];
  doubleOptIn: boolean;
};

export const NEWSLETTER_DEFAULTS: NewsletterSettings = {
  cta: {
    eyebrow: "Stay in the loop",
    heading: "Get the weekly K-pop briefing",
    subheading:
      "Comebacks, chart movements, tour dates and member news — straight to your inbox. No spam, unsubscribe anytime.",
    button: "Subscribe",
  },
  signupOptInLabel: "Email me the weekly KpopBlog newsletter (you can unsubscribe anytime).",
  successMessage: "You're in! Check your inbox to confirm your subscription.",
  confirmMessage: "Thanks — your subscription is confirmed.",
  defaultFrequency: "weekly",
  availableTopics: ["comebacks", "charts", "tours", "member-updates", "editorial"],
  doubleOptIn: true,
};

const STORAGE_KEY = "kpopblog:newsletter:local";
const SETTINGS_CACHE_KEY = "kpopblog:newsletter:settings";
const SETTINGS_TTL_MS = 5 * 60_000;

function apiBase(): string | null {
  if (typeof window === "undefined") return null;
  const cfg = (window as { kpopblogConfig?: { apiUrl?: string } }).kpopblogConfig;
  const raw = cfg?.apiUrl;
  if (!raw) return null;
  // The plugin injects apiUrl already including the /wp-json/kpopblog/v1
  // namespace. Strip it so callers can append clean /wp-json/... paths
  // without producing a double prefix.
  return String(raw).replace(/\/$/, "").replace(/\/wp-json\/kpopblog\/v1$/, "");
}

export async function fetchNewsletterSettings(): Promise<NewsletterSettings> {
  if (typeof window === "undefined") return NEWSLETTER_DEFAULTS;
  try {
    const cached = JSON.parse(sessionStorage.getItem(SETTINGS_CACHE_KEY) ?? "null") as
      | { at: number; settings: NewsletterSettings }
      | null;
    if (cached && Date.now() - cached.at < SETTINGS_TTL_MS) return cached.settings;
  } catch {
    /* ignore */
  }
  const base = apiBase();
  if (!base) return NEWSLETTER_DEFAULTS;
  try {
    const res = await fetch(`${base}/wp-json/kpopblog/v1/newsletter/settings`, { credentials: "omit" });
    if (!res.ok) return NEWSLETTER_DEFAULTS;
    const data = (await res.json()) as NewsletterSettings;
    sessionStorage.setItem(SETTINGS_CACHE_KEY, JSON.stringify({ at: Date.now(), settings: data }));
    return data;
  } catch {
    return NEWSLETTER_DEFAULTS;
  }
}

export type SubscribeInput = {
  email: string;
  topics?: string[];
  frequency?: NewsletterSettings["defaultFrequency"];
  source?: string;
  locale?: string;
};

export type SubscribeResult = {
  ok: boolean;
  pending: boolean;
  message: string;
};

function rememberLocal(email: string) {
  try {
    const list = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? "[]") as string[];
    if (!list.includes(email)) {
      list.push(email);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    }
  } catch {
    /* ignore */
  }
}

export function hasLocalSubscription(email: string): boolean {
  try {
    return (JSON.parse(localStorage.getItem(STORAGE_KEY) ?? "[]") as string[]).includes(email);
  } catch {
    return false;
  }
}

export async function subscribeNewsletter(input: SubscribeInput): Promise<SubscribeResult> {
  const email = input.email.trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return { ok: false, pending: false, message: "Please enter a valid email address." };
  }
  const base = apiBase();
  if (!base) {
    rememberLocal(email);
    return { ok: true, pending: false, message: NEWSLETTER_DEFAULTS.successMessage };
  }
  try {
    const res = await fetch(`${base}/wp-json/kpopblog/v1/newsletter/subscribe`, {
      method: "POST",
      credentials: "omit",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        email,
        topics: input.topics ?? [],
        frequency: input.frequency,
        source: input.source ?? "site",
        locale: input.locale ?? (typeof navigator !== "undefined" ? navigator.language.slice(0, 2) : "en"),
      }),
    });
    const json = (await res.json().catch(() => ({}))) as Partial<SubscribeResult> & { message?: string };
    if (!res.ok) {
      return { ok: false, pending: false, message: json.message || "Subscription failed. Please try again." };
    }
    rememberLocal(email);
    return { ok: true, pending: !!json.pending, message: json.message || NEWSLETTER_DEFAULTS.successMessage };
  } catch {
    rememberLocal(email);
    return { ok: true, pending: false, message: NEWSLETTER_DEFAULTS.successMessage };
  }
}

export async function confirmNewsletter(token: string): Promise<SubscribeResult> {
  const base = apiBase();
  if (!base) return { ok: true, pending: false, message: NEWSLETTER_DEFAULTS.confirmMessage };
  try {
    const res = await fetch(`${base}/wp-json/kpopblog/v1/newsletter/confirm`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token }),
    });
    const json = (await res.json().catch(() => ({}))) as { message?: string };
    return { ok: res.ok, pending: false, message: json.message || NEWSLETTER_DEFAULTS.confirmMessage };
  } catch {
    return { ok: false, pending: false, message: "Confirmation failed." };
  }
}

export async function unsubscribeNewsletter(emailOrToken: { email?: string; token?: string }): Promise<{ ok: boolean }> {
  const base = apiBase();
  if (!base) return { ok: true };
  try {
    const res = await fetch(`${base}/wp-json/kpopblog/v1/newsletter/unsubscribe`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(emailOrToken),
    });
    return { ok: res.ok };
  } catch {
    return { ok: false };
  }
}
