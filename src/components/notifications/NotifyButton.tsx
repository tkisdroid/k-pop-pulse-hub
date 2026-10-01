import { useEffect, useState } from "react";
import { Bell, BellOff, BellRing } from "lucide-react";
import { Button } from "@/components/ui/button";
import { localNotifications, type Reminder } from "@/services/notifications/local";
import { toast } from "sonner";

interface Props {
  reminder: Omit<Reminder, "createdAt">;
  /** Render style. */
  size?: "sm" | "default";
  variant?: "outline" | "secondary" | "ghost";
  className?: string;
  /** Optional label for the "off" state (default: "Remind me"). */
  label?: string;
}

/**
 * "Remind me" toggle that schedules a local browser notification at
 * `reminder.fireAt - reminder.leadMinutes`. Persists in localStorage and
 * re-arms on next visit.
 */
export function NotifyButton({
  reminder,
  size = "sm",
  variant = "outline",
  className,
  label = "Remind",
}: Props) {
  const [on, setOn] = useState(false);
  const [perm, setPerm] = useState<NotificationPermission>("default");
  // Notification only exists in browsers; checking it during render would make
  // SSR output (null) disagree with the client render (button) and break hydration.
  const [supported, setSupported] = useState(false);

  useEffect(() => {
    setSupported(typeof Notification !== "undefined");
    setOn(localNotifications.has(reminder.id));
    setPerm(localNotifications.permission());
    const onChange = () => setOn(localNotifications.has(reminder.id));
    window.addEventListener("reminders:changed", onChange);
    return () => window.removeEventListener("reminders:changed", onChange);
  }, [reminder.id]);

  if (!supported) return null;

  async function toggle() {
    if (on) {
      localNotifications.remove(reminder.id);
      setOn(false);
      toast("Reminder removed");
      return;
    }
    const result = await localNotifications.add(reminder);
    if (!result.ok) {
      setPerm(localNotifications.permission());
      toast.error(
        result.reason === "denied"
          ? "Notifications blocked in browser settings"
          : "Allow notifications to get reminders",
      );
      return;
    }
    setOn(true);
    const ms = +new Date(reminder.fireAt) - (reminder.leadMinutes ?? 0) * 60_000 - Date.now();
    const minutes = Math.max(0, Math.round(ms / 60_000));
    toast.success(minutes > 60 ? `Reminder set` : `Reminder set — firing in ~${minutes} min`);
  }

  const Icon = on ? BellRing : perm === "denied" ? BellOff : Bell;
  return (
    <Button
      size={size}
      variant={on ? "secondary" : variant}
      onClick={toggle}
      className={className}
      aria-pressed={on}
      title={on ? "Cancel reminder" : "Get a browser notification"}
    >
      <Icon className="size-4" />
      <span className="ml-1.5">{on ? "On" : label}</span>
    </Button>
  );
}
