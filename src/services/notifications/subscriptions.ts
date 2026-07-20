export type SubscriptionState = {
  topics: string[];
  artists: number[];
};

type WordPressConfig = {
  apiUrl?: string;
  nonce?: string;
};

const DEMO_KEY = "kpopblog:subscriptions:topics";

function config(): WordPressConfig | null {
  if (typeof window === "undefined") return null;
  return (window as typeof window & { kpopblogConfig?: WordPressConfig }).kpopblogConfig ?? null;
}

function demoState(): SubscriptionState {
  if (typeof localStorage === "undefined") return { topics: [], artists: [] };
  try {
    const topics = JSON.parse(localStorage.getItem(DEMO_KEY) ?? "[]") as string[];
    return { topics: Array.isArray(topics) ? topics : [], artists: [] };
  } catch {
    return { topics: [], artists: [] };
  }
}

async function wordpressRequest(init?: RequestInit): Promise<SubscriptionState> {
  const wordpress = config();
  if (!wordpress?.apiUrl) throw new Error("WordPress subscription API is unavailable.");
  const headers = new Headers(init?.headers);
  headers.set("Content-Type", "application/json");
  if (wordpress.nonce) headers.set("X-WP-Nonce", wordpress.nonce);
  const response = await fetch(`${wordpress.apiUrl.replace(/\/$/, "")}/subscriptions`, {
    ...init,
    headers,
    credentials: "same-origin",
  });
  const data = (await response.json().catch(() => ({}))) as SubscriptionState & {
    message?: string;
  };
  if (!response.ok) throw new Error(data.message || "Could not update subscriptions.");
  return { topics: data.topics ?? [], artists: data.artists ?? [] };
}

export const subscriptionProvider = {
  async list(): Promise<SubscriptionState> {
    return config()?.apiUrl ? wordpressRequest() : demoState();
  },
  async setTopic(key: string, subscribed: boolean): Promise<SubscriptionState> {
    if (config()?.apiUrl) {
      return wordpressRequest({
        method: "POST",
        body: JSON.stringify({ type: "topic", key, subscribed }),
      });
    }
    const state = demoState();
    state.topics = subscribed
      ? Array.from(new Set([...state.topics, key]))
      : state.topics.filter((topic) => topic !== key);
    localStorage.setItem(DEMO_KEY, JSON.stringify(state.topics));
    return state;
  },
};
