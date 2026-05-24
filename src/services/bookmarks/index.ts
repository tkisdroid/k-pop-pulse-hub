/**
 * Client-side bookmarks ("Save for later").
 * Stored in localStorage; survives across sessions on the device.
 */
const KEY = "kpopblog:bookmarks";

type Bookmark = { id: string; slug: string; title: string; image?: string; savedAt: string };

function read(): Bookmark[] {
  if (typeof localStorage === "undefined") return [];
  try {
    return JSON.parse(localStorage.getItem(KEY) ?? "[]");
  } catch {
    return [];
  }
}

function write(list: Bookmark[]) {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(KEY, JSON.stringify(list));
  try {
    window.dispatchEvent(new CustomEvent("bookmarks:changed"));
  } catch {}
}

export const bookmarks = {
  list: read,
  has(id: string) {
    return read().some((b) => b.id === id);
  },
  toggle(b: Omit<Bookmark, "savedAt">): boolean {
    const list = read();
    const existing = list.findIndex((x) => x.id === b.id);
    if (existing >= 0) {
      list.splice(existing, 1);
      write(list);
      return false;
    }
    list.unshift({ ...b, savedAt: new Date().toISOString() });
    write(list.slice(0, 200));
    return true;
  },
  remove(id: string) {
    write(read().filter((b) => b.id !== id));
  },
  clear() {
    write([]);
  },
};

export type { Bookmark };
