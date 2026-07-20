import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { authProvider } from "@/services/auth";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/forgot-password")({
  head: () => buildHead({ title: "Forgot password", canonical: "/forgot-password" }),
  component: ForgotPassword,
});

function ForgotPassword() {
  const [email, setEmail] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  if (!authProvider.requestPasswordReset) {
    return (
      <div className="mx-auto max-w-md px-4 py-12">
        <h1 className="font-display text-3xl font-bold mb-2">Password reset unavailable</h1>
        <p className="text-sm text-muted-foreground">
          This feature requires the WordPress backend.{" "}
          <Link to="/login" className="text-primary">
            Back to login
          </Link>
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <h1 className="font-display text-3xl font-bold mb-2">Reset your password</h1>
      <p className="text-sm text-muted-foreground mb-6">
        Enter your email and we'll send you a reset link.
      </p>
      <form
        className="space-y-3"
        onSubmit={async (e) => {
          e.preventDefault();
          if (submitting) return;
          setSubmitting(true);
          try {
            const r = await authProvider.requestPasswordReset!(email);
            setMessage(r.message);
          } finally {
            setSubmitting(false);
          }
        }}
      >
        <input
          type="email"
          required
          placeholder="Email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="w-full h-10 px-3 rounded-md bg-background border border-input"
        />
        <Button type="submit" disabled={submitting} className="w-full">
          {submitting ? "Sending…" : "Send reset link"}
        </Button>
      </form>
      {message && <p className="mt-4 text-sm bg-accent/40 rounded p-3">{message}</p>}
      <p className="mt-6 text-sm text-center">
        <Link to="/login" className="text-primary">
          Back to login
        </Link>
      </p>
    </div>
  );
}
