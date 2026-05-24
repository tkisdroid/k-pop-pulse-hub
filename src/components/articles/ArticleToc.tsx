import { useEffect, useMemo, useState } from "react";

type Heading = { id: string; text: string; level: 2 | 3 };

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
    const text = String(inner).replace(/<[^>]+>/g, "").trim();
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

export function ArticleToc({ headings }: { headings: Heading[] }) {
  const [activeId, setActiveId] = useState<string | null>(null);

  const ids = useMemo(() => headings.map((h) => h.id), [headings]);

  useEffect(() => {
    if (ids.length === 0) return;
    const els = ids
      .map((id) => document.getElementById(id))
      .filter((el): el is HTMLElement => !!el);
    if (els.length === 0) return;
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((e) => e.isIntersecting)
          .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible[0]) setActiveId(visible[0].target.id);
      },
      { rootMargin: "-80px 0px -70% 0px", threshold: [0, 1] },
    );
    els.forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, [ids]);

  if (headings.length < 2) return null;

  return (
    <nav aria-label="Table of contents" className="text-sm">
      <div className="text-xs uppercase tracking-wider text-muted-foreground font-semibold mb-3">
        Contents
      </div>
      <ul className="space-y-1.5 border-l border-border">
        {headings.map((h) => {
          const isActive = activeId === h.id;
          return (
            <li key={h.id} className={h.level === 3 ? "pl-4" : ""}>
              <a
                href={`#${h.id}`}
                onClick={(e) => {
                  e.preventDefault();
                  const el = document.getElementById(h.id);
                  if (!el) return;
                  const top = el.getBoundingClientRect().top + window.scrollY - 80;
                  window.scrollTo({ top, behavior: "smooth" });
                  history.replaceState(null, "", `#${h.id}`);
                }}
                className={`block -ml-px border-l-2 pl-3 py-0.5 transition-colors ${
                  isActive
                    ? "border-primary text-primary font-medium"
                    : "border-transparent text-muted-foreground hover:text-foreground"
                }`}
              >
                {h.text}
              </a>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
