/**
 * PWA service-worker registration with strict Lovable-preview guards.
 * - Skipped inside iframes (Lovable editor preview).
 * - Skipped on preview hostnames (id-preview--*, lovableproject.com).
 * - Skipped in dev (import.meta.env.DEV).
 * - Existing SWs are unregistered in those contexts to clean up stale installs.
 */
export function registerPwa() {
  if (typeof window === "undefined" || !("serviceWorker" in navigator)) return;

  const isInIframe = (() => {
    try {
      return window.self !== window.top;
    } catch {
      return true;
    }
  })();
  const host = window.location.hostname;
  const isPreviewHost =
    host.includes("id-preview--") || host.includes("lovableproject.com");
  const isDev = (import.meta as any).env?.DEV === true;

  if (isInIframe || isPreviewHost || isDev) {
    // Kill any stale registration so the preview never serves cached HTML.
    navigator.serviceWorker.getRegistrations().then((rs) => rs.forEach((r) => r.unregister())).catch(() => {});
    return;
  }

  import("workbox-window")
    .then(({ Workbox }) => {
      const wb = new Workbox("/sw.js");
      wb.addEventListener("waiting", () => {
        // New version available — activate on next navigation.
        wb.messageSkipWaiting();
      });
      wb.register().catch((e) => console.warn("[pwa] register failed", e));
    })
    .catch(() => {});
}
