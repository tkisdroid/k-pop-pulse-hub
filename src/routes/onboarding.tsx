import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { localeMeta } from "@/i18n";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { Button } from "@/components/ui/button";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/onboarding")({
  head: () => buildHead({ title: "Get started", canonical: "/onboarding" }),
  component: Onboarding,
});

const STEPS = ["Language", "Region", "Artists", "Rules", "Notifications"] as const;

function Onboarding() {
  const { data } = useRuntimeData();
  const nav = useNavigate();
  const [step, setStep] = useState(0);
  const [lang, setLang] = useState("en");
  const [country, setCountry] = useState("Global");
  const [followed, setFollowed] = useState<string[]>([]);
  const [agreed, setAgreed] = useState(false);
  const [notif, setNotif] = useState({ breaking: true, comebacks: true, replies: true, weekly: false });

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <div className="flex gap-1 mb-6">{STEPS.map((s, i) => <div key={s} className={`flex-1 h-1 rounded-full ${i <= step ? "gradient-neon" : "bg-muted"}`} />)}</div>
      <h1 className="font-display text-3xl font-bold mb-1">{STEPS[step]}</h1>
      <div className="mt-4 p-4 rounded-xl bg-card border border-border">
        {step === 0 && (
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {Object.entries(localeMeta).map(([k, m]) => <button key={k} onClick={() => setLang(k)} className={`p-2 rounded-md text-sm ${lang === k ? "bg-primary text-primary-foreground" : "bg-accent"}`}>{m.label}</button>)}
          </div>
        )}
        {step === 1 && <input value={country} onChange={(e) => setCountry(e.target.value)} placeholder="Country / region" className="w-full h-10 px-3 rounded-md bg-background border border-input" />}
        {step === 2 && (
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {data.artists.map((a) => {
              const on = followed.includes(a.id);
              return <button key={a.id} onClick={() => setFollowed(on ? followed.filter((x) => x !== a.id) : [...followed, a.id])} className={`p-2 rounded-md text-sm ${on ? "bg-primary text-primary-foreground" : "bg-accent"}`}>{a.name}</button>;
            })}
          </div>
        )}
        {step === 3 && (
          <div className="space-y-2 text-sm">
            <p>By joining KpopBlog you agree to:</p>
            <ul className="list-disc pl-5 text-muted-foreground space-y-1">
              <li>Be respectful — no harassment, hate speech, or fanwar baiting</li>
              <li>No doxxing, private information, or sasaeng behavior</li>
              <li>Label rumors and provide sources</li>
              <li>No impersonation, spam, or scam links</li>
            </ul>
            <label className="flex items-center gap-2 mt-3"><input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} /> I agree to the community guidelines</label>
          </div>
        )}
        {step === 4 && (
          <div className="space-y-2 text-sm">
            {(["breaking", "comebacks", "replies", "weekly"] as const).map((k) => <label key={k} className="flex items-center justify-between p-2 rounded bg-accent"><span className="capitalize">{k}</span><input type="checkbox" checked={notif[k]} onChange={(e) => setNotif({ ...notif, [k]: e.target.checked })} /></label>)}
          </div>
        )}
      </div>
      <div className="mt-6 flex justify-between">
        <Button variant="outline" disabled={step === 0} onClick={() => setStep((s) => s - 1)}>Back</Button>
        {step < STEPS.length - 1 ? <Button onClick={() => setStep((s) => s + 1)} disabled={step === 3 && !agreed}>Next</Button> : <Button onClick={() => nav({ to: "/" })}>Finish</Button>}
      </div>
    </div>
  );
}
