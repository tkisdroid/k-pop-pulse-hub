export type ConsentPreferences = {
  essential: true;
  analytics: boolean;
  advertising: boolean;
  personalization: boolean;
  updatedAt: string;
  version: 1;
};

const STORAGE_KEY = "kpopblog:privacy-consent:v1";
type Listener = (preferences: ConsentPreferences | null) => void;
const listeners = new Set<Listener>();

function normalize(value: unknown): ConsentPreferences | null {
  if (!value || typeof value !== "object") return null;
  const candidate = value as Partial<ConsentPreferences>;
  if (candidate.version !== 1 || typeof candidate.updatedAt !== "string") return null;
  return {
    essential: true,
    analytics: Boolean(candidate.analytics),
    advertising: Boolean(candidate.advertising),
    personalization: Boolean(candidate.personalization),
    updatedAt: candidate.updatedAt,
    version: 1,
  };
}

function read(): ConsentPreferences | null {
  if (typeof localStorage === "undefined") return null;
  try {
    return normalize(JSON.parse(localStorage.getItem(STORAGE_KEY) ?? "null"));
  } catch {
    return null;
  }
}

function save(input: Omit<ConsentPreferences, "essential" | "updatedAt" | "version">) {
  const preferences: ConsentPreferences = {
    essential: true,
    analytics: Boolean(input.analytics),
    advertising: Boolean(input.advertising),
    personalization: Boolean(input.personalization),
    updatedAt: new Date().toISOString(),
    version: 1,
  };
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
  } catch {
    // The in-memory notification still keeps the current page consistent.
  }
  listeners.forEach((listener) => listener(preferences));
  if (typeof window !== "undefined") {
    window.dispatchEvent(new CustomEvent("kpopblog:consent-changed", { detail: preferences }));
  }
  return preferences;
}

export const consentManager = {
  get: read,
  save,
  acceptAll() {
    return save({ analytics: true, advertising: true, personalization: true });
  },
  rejectOptional() {
    return save({ analytics: false, advertising: false, personalization: false });
  },
  subscribe(listener: Listener) {
    listeners.add(listener);
    listener(read());
    return () => {
      listeners.delete(listener);
    };
  },
};
