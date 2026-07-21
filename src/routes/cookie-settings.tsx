import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { useConsent } from "@/hooks/useConsent";

export const Route = createFileRoute("/cookie-settings")({
  head: () => buildHead({ title: "Cookie Settings", canonical: "/cookie-settings" }),
  component: CookieSettingsPage,
});

function CookieSettingsPage() {
  const { preferences, consentManager } = useConsent();
  const [draft, setDraft] = useState({
    analytics: false,
    advertising: false,
    personalization: false,
  });
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    if (!preferences) return;
    setDraft({
      analytics: preferences.analytics,
      advertising: preferences.advertising,
      personalization: preferences.personalization,
    });
  }, [preferences]);

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="mb-3 font-display text-4xl font-bold">Cookie Settings</h1>
      <p className="text-muted-foreground">
        Essential storage is always active for security, login state, and core preferences. Optional
        categories can be changed at any time.
      </p>

      <fieldset className="mt-8 space-y-3">
        <legend className="sr-only">Privacy categories</legend>
        <PreferenceRow
          name="Essential"
          description="Required for authentication, security, and site operation."
          checked
          disabled
          onChange={() => {}}
        />
        <PreferenceRow
          name="Analytics"
          description="Helps measure performance and improve navigation."
          checked={draft.analytics}
          onChange={(checked) => setDraft((current) => ({ ...current, analytics: checked }))}
        />
        <PreferenceRow
          name="Personalization"
          description="Remembers optional content and language preferences."
          checked={draft.personalization}
          onChange={(checked) => setDraft((current) => ({ ...current, personalization: checked }))}
        />
        <PreferenceRow
          name="Advertising"
          description="Allows configured Google AdSense units to load. Ads remain disabled without this choice."
          checked={draft.advertising}
          onChange={(checked) => setDraft((current) => ({ ...current, advertising: checked }))}
        />
      </fieldset>

      <div className="mt-6 flex flex-wrap gap-2">
        <Button
          onClick={() => {
            consentManager.save(draft);
            setSaved(true);
          }}
        >
          Save choices
        </Button>
        <Button
          variant="outline"
          onClick={() => {
            consentManager.rejectOptional();
            setSaved(true);
          }}
        >
          Reject optional
        </Button>
      </div>
      {saved && (
        <p className="mt-3 text-sm text-primary" role="status">
          Privacy choices saved.
        </p>
      )}
      {preferences && (
        <p className="mt-4 text-xs text-muted-foreground">
          Last updated {new Date(preferences.updatedAt).toLocaleString()}.
        </p>
      )}
    </div>
  );
}

function PreferenceRow({
  name,
  description,
  checked,
  disabled = false,
  onChange,
}: {
  name: string;
  description: string;
  checked: boolean;
  disabled?: boolean;
  onChange: (checked: boolean) => void;
}) {
  return (
    <label className="flex items-start gap-4 rounded-xl border border-border bg-card p-4">
      <input
        type="checkbox"
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange(event.target.checked)}
        className="mt-1"
      />
      <span>
        <span className="block font-semibold">{name}</span>
        <span className="mt-1 block text-sm text-muted-foreground">{description}</span>
      </span>
    </label>
  );
}
