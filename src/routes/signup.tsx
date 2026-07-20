import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { useAuth } from "@/hooks/useAuth";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { fetchNewsletterSettings, subscribeNewsletter, NEWSLETTER_DEFAULTS } from "@/services/newsletter";

export const Route = createFileRoute("/signup")({
  head: () => buildHead({ title: "Sign up", canonical: "/signup" }),
  component: Signup,
});

function Signup() {
  const { signUp } = useAuth();
  const nav = useNavigate();
  const [form, setForm] = useState({ email: "", username: "", displayName: "", password: "" });
  const [agreed, setAgreed] = useState(false);
  const [newsletterOptIn, setNewsletterOptIn] = useState(true);
  const [optInLabel, setOptInLabel] = useState(NEWSLETTER_DEFAULTS.signupOptInLabel);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const registrationEnabled =
    typeof window === "undefined" || window.kpopblogConfig?.registrationEnabled !== false;

  useEffect(() => {
    fetchNewsletterSettings().then((s) => setOptInLabel(s.signupOptInLabel));
  }, []);

  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <h1 className="font-display text-3xl font-bold mb-2">Join KpopBlog</h1>
      <p className="text-sm text-muted-foreground mb-6">Create an account to join the global K-pop community.</p>
      <form
        className="space-y-3"
        onSubmit={async (e) => {
          e.preventDefault();
          if (!registrationEnabled || !agreed || submitting) return;
          setSubmitting(true);
          setError(null);
          try {
            await signUp(form);
            if (newsletterOptIn && form.email) {
              try {
                await subscribeNewsletter({ email: form.email, source: "signup" });
              } catch (newsletterError) {
                console.warn("Newsletter enrollment failed after account creation", newsletterError);
              }
            }
            nav({ to: "/onboarding" });
          } catch (err) {
            setError((err as Error).message);
          } finally {
            setSubmitting(false);
          }
        }}
      >
        <input required type="email" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <input required minLength={3} autoComplete="username" placeholder="Username" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <input required placeholder="Display name" value={form.displayName} onChange={(e) => setForm({ ...form, displayName: e.target.value })} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <input required minLength={8} type="password" autoComplete="new-password" placeholder="Password (8+ characters)" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} className="w-full h-10 px-3 rounded-md bg-background border border-input" />

        <label className="flex items-start gap-2 text-xs">
          <input type="checkbox" checked={newsletterOptIn} onChange={(e) => setNewsletterOptIn(e.target.checked)} className="mt-0.5" />
          <span>{optInLabel}</span>
        </label>
        <label className="flex items-start gap-2 text-xs">
          <input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} className="mt-0.5" />
          <span>I agree to the <Link to="/community-guidelines" className="text-primary">community guidelines</Link> and <Link to="/terms" className="text-primary">terms</Link>.</span>
        </label>
        <Button type="submit" disabled={!registrationEnabled || !agreed || submitting} className="w-full">
          {submitting ? "Creating…" : "Create account"}
        </Button>
      </form>
      {!registrationEnabled && <p className="mt-3 text-sm text-destructive">Account registration is currently disabled.</p>}
      {error && <p className="mt-3 text-xs text-destructive">{error}</p>}
      <p className="mt-6 text-sm text-center">Already have an account? <Link to="/login" className="text-primary">Log in</Link></p>
    </div>
  );
}
