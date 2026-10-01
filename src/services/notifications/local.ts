/**
 * Local notification scheduler.
 *
 * Schedules browser Notifications for comeback releases and poll deadlines
 * using setTimeout for the in-tab session, and persists reminders in
 * localStorage so they survive reloads. On boot, due reminders fire
 * immediately and future ones get rescheduled.
 *
 * Because the project has no push backend yet, notifications only fire
 * while a tab is open. If the user installs the PWA, the service worker
 * keeps Notifications available for a bit longer, but true offline /
 * background push will need a server (see push.ts wiring point).
 */

const KEY = "kpopblog:reminders";
const MAX = 200;

export type ReminderKind = "comeback" | "poll";

export interface Reminder {
  id: string;
  kind: ReminderKind;
  title: string;
  body?: string;
  url?: string;
  icon?: string;
  /** ISO timestamp of when to fire. */
  fireAt: string;
  /** Optional cooldown lead time in minutes (e.g. 15 = fire 15 min before fireAt). */
  leadMinutes?: number;
  createdAt: string;
}

function read(): Reminder[] {
  if (typeof localStorage === "undefined") return [];
  try {
    return JSON.parse(localStorage.getItem(KEY) ?? "[]");
  } catch {
    return [];
  }
}

function write(list: Reminder[]) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX)));
  try {
    window.dispatchEvent(new CustomEvent("reminders:changed"));
  } catch {
    // Storage was updated; notification is best effort outside browser contexts.
  }
}

const timers = new Map<string, ReturnType<typeof setTimeout>>();

function fireTime(r: Reminder): number {
  const at = +new Date(r.fireAt);
  const lead = (r.leadMinutes ?? 0) * 60_000;
  return at - lead;
}

async function ensurePermission(): Promise<NotificationPermission> {
  if (typeof Notification === "undefined") return "denied";
  if (Notification.permission === "granted" || Notification.permission === "denied")
    return Notification.permission;
  try {
    return await Notification.requestPermission();
  } catch {
    return "denied";
  }
}

function show(r: Reminder) {
  if (typeof Notification === "undefined" || Notification.permission !== "granted") return;
  try {
    // Prefer the service worker so the click can re-open the tab even if closed.
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.getRegistration().then((reg) => {
        if (reg) {
          reg.showNotification(r.title, {
            body: r.body,
            icon: r.icon || "/icon-192.png",
            badge: "/icon-192.png",
            tag: r.id,
            data: { url: r.url || "/" },
          });
        } else {
          new Notification(r.title, { body: r.body, icon: r.icon || "/icon-192.png", tag: r.id });
        }
      });
    } else {
      new Notification(r.title, { body: r.body, icon: r.icon || "/icon-192.png", tag: r.id });
    }
  } catch {
    /* ignore */
  }
}

function schedule(r: Reminder) {
  if (typeof window === "undefined") return;
  const delay = fireTime(r) - Date.now();
  if (delay <= 0) {
    show(r);
    remove(r.id);
    return;
  }
  // setTimeout uses int32 — cap to ~24 days; longer reminders rearm on next boot.
  const safeDelay = Math.min(delay, 2_000_000_000);
  const t = setTimeout(() => {
    show(r);
    remove(r.id);
  }, safeDelay);
  timers.set(r.id, t);
}

function remove(id: string) {
  const t = timers.get(id);
  if (t) clearTimeout(t);
  timers.delete(id);
  write(read().filter((r) => r.id !== id));
}

export const localNotifications = {
  list: read,
  has(id: string) {
    return read().some((r) => r.id === id);
  },
  permission(): NotificationPermission {
    return typeof Notification === "undefined" ? "denied" : Notification.permission;
  },
  async requestPermission() {
    return ensurePermission();
  },
  async add(
    r: Omit<Reminder, "createdAt"> & { createdAt?: string },
  ): Promise<{ ok: boolean; reason?: string }> {
    const perm = await ensurePermission();
    if (perm !== "granted") return { ok: false, reason: perm };
    const list = read().filter((x) => x.id !== r.id);
    const entry: Reminder = { createdAt: new Date().toISOString(), ...r };
    list.unshift(entry);
    write(list);
    schedule(entry);
    return { ok: true };
  },
  remove,
  clear() {
    timers.forEach((t) => clearTimeout(t));
    timers.clear();
    write([]);
  },
  /** Call once on app boot to (re)arm all stored reminders. */
  hydrate() {
    if (typeof window === "undefined") return;
    timers.forEach((t) => clearTimeout(t));
    timers.clear();
    const list = read();
    const due: Reminder[] = [];
    const keep: Reminder[] = [];
    for (const r of list) {
      if (fireTime(r) <= Date.now()) due.push(r);
      else keep.push(r);
    }
    if (due.length) {
      // Fire backlog quickly, then persist what's left.
      due.forEach(show);
      write(keep);
    }
    keep.forEach(schedule);
  },
};
