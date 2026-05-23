import { useEffect, useRef, useState } from "react";
import { Bell, Calendar, MessageCircle, Newspaper, UserPlus, Sparkles, Check } from "lucide-react";
import { Link } from "@tanstack/react-router";
import { notifications, type NotificationItem, type NotificationKind } from "@/services/notifications/store";

const ICON: Record<NotificationKind, typeof Bell> = {
  comeback: Calendar,
  article: Newspaper,
  reply: MessageCircle,
  follow: UserPlus,
  system: Sparkles,
};

function timeAgo(iso: string) {
  const s = Math.floor((Date.now() - +new Date(iso)) / 1000);
  if (s < 60) return `${s}s`;
  if (s < 3600) return `${Math.floor(s / 60)}m`;
  if (s < 86400) return `${Math.floor(s / 3600)}h`;
  return `${Math.floor(s / 86400)}d`;
}

export function NotificationCenter() {
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<NotificationItem[]>([]);
  const popRef = useRef<HTMLDivElement>(null);

  useEffect(() => notifications.subscribe(setItems), []);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (popRef.current && !popRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onDown);
    return () => document.removeEventListener("mousedown", onDown);
  }, [open]);

  const unread = items.filter((n) => !n.read).length;

  return (
    <div className="relative" ref={popRef}>
      <button
        onClick={() => setOpen((o) => !o)}
        className="p-2 rounded-md hover:bg-accent relative"
        aria-label="Notifications"
      >
        <Bell className="size-5" />
        {unread > 0 && (
          <span className="absolute top-1 right-1 min-w-4 h-4 px-1 rounded-full bg-primary text-primary-foreground text-[10px] font-bold grid place-items-center">
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </button>
      {open && (
        <div className="absolute right-0 mt-2 w-80 rounded-xl border border-border bg-popover shadow-xl z-50 overflow-hidden">
          <div className="flex items-center justify-between px-3 py-2 border-b border-border">
            <div className="font-semibold text-sm">Notifications</div>
            <button
              onClick={() => notifications.markAllRead()}
              className="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1"
            >
              <Check className="size-3" /> Mark all read
            </button>
          </div>
          <div className="max-h-96 overflow-y-auto">
            {items.length === 0 && (
              <div className="p-6 text-center text-sm text-muted-foreground">You're all caught up.</div>
            )}
            {items.map((n) => {
              const Icon = ICON[n.kind] ?? Bell;
              const body = (
                <div className={`flex gap-3 p-3 border-b border-border/60 hover:bg-accent/40 ${n.read ? "opacity-60" : ""}`}>
                  <div className="size-8 rounded-full bg-accent grid place-items-center shrink-0">
                    <Icon className="size-4 text-primary" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-medium leading-snug line-clamp-2">{n.title}</div>
                    {n.body && <div className="text-xs text-muted-foreground line-clamp-2">{n.body}</div>}
                    <div className="text-[10px] text-muted-foreground mt-1">{timeAgo(n.createdAt)} ago</div>
                  </div>
                  {!n.read && <span className="size-2 rounded-full bg-primary mt-2 shrink-0" />}
                </div>
              );
              return n.href ? (
                <Link
                  key={n.id}
                  to={n.href}
                  onClick={() => {
                    notifications.markRead(n.id);
                    setOpen(false);
                  }}
                >
                  {body}
                </Link>
              ) : (
                <button key={n.id} onClick={() => notifications.markRead(n.id)} className="w-full text-left">
                  {body}
                </button>
              );
            })}
          </div>
          <div className="px-3 py-2 border-t border-border text-xs text-muted-foreground text-center">
            Live updates via WordPress webhook
          </div>
        </div>
      )}
    </div>
  );
}
