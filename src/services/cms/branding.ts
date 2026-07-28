/**
 * Branding — reads the design settings the WordPress plugin injects via
 * window.kpopblogConfig.branding (see wordpress-plugin/kpopblog/includes/design.php).
 * Outside WordPress (standalone preview/SSR) it is a no-op, so the built-in
 * look is untouched and server/client first renders agree.
 *
 * The accent is applied from a React effect (see RootComponent) rather than at
 * module top level: the WordPress page goes through an intermediate document
 * state on load, so DOM writes made at module-eval time are discarded before
 * the final document settles. Effects run against the final document.
 */

export interface Branding {
  siteName: string;
  accentWord: string;
  tagline: string;
  accentColor: string;
  logoUrl: string;
}

const DEFAULTS: Branding = {
  siteName: "Kpop",
  accentWord: "Blog",
  tagline: "",
  accentColor: "",
  logoUrl: "",
};

function inWordPress(): boolean {
  return typeof window !== "undefined" && Boolean(window.kpopblogConfig?.apiUrl);
}

export function getBranding(): Branding {
  if (typeof window === "undefined") return DEFAULTS;
  const b = window.kpopblogConfig?.branding;
  if (!b) return DEFAULTS;
  return {
    siteName: b.siteName ?? DEFAULTS.siteName,
    accentWord: b.accentWord ?? DEFAULTS.accentWord,
    tagline: b.tagline ?? DEFAULTS.tagline,
    accentColor: b.accentColor ?? DEFAULTS.accentColor,
    logoUrl: b.logoUrl ?? DEFAULTS.logoUrl,
  };
}

/** Full wordmark text, e.g. "Kpop" + "Blog" → "KpopBlog". */
export function brandTitle(b: Branding = getBranding()): string {
  return `${b.siteName}${b.accentWord}`.trim();
}

/**
 * Apply the admin-configured accent color to the theme CSS variables. Only
 * acts inside WordPress; the override wins over both the light and dark theme
 * rules (inline custom properties beat the theme class), so the brand color
 * stays consistent across modes.
 */
export function applyBranding(): void {
  if (!inWordPress()) return;
  const b = getBranding();
  if (!b.accentColor) return;
  const root = document.documentElement;
  root.style.setProperty("--primary", b.accentColor);
  root.style.setProperty("--ring", b.accentColor);
  // Collapse the neon gradient to the brand color so the wordmark and logo
  // mark read as solid brand color rather than the default pink/violet/cyan.
  root.style.setProperty("--neon-pink", b.accentColor);
  root.style.setProperty("--neon-violet", b.accentColor);
  root.style.setProperty("--neon-cyan", b.accentColor);
}
