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

type InboxResponse = {
  items: NotificationItem[];
  total: number;
  unreadCount: number;
};

type WordPressConfig = {
  apiUrl?: string;
  nonce?: string;
};

const KEY = "kpopblog:notifications";
const LIMIT = 50;
type Listener = (items: NotificationItem[]) => void;
const listeners = new Set<Listener>();
let serverItems: NotificationItem[] = [];

function wordpressConfig(): WordPressConfig | null {
  if (typeof window === "undefined") return null;
  return (window as typeof window & { kpopblogConfig?: WordPressConfig }).kpopblogConfig ?? null;
}

function isWordPressMode(): boolean {
  return Boolean(wordpressConfig()?.apiUrl);
}

function seedDemo(): NotificationItem[] {
  const now = Date.now();
  return [
    {
      id: "n1",
      kind: "comeback",
      title: "NewJeans comeback in 3 days",
      body: "Mark your calendar for the new single drop.",
      href: "/comebacks",
      read: false,
      createdAt: new Date(now - 60_000).toISOString(),
    },
    {
      id: "n2",
      kind: "article",
      title: "BTS world tour rumor confirmed",
      href: "/latest",
      read: false,
      createdAt: new Date(now - 3 * 60_000).toISOString(),
    },
    {
      id: "n3",
      kind: "reply",
      title: "@stan_kpop replied to your comment",
      href: "/forum",
      read: true,
      createdAt: new Date(now - 60 * 60_000).toISOString(),
    },
  ];
}

function readDemo(): NotificationItem[] {
  if (typeof localStorage === "undefined") return [];
  try {
    const raw = localStorage.getItem(KEY);
    if (raw) {
      const parsed = JSON.parse(raw) as NotificationItem[];
      return Array.isArray(parsed) ? parsed : [];
    }
    const seeded = seedDemo();
    localStorage.setItem(KEY, JSON.stringify(seeded));
    return seeded;
  } catch {
    return [];
  }
}

function read(): NotificationItem[] {
  return isWordPressMode() ? serverItems : readDemo();
}

function emit(items: NotificationItem[]) {
  listeners.forEach((listener) => listener(items));
}

function writeDemo(items: NotificationItem[]) {
  if (typeof localStorage === "undefined") return;
  const trimmed = items.slice(0, LIMIT);
  localStorage.setItem(KEY, JSON.stringify(trimmed));
  emit(trimmed);
}

async function request(path: string, init?: RequestInit): Promise<Response> {
  const config = wordpressConfig();
  if (!config?.apiUrl) throw new Error("WordPress notification API is unavailable.");
  const headers = new Headers(init?.headers);
  if (config.nonce) headers.set("X-WP-Nonce", config.nonce);
  return fetch(`${config.apiUrl.replace(/\/$/, "")}${path}`, {
    ...init,
    headers,
    credentials: "same-origin",
  });
}

export const notifications = {
  list(): NotificationItem[] {
    return read();
  },
  unreadCount(): number {
    return read().filter((notification) => !notification.read).length;
  },
  subscribe(listener: Listener): () => void {
    listeners.add(listener);
    listener(read());
    return () => listeners.delete(listener);
  },
  async refresh(): Promise<boolean> {
    if (!isWordPressMode()) {
      emit(readDemo());
      return true;
    }
    serverItems = [];
    emit(serverItems);
    try {
      const response = await request("/notifications?perPage=50");
      if (!response.ok) return false;
      const data = (await response.json()) as InboxResponse;
      serverItems = Array.isArray(data.items) ? data.items : [];
      emit(serverItems);
      return true;
    } catch {
      return false;
    }
  },
  notify(input: Omit<NotificationItem, "id" | "read" | "createdAt"> & { id?: string }) {
    const item: NotificationItem = {
      id: input.id ?? `n_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
      read: false,
      createdAt: new Date().toISOString(),
      ...input,
    };
    if (!isWordPressMode()) {
      writeDemo([item, ...readDemo().filter((existing) => existing.id !== item.id)]);
    }
    return item;
  },
  async markRead(id: string): Promise<boolean> {
    if (!isWordPressMode()) {
      writeDemo(
        readDemo().map((notification) =>
          notification.id === id ? { ...notification, read: true } : notification,
        ),
      );
      return true;
    }
    try {
      const response = await request(`/notifications/${encodeURIComponent(id)}/read`, {
        method: "POST",
      });
      if (!response.ok) return false;
      serverItems = serverItems.map((notification) =>
        notification.id === id ? { ...notification, read: true } : notification,
      );
      emit(serverItems);
      return true;
    } catch {
      return false;
    }
  },
  async markAllRead(): Promise<boolean> {
    if (!isWordPressMode()) {
      writeDemo(readDemo().map((notification) => ({ ...notification, read: true })));
      return true;
    }
    try {
      const response = await request("/notifications/read-all", { method: "POST" });
      if (!response.ok) return false;
      serverItems = serverItems.map((notification) => ({ ...notification, read: true }));
      emit(serverItems);
      return true;
    } catch {
      return false;
    }
  },
  clear() {
    if (isWordPressMode()) {
      serverItems = [];
      emit(serverItems);
      return;
    }
    writeDemo([]);
  },
};
