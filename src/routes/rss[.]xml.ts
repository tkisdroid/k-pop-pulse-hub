import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { demoData } from "@/data/demo";

const SITE_NAME = "KpopBlog";
const SITE_DESCRIPTION = "Global K-pop news, artists, comebacks and fan community.";
const BASE_URL = "https://thekpopblog.com";

function esc(s: string) {
  return s.replace(
    /[<>&'"]/g,
    (c) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;", "'": "&apos;", '"': "&quot;" })[c]!,
  );
}

export const Route = createFileRoute("/rss.xml")({
  server: {
    handlers: {
      GET: async () => {
        const items = [...demoData.articles]
          .sort((a, b) => +new Date(b.publishedAt) - +new Date(a.publishedAt))
          .slice(0, 50)
          .map((a) =>
            [
              "    <item>",
              `      <title>${esc(a.title)}</title>`,
              `      <link>${BASE_URL}/news/${a.slug}</link>`,
              `      <guid isPermaLink="false">${a.id}</guid>`,
              `      <pubDate>${new Date(a.publishedAt).toUTCString()}</pubDate>`,
              `      <description>${esc(a.excerpt ?? "")}</description>`,
              `      <category>${esc(a.category)}</category>`,
              "    </item>",
            ].join("\n"),
          )
          .join("\n");
        const xml = [
          '<?xml version="1.0" encoding="UTF-8"?>',
          '<rss version="2.0">',
          "  <channel>",
          `    <title>${SITE_NAME}</title>`,
          `    <link>${BASE_URL}/</link>`,
          `    <description>${SITE_DESCRIPTION}</description>`,
          "    <language>en</language>",
          items,
          "  </channel>",
          "</rss>",
        ].join("\n");
        return new Response(xml, {
          headers: {
            "Content-Type": "application/rss+xml; charset=utf-8",
            "Cache-Control": "public, max-age=1800",
          },
        });
      },
    },
  },
});
