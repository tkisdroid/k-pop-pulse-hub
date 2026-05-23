/**
 * Lightweight notification center. Stores items in localStorage so the
 * inbox survives page reloads even without a backend. Designed to be
 * driven by the WordPress plugin (or any future server) via push: just
 * call `notify({...})` from anywhere.
 */
export type NotificationKind = "comeback" | "article" | "reply" | "follow" | "system";

export interface NotificationItem {
  id: string;
  kind: NotificationKind;
  title: string;
  body?: string;
  href?: string;
  image?: string;
  read: boolean;
  createdAt: string;
}

const KEY = "kpopblog:notifications";
const LIMIT = 50;

type Listener = (items: NotificationItem[]) => void;
const listeners = new Set<Listener>();

function read(): NotificationItem[] {
  if (typeof localStorage === "undefined") return [];
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return seedDemo();
    const parsed = JSON.parse(raw) as NotificationItem[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function write(items: NotificationItem[]) {
  if (typeof localStorage === "undefined") return;
  const trimmed = items.slice(0, LIMIT);
  localStorage.setItem(KEY, JSON.stringify(trimmed));
  listeners.forEach((l) => l(trimmed));
}

function seedDemo(): NotificationItem[] {
  const now = Date.now();
  const seed: NotificationItem[] = [
    { id: "n1", kind: "comeback", title: "NewJeans comeback in 3 days", body: "Mark your calendar for the new single drop.", href: "/comebacks", read: false, createdAt: new Date(now - 60_000).toISOString() },
    { id: "n2", kind: "article", title: "BTS world tour rumor confirmed", href: "/latest", read: false, createdAt: new Date(now - 3 * 60_000).toISOString() },
    { id: "n3", kind: "reply", title: "@stan_kpop replied to your comment", href: "/forum", read: true, createdAt: new Date(now - 60 * 60_000).toISOString() },
  ];
  write(seed);
  return seed;
}

export const notifications = {
  list(): NotificationItem[] {
    return read();
  },
  unreadCount(): number {
    return read().filter((n) => !n.read).length;
  },
  subscribe(l: Listener): () => void {
    listeners.add(l);
    l(read());
    return () => listeners.delete(l);
  },
  notify(input: Omit<NotificationItem, "id" | "read" | "createdAt"> & { id?: string }) {
    const items = read();
    const item: NotificationItem = {
      id: input.id ?? `n_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
      read: false,
      createdAt: new Date().toISOString(),
      ...input,
    };
    write([item, ...items.filter((i) => i.id !== item.id)]);
    return item;
  },
  markRead(id: string) {
    write(read().map((n) => (n.id === id ? { ...n, read: true } : n)));
  },
  markAllRead() {
    write(read().map((n) => ({ ...n, read: true })));
  },
  clear() {
    write([]);
  },
};
