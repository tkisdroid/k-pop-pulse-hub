import { useAuthModal } from "@/hooks/useAuthModal";
import { useAuth } from "@/hooks/useAuth";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { X } from "lucide-react";

const SOCIAL = [
  ["Google", "google"],
  ["Apple", "apple"],
  ["X / Twitter", "x"],
  ["Kakao", "kakao"],
  ["Naver", "naver"],
  ["Discord", "discord"],
] as const;

export function AuthModal() {
  const { open, hide, reason } = useAuthModal();
  const { signIn, signInDemo, signInWithProvider } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [notice, setNotice] = useState<string | null>(null);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4" onClick={hide}>
      <div className="w-full max-w-md bg-card border border-border rounded-2xl shadow-2xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h2 className="font-display text-lg font-bold">{reason}</h2>
          <button onClick={hide} className="p-1 rounded hover:bg-accent"><X className="size-4" /></button>
        </div>
        <div className="p-5 space-y-4">
          <form className="space-y-3" onSubmit={async (e) => { e.preventDefault(); await signIn(email, password); hide(); }}>
            <input type="email" required placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
            <input type="password" required placeholder="Password" value={password} onChange={(e) => setPassword(e.target.value)} className="w-full h-10 px-3 rounded-md bg-background border border-input" />
            <Button type="submit" className="w-full">Log in</Button>
          </form>
          <button onClick={() => setNotice("Magic link will activate once Supabase credentials are provided.")} className="w-full text-sm text-muted-foreground hover:text-foreground">Send magic link instead</button>
          <div className="relative text-center text-xs text-muted-foreground"><span className="bg-card px-2 relative z-10">or continue with</span><div className="absolute inset-x-0 top-1/2 border-t border-border" /></div>
          <div className="grid grid-cols-2 gap-2">
            {SOCIAL.map(([label, key]) => (
              <Button key={key} variant="outline" size="sm" onClick={async () => { const r = await signInWithProvider(key as any); setNotice(r.message); }}>{label}</Button>
            ))}
          </div>
          <div className="rounded-md bg-muted p-3 text-xs space-y-2">
            <div className="font-medium">Demo login</div>
            <div className="flex flex-wrap gap-1">
              {(["member", "moderator", "editor", "admin"] as const).map((r) => (
                <Button key={r} size="sm" variant="secondary" onClick={async () => { await signInDemo(r); hide(); }}>{r}</Button>
              ))}
            </div>
          </div>
          {notice && <p className="text-xs text-muted-foreground bg-accent/40 rounded p-2">{notice}</p>}
        </div>
      </div>
    </div>
  );
}
