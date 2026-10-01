import { useEffect, useRef } from "react";
import { useRouterState } from "@tanstack/react-router";
import { useConsent } from "@/hooks/useConsent";
import { useAuth } from "@/hooks/useAuth";

const VISITOR_KEY = "kpopblog:analytics-visitor:v1";
const PUBLIC_PATH =
  /^\/(?:latest|trending|artists|comebacks|charts|videos|community|forum|polls|quiz|about|(?:news|artist|member|thread|watch|polls|forum|category|tag|author)\/[a-zA-Z0-9_-]+)?\/?$/;

/** Browser-side collection counts cached pages and SPA navigation too. */
export function TrafficTracker() {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const { preferences } = useConsent();
  const { user, loading } = useAuth();
  const lastPath = useRef<string | null>(null);

  useEffect(() => {
    if (!preferences?.analytics) {
      lastPath.current = null;
      try {
        localStorage.removeItem(VISITOR_KEY);
      } catch {
        /* Storage may be disabled. */
      }
      return;
    }
    const config = window.kpopblogConfig;
    if (loading) return;
    if (
      !config?.apiUrl ||
      config.adminUrl ||
      user?.role === "admin" ||
      !PUBLIC_PATH.test(pathname)
    ) {
      lastPath.current = null;
      return;
    }
    if (lastPath.current === pathname) return;
    try {
      // No identifier is created before consent. It expires after 90 days.
      const saved = JSON.parse(localStorage.getItem(VISITOR_KEY) ?? "null") as {
        id?: string;
        expires?: number;
      } | null;
      let visitor = saved?.id;
      if (
        !visitor ||
        !/^[a-f0-9-]{36}$/.test(visitor) ||
        !saved?.expires ||
        saved.expires < Date.now()
      ) {
        visitor = crypto.randomUUID();
        localStorage.setItem(
          VISITOR_KEY,
          JSON.stringify({ id: visitor, expires: Date.now() + 90 * 86400000 }),
        );
      }
      lastPath.current = pathname;
      void fetch(`${config.apiUrl.replace(/\/$/, "")}/analytics/pageview`, {
        method: "POST",
        credentials: "omit",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          consent: true,
          path: pathname,
          visitor,
          event: crypto.randomUUID(),
        }),
        keepalive: true,
      }).catch(() => {
        /* Collection must never interrupt browsing. */
      });
    } catch {
      /* Browsers without storage or secure UUIDs are not tracked. */
    }
  }, [pathname, preferences?.analytics, user?.role, loading]);

  return null;
}
