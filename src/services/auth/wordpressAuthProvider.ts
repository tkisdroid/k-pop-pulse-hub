/**
 * WordPress auth provider — cookie session + REST nonce against the
 * KpopBlog plugin's /wp-json/kpopblog/v1/auth/* endpoints (includes/auth.php).
 * Activated automatically when the React app is mounted inside WordPress
 * (window.kpopblogConfig is injected by the plugin) or when
 * VITE_WORDPRESS_API_URL is set during a standalone build.
 *
 * A wp_rest nonce is bound to the acting user ID, so every response that
 * changes login state carries a fresh nonce that must replace
 * window.kpopblogConfig.nonce — otherwise subsequent authenticated requests
 * (here and in wordpressProvider.ts) fail with a stale-nonce 403.
 */
import type { User } from "@/types";
import type { AuthProvider } from "./demoAuthProvider";
// window.kpopblogConfig is declared globally in wordpressProvider.ts (src/services/cms).

function getApiBase(): string {
  if (typeof window !== "undefined" && window.kpopblogConfig?.apiUrl) {
    return window.kpopblogConfig.apiUrl.replace(/\/$/, "");
  }
  const envUrl = import.meta.env?.VITE_WORDPRESS_API_URL as string | undefined;
  return (envUrl ?? "").replace(/\/$/, "");
}

interface AuthApiResponse {
  nonce: string;
  user?: User | null;
  ok?: boolean;
  message?: string;
}

async function authFetch<T extends AuthApiResponse>(
  path: string,
  method: "GET" | "POST",
  body?: unknown,
): Promise<T> {
  const base = getApiBase();
  if (!base) throw new Error("WordPress API URL not configured");
  const headers: Record<string, string> = { Accept: "application/json" };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (typeof window !== "undefined" && window.kpopblogConfig?.nonce) {
    headers["X-WP-Nonce"] = window.kpopblogConfig.nonce;
  }
  const res = await fetch(`${base}${path}`, {
    method,
    headers,
    credentials: "same-origin",
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const data = (await res.json().catch(() => ({}))) as T;
  if (!res.ok) throw new Error(data.message ?? `WP ${res.status}`);
  if (data.nonce && typeof window !== "undefined" && window.kpopblogConfig) {
    window.kpopblogConfig.nonce = data.nonce;
  }
  return data;
}

const listeners = new Set<(u: User | null) => void>();
function emit(u: User | null) {
  listeners.forEach((l) => l(u));
}

let cachedUser: User | null = null;

export const wordpressAuthProvider: AuthProvider = {
  name: "wordpress",
  getCurrentUser: () => cachedUser,

  async init() {
    try {
      const { user } = await authFetch<AuthApiResponse>("/auth/me", "GET");
      cachedUser = user ?? null;
    } catch {
      cachedUser = null;
    }
    emit(cachedUser);
    return cachedUser;
  },

  async signIn(email, password) {
    const { user } = await authFetch<AuthApiResponse>("/auth/login", "POST", {
      login: email,
      password,
    });
    cachedUser = user ?? null;
    emit(cachedUser);
    if (!cachedUser) throw new Error("Login failed");
    return cachedUser;
  },

  async signInDemo() {
    throw new Error("Demo login isn't available on WordPress — sign in with email and password.");
  },

  async signUp({ email, username, displayName, password }) {
    const { user } = await authFetch<AuthApiResponse>("/auth/register", "POST", {
      email,
      username,
      displayName,
      password,
    });
    cachedUser = user ?? null;
    emit(cachedUser);
    if (!cachedUser) throw new Error("Registration failed");
    return cachedUser;
  },

  async signInWithProvider(provider) {
    return { pending: true, message: `${provider} login isn't available yet on WordPress.` };
  },

  async signOut() {
    await authFetch<AuthApiResponse>("/auth/logout", "POST");
    cachedUser = null;
    emit(null);
  },

  onChange(cb) {
    listeners.add(cb);
    return () => listeners.delete(cb);
  },

  async requestPasswordReset(email) {
    const r = await authFetch<AuthApiResponse>("/auth/forgot-password", "POST", { email });
    return {
      ok: r.ok ?? true,
      message: r.message ?? "If an account exists for that email, a reset link has been sent.",
    };
  },
};
