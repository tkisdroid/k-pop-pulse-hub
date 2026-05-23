import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { SectionHeader } from "@/components/layout/SectionHeader";

export const Route = createFileRoute("/submit")({
  head: () => buildHead({ title: "Submit", canonical: "/submit" }),
  component: Submit,
});

const TYPES = ["News tip", "Article draft", "Artist correction", "Comeback event", "Translation request"] as const;

function Submit() {
  const [type, setType] = useState<typeof TYPES[number]>("News tip");
  const [submitted, setSubmitted] = useState(false);
  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <SectionHeader eyebrow="Submissions" title="Contribute to KpopBlog" subtitle="Tips, corrections and event submissions are reviewed by editors before publishing." />
      <div className="flex gap-2 mb-4 overflow-x-auto scrollbar-hide">
        {TYPES.map((t) => <button key={t} onClick={() => setType(t)} className={`shrink-0 px-3 py-1.5 rounded-full text-sm ${type === t ? "bg-primary text-primary-foreground" : "bg-accent"}`}>{t}</button>)}
      </div>
      {submitted ? (
        <div className="p-6 rounded-xl bg-card border border-border text-center">
          <h2 className="font-display text-xl font-bold">Thanks! 🎉</h2>
          <p className="text-sm text-muted-foreground mt-2">Your {type.toLowerCase()} is in our review queue.</p>
          <Button className="mt-4" onClick={() => setSubmitted(false)}>Submit another</Button>
        </div>
      ) : (
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); setSubmitted(true); }}>
          <input required placeholder="Subject" className="w-full h-10 px-3 rounded-md bg-background border border-input" />
          <textarea required placeholder="Details, source links, dates, etc." className="w-full p-3 rounded-md bg-background border border-input min-h-32" />
          <div className="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">Upload image (placeholder — connect storage later)</div>
          <div className="text-xs text-muted-foreground">By submitting, you agree that your contribution may be edited and published under our community guidelines.</div>
          <Button type="submit">Submit</Button>
        </form>
      )}
    </div>
  );
}
