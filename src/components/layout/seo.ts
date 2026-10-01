export const SITE_URL = "https://thekpopblog.com";

export function absoluteUrl(path: string) {
  return new URL(path, SITE_URL).href;
}

interface Props {
  title: string;
  description?: string;
  canonical?: string;
  ogImage?: string;
  ogType?: "website" | "article" | "profile" | "video.other" | "music.group";
  jsonLd?: Record<string, unknown> | Array<Record<string, unknown>>;
}

// Returns TanStack head() option shape for routes.
export function buildHead({ title, description, canonical, ogImage, ogType, jsonLd }: Props) {
  const empty: {
    meta: Array<Record<string, string>>;
    links: Array<Record<string, string>>;
    scripts: Array<Record<string, string>>;
  } = { meta: [], links: [], scripts: [] };
  if (typeof window !== "undefined" && window.kpopblogConfig?.apiUrl) return empty;
  canonical = canonical ? absoluteUrl(canonical) : undefined;
  ogImage = absoluteUrl(ogImage || "/og-default.jpg");
  const path = canonical ? new URL(canonical).pathname : "";
  const noindex =
    /^\/(admin|moderation|onboarding|login|signup|forgot-password|submit|bookmarks|cookie-settings|search|newsletter|profile|author|quiz)(?:\/|$)/.test(
      path,
    );
  const fullTitle = `${title} — KpopBlog`;
  const desc = description ?? "Global K-pop news, artists, comebacks and fan community.";
  const meta: Array<Record<string, string>> = [
    { title: fullTitle },
    { name: "description", content: desc },
    { name: "robots", content: noindex ? "noindex, follow" : "max-image-preview:large" },
    { property: "og:site_name", content: "KpopBlog" },
    { property: "og:title", content: fullTitle },
    { property: "og:description", content: desc },
    { property: "og:type", content: ogType ?? "website" },
    { name: "twitter:card", content: ogImage ? "summary_large_image" : "summary" },
    { name: "twitter:title", content: fullTitle },
    { name: "twitter:description", content: desc },
  ];
  if (canonical) meta.push({ property: "og:url", content: canonical });
  if (ogImage) {
    meta.push({ property: "og:image", content: ogImage });
    meta.push({ name: "twitter:image", content: ogImage });
  }
  const links: Array<Record<string, string>> = [];
  if (canonical) links.push({ rel: "canonical", href: canonical });

  const scripts: Array<Record<string, string>> = [];
  if (jsonLd) {
    const payloads = Array.isArray(jsonLd) ? jsonLd : [jsonLd];
    for (const p of payloads) {
      scripts.push({
        type: "application/ld+json",
        children: JSON.stringify(p).replace(/</g, "\\u003c"),
      });
    }
  }
  return { meta, links, scripts };
}

export function breadcrumbLd(items: Array<{ name: string; path: string }>) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((it, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: it.name,
      item: absoluteUrl(it.path),
    })),
  };
}
