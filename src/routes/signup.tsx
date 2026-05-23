import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { useAuth } from "@/hooks/useAuth";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/signup")({
  head: () => buildHead({ title: "Sign up", canonical: "/signup" }),
  component: Signup,
});

function Signup() {
  const { signUp } = useAuth();
  const nav = useNavigate();
  const [form, setForm] = useState({ email: "", username: "", displayName: "" });
  const [agreed, setAgreed] = useState(false);
  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <h1 className="font-display text-3xl font-bold mb-2">Join KpopBlog</h1>
      <p className="text-sm text-muted-foreground mb-6">Create an account to join the global K-pop community.</p>
      <form className="space-y-3" onSubmit={async (e) => { e.preventDefault(); if (!agreed) return; await signUp(form); nav({ to: "/onboarding" }); }}>
        <input required type="email" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <input required placeholder="Username" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <input required placeholder="Display name" value={form.displayName} onChange={(e) => setForm({ ...form, displayName: e.target.value })} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <input required type="password" placeholder="Password" className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <label className="flex items-start gap-2 text-xs"><input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} /> I agree to the <Link to="/community-guidelines" className="text-primary">community guidelines</Link> and <Link to="/terms" className="text-primary">terms</Link>.</label>
        <Button type="submit" disabled={!agreed} className="w-full">Create account</Button>
      </form>
      <p className="mt-6 text-sm text-center">Already have an account? <Link to="/login" className="text-primary">Log in</Link></p>
    </div>
  );
}
