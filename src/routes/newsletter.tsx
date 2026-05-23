import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { z } from "zod";
import { confirmNewsletter, unsubscribeNewsletter } from "@/services/newsletter";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { CheckCircle2, Loader2, XCircle } from "lucide-react";

const search = z.object({
  token: z.string().min(8).max(128).optional(),
  action: z.enum(["confirm", "unsubscribe"]).optional(),
});

export const Route = createFileRoute("/newsletter")({
  head: () => buildHead({ title: "Newsletter", canonical: "/newsletter", description: "Confirm or manage your KpopBlog newsletter subscription." }),
  validateSearch: search,
  component: NewsletterPage,
});

function NewsletterPage() {
  const { token, action } = Route.useSearch();
  const [state, setState] = useState<"idle" | "loading" | "ok" | "error">("idle");
  const [message, setMessage] = useState("");
  const [email, setEmail] = useState("");

  useEffect(() => {
    if (!token) return;
    setState("loading");
    const run = action === "unsubscribe"
      ? unsubscribeNewsletter({ token }).then((r) => ({ ok: r.ok, message: r.ok ? "You've been unsubscribed." : "Could not unsubscribe." }))
      : confirmNewsletter(token);
    run.then((r) => {
      setState(r.ok ? "ok" : "error");
      setMessage(r.message);
    });
  }, [token, action]);

  return (
    <div className="mx-auto max-w-xl px-4 py-16">
      <h1 className="font-display text-3xl font-bold mb-2">Newsletter</h1>
      <p className="text-sm text-muted-foreground mb-6">Manage your KpopBlog newsletter subscription.</p>

      {token && (
        <div className="rounded-xl border border-border bg-card p-6 mb-8">
          {state === "loading" && <p className="flex items-center gap-2 text-sm"><Loader2 className="size-4 animate-spin" /> Working…</p>}
          {state === "ok" && <p className="flex items-center gap-2 text-primary"><CheckCircle2 className="size-5" /> {message}</p>}
          {state === "error" && <p className="flex items-center gap-2 text-destructive"><XCircle className="size-5" /> {message}</p>}
        </div>
      )}

      <div className="rounded-xl border border-border bg-card p-6">
        <h2 className="font-semibold mb-2">Unsubscribe</h2>
        <p className="text-sm text-muted-foreground mb-4">Enter the email you used to subscribe.</p>
        <form
          className="flex flex-col sm:flex-row gap-2"
          onSubmit={async (e) => {
            e.preventDefault();
            setState("loading");
            const r = await unsubscribeNewsletter({ email });
            setState(r.ok ? "ok" : "error");
            setMessage(r.ok ? "You've been unsubscribed." : "Email not found.");
          }}
        >
          <Input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@example.com" />
          <Button type="submit" variant="outline">Unsubscribe</Button>
        </form>
      </div>

      <p className="mt-8 text-sm">
        <Link to="/" className="text-primary">← Back to home</Link>
      </p>
    </div>
  );
}
