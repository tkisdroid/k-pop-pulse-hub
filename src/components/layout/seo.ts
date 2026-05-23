interface Props { title: string; description?: string; canonical?: string; ogImage?: string; }
// Returns TanStack head() option shape for routes.
export function buildHead({ title, description, canonical, ogImage }: Props) {
  const meta: Array<Record<string, string>> = [
    { title: `${title} — KpopBlog` },
    { name: "description", content: description ?? "Global K-pop news, artists, comebacks and fan community." },
    { property: "og:title", content: `${title} — KpopBlog` },
    { property: "og:description", content: description ?? "Global K-pop news, artists, comebacks and fan community." },
  ];
  if (canonical) meta.push({ property: "og:url", content: canonical });
  if (ogImage) meta.push({ property: "og:image", content: ogImage });
  const links: Array<Record<string, string>> = [];
  if (canonical) links.push({ rel: "canonical", href: canonical });
  return { meta, links };
}
