import { useEffect, useState } from "react";
import { consentManager, type ConsentPreferences } from "@/services/privacy/consent";

export function useConsent() {
  const [preferences, setPreferences] = useState<ConsentPreferences | null>(() =>
    consentManager.get(),
  );

  useEffect(() => consentManager.subscribe(setPreferences), []);

  return { preferences, consentManager };
}
