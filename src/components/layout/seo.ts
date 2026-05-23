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
  const fullTitle = `${title} — KpopBlog`;
  const desc = description ?? "Global K-pop news, artists, comebacks and fan community.";
  const meta: Array<Record<string, string>> = [
    { title: fullTitle },
    { name: "description", content: desc },
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
      scripts.push({ type: "application/ld+json", children: JSON.stringify(p) });
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
      item: it.path,
    })),
  };
}
