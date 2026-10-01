import { useState } from "react";
import { Link } from "@tanstack/react-router";
import { Button } from "@/components/ui/button";
import { useConsent } from "@/hooks/useConsent";

export function ConsentBanner() {
  const { preferences, consentManager } = useConsent();
  const [customizing, setCustomizing] = useState(false);
  const [draft, setDraft] = useState({
    analytics: false,
    advertising: false,
    personalization: false,
  });

  if (preferences) return null;
  const adsMode =
    typeof window !== "undefined"
      ? (window as typeof window & { kpopblogConfig?: { ads?: { consentMode?: string } } })
          .kpopblogConfig?.ads?.consentMode
      : undefined;
  const bannerCopy =
    adsMode === "optout" || adsMode === "google"
      ? "Essential storage keeps the site working; analytics and personalization are optional. Ads from Google AdSense keep KpopBlog free. Choose “Reject optional” to stop personalized ads."
      : "Essential storage keeps the site working. Analytics, personalization, and advertising are optional. AdSense does not load until you allow advertising.";

  return (
    <div
      className="fixed inset-x-3 bottom-3 z-[100] mx-auto max-w-3xl rounded-xl border border-border bg-popover p-4 shadow-2xl"
      role="dialog"
      aria-labelledby="consent-title"
    >
      <h2 id="consent-title" className="font-display text-lg font-bold">
        Your privacy choices
      </h2>
      <p className="mt-1 text-sm text-muted-foreground">{bannerCopy}</p>

      {customizing && (
        <fieldset className="mt-4 grid gap-2 sm:grid-cols-2">
          <legend className="sr-only">Optional privacy categories</legend>
          <label className="flex items-center gap-3 rounded-lg border border-border p-3 text-sm">
            <input type="checkbox" checked disabled /> Essential
          </label>
          {(["analytics", "personalization", "advertising"] as const).map((key) => (
            <label
              key={key}
              className="flex items-center gap-3 rounded-lg border border-border p-3 text-sm capitalize"
            >
              <input
                type="checkbox"
                checked={draft[key]}
                onChange={(event) =>
                  setDraft((current) => ({ ...current, [key]: event.target.checked }))
                }
              />
              {key}
            </label>
          ))}
        </fieldset>
      )}

      <div className="mt-4 flex flex-wrap items-center gap-2">
        <Button size="sm" onClick={() => consentManager.acceptAll()}>
          Accept all
        </Button>
        <Button size="sm" variant="outline" onClick={() => consentManager.rejectOptional()}>
          Reject optional
        </Button>
        {customizing ? (
          <Button size="sm" variant="secondary" onClick={() => consentManager.save(draft)}>
            Save choices
          </Button>
        ) : (
          <Button size="sm" variant="ghost" onClick={() => setCustomizing(true)}>
            Customize
          </Button>
        )}
        <Link to="/privacy" className="ml-auto text-xs text-primary hover:underline">
          Privacy policy
        </Link>
      </div>
    </div>
  );
}
