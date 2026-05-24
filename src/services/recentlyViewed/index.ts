/**
 * Recently viewed articles, sessionStorage-scoped.
 * Resets when the tab closes — privacy-friendly and always fresh.
 */
const KEY = "kpopblog:recentlyViewed";
const LIMIT = 12;

type Entry = { id: string; slug: string; title: string; image?: string; viewedAt: string };

function read(): Entry[] {
  if (typeof sessionStorage === "undefined") return [];
  try {
    return JSON.parse(sessionStorage.getItem(KEY) ?? "[]");
  } catch {
    return [];
  }
}

function write(list: Entry[]) {
  if (typeof sessionStorage === "undefined") return;
  sessionStorage.setItem(KEY, JSON.stringify(list));
}

export const recentlyViewed = {
  list: read,
  push(e: Omit<Entry, "viewedAt">) {
    const list = read().filter((x) => x.id !== e.id);
    list.unshift({ ...e, viewedAt: new Date().toISOString() });
    write(list.slice(0, LIMIT));
  },
  clear() {
    write([]);
  },
};

export type { Entry as RecentlyViewedEntry };
