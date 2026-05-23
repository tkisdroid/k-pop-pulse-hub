/**
 * Real-time notification poller.
 *
 * Cursors through GET /wp-json/kpopblog/v1/events?since=<id> at a brisk
 * interval while the tab is visible, slows down when hidden, and merges
 * new events into the in-app notification store. The store dedupes by ID,
 * so polling is safe to run from multiple tabs concurrently.
 *
 * WordPress is the source of truth — server-side hooks emit events for
 * comments, follows, subscribes, new articles, and comebacks. This poller
 * is the in-app consumer; an HMAC-signed outbound webhook is also fired
 * by the plugin for external listeners.
 */
import { notifications, type NotificationKind } from "./store";

const CURSOR_KEY = "kpopblog:events:cursor";
const ACTIVE_MS = 15_000;
const IDLE_MS = 60_000;

type RemoteEvent = {
  id: number;
  kind: NotificationKind | string;
  payload: { title?: string; body?: string; href?: string; image?: string };
  createdAt: string;
};

function getApiBase(): string | null {
  if (typeof window !== "undefined") {
    const cfg = (window as any).kpopblogConfig;
    if (cfg?.apiUrl) return String(cfg.apiUrl).replace(/\/$/, "");
  }
  const env = (import.meta as any).env?.VITE_WORDPRESS_API_URL as string | undefined;
  return env ? env.replace(/\/$/, "") : null;
}

function readCursor(): number {
  if (typeof localStorage === "undefined") return 0;
  return parseInt(localStorage.getItem(CURSOR_KEY) ?? "0", 10) || 0;
}

function writeCursor(v: number) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(CURSOR_KEY, String(v));
}

let timer: number | null = null;
let inFlight = false;
let started = false;

async function poll() {
  if (inFlight) return;
  const base = getApiBase();
  if (!base) return;
  inFlight = true;
  try {
    const since = readCursor();
    const nonce = typeof window !== "undefined" ? (window as any).kpopblogConfig?.nonce : undefined;
    const res = await fetch(`${base}/events?since=${since}&limit=50`, {
      headers: nonce ? { "X-WP-Nonce": nonce } : undefined,
      credentials: nonce ? "include" : "same-origin",
    });
    if (!res.ok) return;
    const json = (await res.json()) as { events: RemoteEvent[]; cursor: number };
    for (const ev of json.events ?? []) {
      const kind = (["comeback", "article", "reply", "follow", "system"] as const).includes(ev.kind as never)
        ? (ev.kind as NotificationKind)
        : "system";
      notifications.notify({
        id: `wp_${ev.id}`,
        kind,
        title: ev.payload?.title ?? "Update",
        body: ev.payload?.body,
        href: ev.payload?.href,
        image: ev.payload?.image,
      });
    }
    if (typeof json.cursor === "number") writeCursor(json.cursor);
  } catch (e) {
    console.warn("[notifications/realtime]", e);
  } finally {
    inFlight = false;
  }
}

function schedule() {
  if (timer != null) window.clearInterval(timer);
  const visible = typeof document !== "undefined" ? document.visibilityState === "visible" : true;
  timer = window.setInterval(poll, visible ? ACTIVE_MS : IDLE_MS);
}

export function startNotificationRealtime() {
  if (started || typeof window === "undefined") return;
  if (!getApiBase()) return; // no WP backend → nothing to poll
  started = true;
  poll();
  schedule();
  document.addEventListener("visibilitychange", () => {
    schedule();
    if (document.visibilityState === "visible") poll();
  });
  window.addEventListener("focus", poll);
}

export function stopNotificationRealtime() {
  if (timer != null) window.clearInterval(timer);
  timer = null;
  started = false;
}
