export type Heading = { id: string; text: string; level: 2 | 3 };

function slugify(s: string) {
  return s
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, "")
    .replace(/\s+/g, "-")
    .slice(0, 80);
}

export function extractHeadings(html: string): { html: string; headings: Heading[] } {
  const headings: Heading[] = [];
  const used = new Set<string>();
  const out = html.replace(/<(h[23])([^>]*)>([\s\S]*?)<\/\1>/gi, (_, tag, attrs, inner) => {
    const text = String(inner)
      .replace(/<[^>]+>/g, "")
      .trim();
    if (!text) return `<${tag}${attrs}>${inner}</${tag}>`;
    let id = slugify(text);
    let i = 1;
    while (used.has(id)) id = `${slugify(text)}-${++i}`;
    used.add(id);
    headings.push({ id, text, level: tag === "h2" ? 2 : 3 });
    const cleaned = String(attrs).replace(/\sid="[^"]*"/i, "");
    return `<${tag}${cleaned} id="${id}">${inner}</${tag}>`;
  });
  return { html: out, headings };
}
