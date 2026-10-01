import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";
import { demoData } from "@/data/demo";

const BASE_URL = "https://thekpopblog.com";

interface Entry {
  path: string;
  lastmod?: string;
  changefreq?: string;
  priority?: string;
}

export const Route = createFileRoute("/sitemap.xml")({
  server: {
    handlers: {
      GET: async () => {
        const staticPaths: Entry[] = [
          { path: "/", changefreq: "hourly", priority: "1.0" },
          { path: "/latest", changefreq: "hourly", priority: "0.9" },
          { path: "/trending", changefreq: "hourly", priority: "0.9" },
          { path: "/artists", changefreq: "daily", priority: "0.8" },
          { path: "/videos", changefreq: "daily", priority: "0.7" },
          { path: "/charts", changefreq: "daily", priority: "0.7" },
          { path: "/comebacks", changefreq: "daily", priority: "0.7" },
          { path: "/polls", changefreq: "daily", priority: "0.6" },
          { path: "/community", changefreq: "daily", priority: "0.6" },
          { path: "/forum", changefreq: "daily", priority: "0.6" },
          { path: "/about", changefreq: "monthly", priority: "0.3" },
          { path: "/contact", changefreq: "monthly", priority: "0.3" },
          { path: "/advertise", changefreq: "monthly", priority: "0.3" },
          { path: "/privacy", changefreq: "yearly", priority: "0.2" },
          { path: "/terms", changefreq: "yearly", priority: "0.2" },
          { path: "/copyright", changefreq: "yearly", priority: "0.2" },
          { path: "/corrections", changefreq: "yearly", priority: "0.2" },
          { path: "/community-guidelines", changefreq: "yearly", priority: "0.2" },
        ];
        const dynamic: Entry[] = [
          ...demoData.articles.map((a) => ({
            path: `/news/${a.slug}`,
            lastmod: a.modifiedAt ?? a.publishedAt,
            changefreq: "weekly",
            priority: "0.8",
          })),
          ...demoData.artists.map((a) => ({
            path: `/artist/${a.slug}`,
            changefreq: "weekly",
            priority: "0.7",
          })),
          ...demoData.members.map((m) => ({
            path: `/member/${m.slug}`,
            changefreq: "monthly",
            priority: "0.5",
          })),
          ...demoData.videos.map((v) => ({
            path: `/watch/${v.id}`,
            changefreq: "weekly",
            priority: "0.6",
          })),
          ...demoData.polls.map((p) => ({
            path: `/polls/${p.slug}`,
            changefreq: "daily",
            priority: "0.5",
          })),
          ...demoData.threads.map((t) => ({
            path: `/thread/${t.slug}`,
            changefreq: "daily",
            priority: "0.4",
          })),
        ];
        const entries = [...staticPaths, ...dynamic];
        const urls = entries.map((e) =>
          [
            "  <url>",
            `    <loc>${BASE_URL}${e.path}</loc>`,
            e.lastmod ? `    <lastmod>${new Date(e.lastmod).toISOString()}</lastmod>` : null,
            e.changefreq ? `    <changefreq>${e.changefreq}</changefreq>` : null,
            e.priority ? `    <priority>${e.priority}</priority>` : null,
            "  </url>",
          ]
            .filter(Boolean)
            .join("\n"),
        );
        const xml = [
          '<?xml version="1.0" encoding="UTF-8"?>',
          '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
          ...urls,
          "</urlset>",
        ].join("\n");
        return new Response(xml, {
          headers: { "Content-Type": "application/xml", "Cache-Control": "public, max-age=3600" },
        });
      },
    },
  },
});
