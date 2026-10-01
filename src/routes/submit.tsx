import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { communityProvider } from "@/services/community";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { Loader2 } from "lucide-react";

export const Route = createFileRoute("/submit")({
  head: () => buildHead({ title: "Submit", canonical: "/submit" }),
  component: Submit,
});

const TYPES = [
  "News tip",
  "Article draft",
  "Artist correction",
  "Comeback event",
  "Translation request",
] as const;
const TYPE_KEYS: Record<(typeof TYPES)[number], string> = {
  "News tip": "news-tip",
  "Article draft": "article-draft",
  "Artist correction": "artist-correction",
  "Comeback event": "comeback-event",
  "Translation request": "translation-request",
};

function Submit() {
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [type, setType] = useState<(typeof TYPES)[number]>("News tip");
  const [subject, setSubject] = useState("");
  const [details, setDetails] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!user) {
      show("Log in to submit a contribution");
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await communityProvider.createSubmission({
        type: TYPE_KEYS[type],
        subject: subject.trim(),
        details: details.trim(),
      });
      setSubmitted(true);
      setSubject("");
      setDetails("");
    } catch (submissionError) {
      setError(
        submissionError instanceof Error
          ? submissionError.message
          : "Submission could not be saved.",
      );
    } finally {
      setSubmitting(false);
    }
  }
  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <SectionHeader
        as="h1"
        eyebrow="Submissions"
        title="Contribute to KpopBlog"
        subtitle="Tips, corrections and event submissions are reviewed by editors before publishing."
      />
      <div className="flex gap-2 mb-4 overflow-x-auto scrollbar-hide">
        {TYPES.map((t) => (
          <button
            key={t}
            onClick={() => setType(t)}
            className={`shrink-0 px-3 py-1.5 rounded-full text-sm ${type === t ? "bg-primary text-primary-foreground" : "bg-accent"}`}
          >
            {t}
          </button>
        ))}
      </div>
      {submitted ? (
        <div className="p-6 rounded-xl bg-card border border-border text-center">
          <h2 className="font-display text-xl font-bold">Thanks! 🎉</h2>
          <p className="text-sm text-muted-foreground mt-2">
            Your {type.toLowerCase()} is in our review queue.
          </p>
          <Button className="mt-4" onClick={() => setSubmitted(false)}>
            Submit another
          </Button>
        </div>
      ) : (
        <form className="space-y-4" onSubmit={submit}>
          <label className="block text-sm font-medium">
            Subject
            <input
              required
              minLength={3}
              maxLength={160}
              value={subject}
              onChange={(event) => setSubject(event.target.value)}
              className="mt-1 w-full h-10 px-3 rounded-md bg-background border border-input"
            />
          </label>
          <label className="block text-sm font-medium">
            Details
            <textarea
              required
              minLength={10}
              maxLength={10000}
              value={details}
              onChange={(event) => setDetails(event.target.value)}
              className="mt-1 w-full p-3 rounded-md bg-background border border-input min-h-32"
            />
          </label>
          <div className="text-xs text-muted-foreground">
            By submitting, you agree that your contribution may be edited and published under our
            community guidelines.
          </div>
          {error && (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
          )}
          <Button
            type="submit"
            disabled={submitting || !subject.trim() || details.trim().length < 10}
          >
            {submitting && <Loader2 className="size-4 animate-spin" />} Submit
          </Button>
        </form>
      )}
    </div>
  );
}
