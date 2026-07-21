import { notifications } from "./store";

const ACTIVE_MS = 15_000;
const IDLE_MS = 60_000;
let timer: number | null = null;
let started = false;
let inFlight = false;

async function poll() {
  if (inFlight) return;
  inFlight = true;
  try {
    await notifications.refresh();
  } finally {
    inFlight = false;
  }
}

function schedule() {
  if (timer !== null) window.clearInterval(timer);
  timer = window.setInterval(poll, document.visibilityState === "visible" ? ACTIVE_MS : IDLE_MS);
}

function handleVisibilityChange() {
  schedule();
  if (document.visibilityState === "visible") void poll();
}

function handleFocus() {
  void poll();
}

export function startNotificationRealtime() {
  if (started || typeof window === "undefined") return;
  started = true;
  void poll();
  schedule();
  document.addEventListener("visibilitychange", handleVisibilityChange);
  window.addEventListener("focus", handleFocus);
}

export function stopNotificationRealtime() {
  if (typeof window === "undefined") return;
  if (timer !== null) window.clearInterval(timer);
  timer = null;
  started = false;
  document.removeEventListener("visibilitychange", handleVisibilityChange);
  window.removeEventListener("focus", handleFocus);
  notifications.clear();
}
