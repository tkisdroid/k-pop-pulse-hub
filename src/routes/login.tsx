import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { useAuth } from "@/hooks/useAuth";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/login")({
  head: () => buildHead({ title: "Login", canonical: "/login" }),
  component: Login,
});

const SOCIAL = [["Google", "google"], ["Apple", "apple"], ["X / Twitter", "x"], ["Kakao", "kakao"], ["Naver", "naver"], ["Discord", "discord"]] as const;

function Login() {
  const { signIn, signInDemo, signInWithProvider } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState(""); const [password, setPassword] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <h1 className="font-display text-3xl font-bold mb-2">Welcome back</h1>
      <p className="text-sm text-muted-foreground mb-6">Log in to react, comment, vote and follow artists.</p>
      <form className="space-y-3" onSubmit={async (e) => { e.preventDefault(); await signIn(email, password); nav({ to: "/" }); }}>
        <input type="email" required placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <input type="password" required placeholder="Password" value={password} onChange={(e) => setPassword(e.target.value)} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
        <Button type="submit" className="w-full">Log in</Button>
      </form>
      <button onClick={() => setNotice("Magic link activates once Supabase is connected.")} className="w-full mt-3 text-sm text-muted-foreground hover:text-foreground">Send magic link instead</button>
      <div className="my-4 text-xs text-muted-foreground text-center">or continue with</div>
      <div className="grid grid-cols-2 gap-2">
        {SOCIAL.map(([l, k]) => <Button key={k} variant="outline" onClick={async () => { const r = await signInWithProvider(k as any); setNotice(r.message); }}>{l}</Button>)}
      </div>
      <div className="mt-6 p-3 rounded-md bg-muted text-xs space-y-2">
        <div className="font-medium">Demo login</div>
        <div className="flex flex-wrap gap-1">{(["member", "moderator", "editor", "admin"] as const).map((r) => <Button key={r} size="sm" variant="secondary" onClick={async () => { await signInDemo(r); nav({ to: "/" }); }}>{r}</Button>)}</div>
      </div>
      {notice && <p className="mt-3 text-xs text-muted-foreground bg-accent/40 rounded p-2">{notice}</p>}
      <p className="mt-6 text-sm text-center">No account? <Link to="/signup" className="text-primary">Sign up</Link></p>
    </div>
  );
}
