import { useEffect, useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Mail, Loader2, Check, Bell, BellRing } from "lucide-react";
import {
  fetchNewsletterSettings,
  subscribeNewsletter,
  NEWSLETTER_DEFAULTS,
  type NewsletterSettings,
} from "@/services/newsletter";
import { localNotifications } from "@/services/notifications/local";
import { toast } from "sonner";


type Variant = "inline" | "card" | "footer";

export function NewsletterCTA({
  variant = "card",
  source = "site",
  className = "",
  showTopics = false,
}: {
  variant?: Variant;
  source?: string;
  className?: string;
  showTopics?: boolean;
}) {
  const [settings, setSettings] = useState<NewsletterSettings>(NEWSLETTER_DEFAULTS);
  const [email, setEmail] = useState("");
  const [topics, setTopics] = useState<string[]>([]);
  const [status, setStatus] = useState<"idle" | "loading" | "success" | "error">("idle");
  const [message, setMessage] = useState("");
  const fieldId = useId();

  useEffect(() => {
    let alive = true;
    fetchNewsletterSettings().then((s) => {
      if (!alive) return;
      setSettings(s);
      setTopics(s.availableTopics);
    });
    return () => {
      alive = false;
    };
  }, []);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("loading");
    const result = await subscribeNewsletter({
      email,
      topics: showTopics ? topics : undefined,
      frequency: settings.defaultFrequency,
      source,
    });
    setStatus(result.ok ? "success" : "error");
    setMessage(result.message);
    if (result.ok) setEmail("");
  }

  if (variant === "footer") {
    return (
      <div className={className}>
        <h4 className="font-semibold mb-3 flex items-center gap-2">
          <Mail className="size-4" /> Newsletter
        </h4>
        <p className="text-sm text-muted-foreground mb-3">{settings.cta.subheading}</p>
        <form onSubmit={onSubmit} className="flex gap-2">
          <Input
            type="email"
            required
            placeholder="you@example.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={status === "loading"}
            aria-label="Email address"
            className="h-9"
          />
          <Button type="submit" size="sm" disabled={status === "loading" || !email}>
            {status === "loading" ? <Loader2 className="size-4 animate-spin" /> : settings.cta.button}
          </Button>
        </form>
        {status !== "idle" && status !== "loading" && (
          <p className={`mt-2 text-xs ${status === "success" ? "text-primary" : "text-destructive"}`}>{message}</p>
        )}
      </div>
    );
  }

  if (variant === "inline") {
    return (
      <form onSubmit={onSubmit} className={`flex flex-col sm:flex-row gap-2 ${className}`}>
        <Input
          type="email"
          required
          placeholder="Enter your email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          disabled={status === "loading"}
          aria-label="Email address"
        />
        <Button type="submit" disabled={status === "loading" || !email}>
          {status === "loading" ? <Loader2 className="size-4 animate-spin" /> : settings.cta.button}
        </Button>
        {status === "success" && (
          <span className="text-sm text-primary inline-flex items-center gap-1"><Check className="size-4" /> {message}</span>
        )}
        {status === "error" && <span className="text-sm text-destructive">{message}</span>}
      </form>
    );
  }

  return (
    <section
      className={`relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-primary/10 via-card to-card p-6 md:p-10 ${className}`}
    >
      <div className="absolute -top-20 -right-20 size-64 rounded-full bg-primary/20 blur-3xl pointer-events-none" />
      <div className="relative max-w-2xl">
        <div className="text-xs uppercase tracking-widest text-primary font-semibold">{settings.cta.eyebrow}</div>
        <h2 className="font-display text-2xl md:text-3xl font-bold mt-2">{settings.cta.heading}</h2>
        <p className="text-muted-foreground mt-2">{settings.cta.subheading}</p>

        <form onSubmit={onSubmit} className="mt-5 flex flex-col sm:flex-row gap-2">
          <Input
            id={fieldId}
            type="email"
            required
            placeholder="you@example.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={status === "loading" || status === "success"}
            aria-label="Email address"
            className="h-11"
          />
          <Button type="submit" size="lg" disabled={status === "loading" || status === "success" || !email}>
            {status === "loading" ? <Loader2 className="size-4 animate-spin mr-2" /> : null}
            {status === "success" ? "Subscribed" : settings.cta.button}
          </Button>
        </form>

        {showTopics && settings.availableTopics.length > 0 && (
          <fieldset className="mt-4">
            <legend className="text-xs uppercase tracking-wider text-muted-foreground mb-2">Topics</legend>
            <div className="flex flex-wrap gap-2">
              {settings.availableTopics.map((t) => {
                const active = topics.includes(t);
                return (
                  <button
                    type="button"
                    key={t}
                    onClick={() => setTopics((prev) => (active ? prev.filter((x) => x !== t) : [...prev, t]))}
                    className={`px-3 py-1 rounded-full text-xs border transition ${
                      active
                        ? "bg-primary text-primary-foreground border-primary"
                        : "bg-card text-muted-foreground border-border hover:text-foreground"
                    }`}
                  >
                    {t.replace(/-/g, " ")}
                  </button>
                );
              })}
            </div>
          </fieldset>
        )}

        {status !== "idle" && status !== "loading" && (
          <p
            role="status"
            className={`mt-3 text-sm ${status === "success" ? "text-primary" : "text-destructive"}`}
          >
            {message}
          </p>
        )}
        {status === "success" && <PushOptIn />}
        <p className="mt-3 text-[11px] text-muted-foreground">
          By subscribing, you agree to our privacy policy. Unsubscribe with one click anytime.
        </p>
      </div>
    </section>
  );
}

function PushOptIn() {
  const [perm, setPerm] = useState<NotificationPermission>(() => localNotifications.permission());
  useEffect(() => { setPerm(localNotifications.permission()); }, []);
  if (typeof Notification === "undefined") return null;
  if (perm === "granted") {
    return (
      <p className="mt-3 text-xs text-primary inline-flex items-center gap-1.5">
        <BellRing className="size-3.5" /> Push reminders enabled — we'll ping you 15 min before comebacks drop.
      </p>
    );
  }
  if (perm === "denied") {
    return (
      <p className="mt-3 text-xs text-muted-foreground">
        Browser notifications are blocked. Enable them in your site settings to get comeback reminders.
      </p>
    );
  }
  return (
    <div className="mt-4 flex items-center gap-3 rounded-lg border border-dashed border-primary/30 bg-primary/5 p-3">
      <Bell className="size-4 text-primary shrink-0" />
      <div className="flex-1 text-xs">
        <div className="font-semibold">Also get browser reminders?</div>
        <div className="text-muted-foreground">Comebacks and poll deadlines, never miss a drop.</div>
      </div>
      <Button
        size="sm"
        variant="outline"
        onClick={async () => {
          const p = await localNotifications.requestPermission();
          setPerm(p);
          if (p === "granted") toast.success("Reminders enabled");
          else if (p === "denied") toast.error("Notifications blocked in browser settings");
        }}
      >
        Enable
      </Button>
    </div>
  );
}

