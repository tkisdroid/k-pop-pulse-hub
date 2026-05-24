import { useEffect, useState } from "react";
import { WifiOff } from "lucide-react";

/**
 * Tiny pill shown when the browser reports offline. Articles already
 * loaded continue to render from the SW cache + persisted React Query.
 */
export function OfflineBadge() {
  const [online, setOnline] = useState(true);
  useEffect(() => {
    setOnline(navigator.onLine);
    const on = () => setOnline(true);
    const off = () => setOnline(false);
    window.addEventListener("online", on);
    window.addEventListener("offline", off);
    return () => {
      window.removeEventListener("online", on);
      window.removeEventListener("offline", off);
    };
  }, []);
  if (online) return null;
  return (
    <div
      role="status"
      className="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 inline-flex items-center gap-2 rounded-full bg-foreground text-background px-3 py-1.5 text-xs font-medium shadow-lg"
    >
      <WifiOff className="size-3.5" /> Offline — reading from cache
    </div>
  );
}
