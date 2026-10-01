import { useEffect } from "react";
import { useRouterState } from "@tanstack/react-router";

export interface Metadata {
  title: string;
  description: string;
  canonical: string;
  og_type: string;
  image: string;
  noindex: boolean;
  json_ld: Record<string, unknown>;
  published?: string;
  modified?: string;
}

function meta(attribute: "name" | "property", key: string, value?: string) {
  const matches = [
    ...document.head.querySelectorAll<HTMLMetaElement>(`meta[${attribute}="${key}"]`),
  ];
  const element = matches.shift() ?? document.createElement("meta");
  matches.forEach((duplicate) => duplicate.remove());
  if (!value) {
    element.remove();
    return;
  }
  element.setAttribute(attribute, key);
  element.content = value;
  document.head.append(element);
}

/** WordPress owns SEO in both the initial HTML and client-side route changes. */
export function WordPressSeo() {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  useEffect(() => {
    const api = window.kpopblogConfig?.apiUrl;
    if (!api) return;
    const controller = new AbortController();
    const apply = (seo: Metadata) => {
      if (controller.signal.aborted) return;
      document.getElementById("kpopblog-discovery-jsonld")?.remove();
      document.head.querySelectorAll('link[rel="canonical"]').forEach((link) => link.remove());
      document.title = seo.title;
      meta("name", "description", seo.description);
      meta(
        "name",
        "robots",
        seo.noindex
          ? "noindex, follow"
          : "max-image-preview:large, max-snippet:-1, max-video-preview:-1",
      );
      for (const [key, value] of Object.entries({
        title: seo.title,
        description: seo.description,
        url: seo.canonical,
        type: seo.og_type,
        image: seo.image,
      }))
        meta("property", `og:${key}`, value);
      for (const [key, value] of Object.entries({
        card: "summary_large_image",
        title: seo.title,
        description: seo.description,
        image: seo.image,
      }))
        meta("name", `twitter:${key}`, value);
      meta("property", "article:published_time", seo.published);
      meta("property", "article:modified_time", seo.modified);
      if (seo.canonical) {
        const link = document.createElement("link");
        link.rel = "canonical";
        link.href = seo.canonical;
        document.head.append(link);
      }
      if (Object.keys(seo.json_ld).length) {
        const script = document.createElement("script");
        script.id = "kpopblog-discovery-jsonld";
        script.type = "application/ld+json";
        script.textContent = JSON.stringify(seo.json_ld);
        document.head.append(script);
      }
    };
    if (window.kpopblogConfig?.seoPath === pathname && window.kpopblogConfig.seo) {
      apply(window.kpopblogConfig.seo);
      return () => controller.abort();
    }
    void fetch(`${api.replace(/\/$/, "")}/seo?path=${encodeURIComponent(pathname)}`, {
      signal: controller.signal,
      credentials: "omit",
      headers: { Accept: "application/json" },
    })
      .then(async (response) => {
        if (!response.ok) throw new Error("Metadata unavailable");
        return (await response.json()) as Metadata;
      })
      .then(apply)
      .catch(() => {
        if (controller.signal.aborted) return;
        document.getElementById("kpopblog-discovery-jsonld")?.remove();
        document.head.querySelectorAll('link[rel="canonical"]').forEach((link) => link.remove());
        meta("name", "robots", "noindex, follow");
      });
    return () => controller.abort();
  }, [pathname]);
  return null;
}
