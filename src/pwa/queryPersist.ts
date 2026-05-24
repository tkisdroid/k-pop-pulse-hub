/**
 * Persist React Query cache to localStorage so previously-loaded articles
 * stay readable offline (and across reloads). Cache key bumps on schema
 * changes via buster so stale shapes never poison new builds.
 *
 * Skipped in iframes / Lovable preview / dev — same guard as PWA registration.
 */
import type { QueryClient } from "@tanstack/react-query";
import { persistQueryClient } from "@tanstack/react-query-persist-client";
import { createSyncStoragePersister } from "@tanstack/query-sync-storage-persister";

const CACHE_BUSTER = "v1";
const MAX_AGE_MS = 1000 * 60 * 60 * 24 * 7; // 7 days

export function setupQueryPersistence(queryClient: QueryClient) {
  if (typeof window === "undefined" || typeof localStorage === "undefined") return;

  const isInIframe = (() => {
    try { return window.self !== window.top; } catch { return true; }
  })();
  const host = window.location.hostname;
  const isPreviewHost = host.includes("id-preview--") || host.includes("lovableproject.com");
  const isDev = (import.meta as any).env?.DEV === true;
  if (isInIframe || isPreviewHost || isDev) return;

  // gcTime must exceed maxAge so persisted entries aren't dropped on hydration.
  queryClient.setDefaultOptions({
    queries: { gcTime: MAX_AGE_MS, staleTime: 60_000, networkMode: "offlineFirst" },
  });

  const persister = createSyncStoragePersister({
    storage: window.localStorage,
    key: "kpopblog:rq-cache",
    throttleTime: 1500,
  });

  persistQueryClient({
    queryClient,
    persister,
    maxAge: MAX_AGE_MS,
    buster: CACHE_BUSTER,
    dehydrateOptions: {
      // Only persist successful, finite-size query results.
      shouldDehydrateQuery: (q) =>
        q.state.status === "success" && (q.state.data == null || JSON.stringify(q.state.data).length < 200_000),
    },
  });
}
