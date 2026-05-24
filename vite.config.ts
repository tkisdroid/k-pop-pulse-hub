// @lovable.dev/vite-tanstack-config already includes the following — do NOT add them manually
// or the app will break with duplicate plugins:
//   - tanstackStart, viteReact, tailwindcss, tsConfigPaths, cloudflare (build-only),
//     componentTagger (dev-only), VITE_* env injection, @ path alias, React/TanStack dedupe,
//     error logger plugins, and sandbox detection (port/host/strictPort).
// You can pass additional config via defineConfig({ vite: { ... } }) if needed.
import { defineConfig } from "@lovable.dev/vite-tanstack-config";
import { VitePWA } from "vite-plugin-pwa";

// Redirect TanStack Start's bundled server entry to src/server.ts (our SSR error wrapper).
// @cloudflare/vite-plugin builds from this — wrangler.jsonc main alone is insufficient.
export default defineConfig({
  tanstackStart: {
    server: { entry: "server" },
  },
  vite: {
    plugins: [
      VitePWA({
        registerType: "autoUpdate",
        injectRegister: null, // we register manually with iframe/preview guard
        filename: "sw.js",
        // Don't ship the manifest from plugin — we author public/manifest.webmanifest manually.
        manifest: false,
        devOptions: { enabled: false }, // never run SW in dev / Lovable preview
        workbox: {
          globPatterns: ["**/*.{js,css,html,ico,png,svg,webp,woff2}"],
          importScripts: ["/sw-extra.js"],
          navigateFallback: "/",

          navigateFallbackDenylist: [
            /^\/api\//,
            /^\/sitemap\.xml$/,
            /^\/rss\.xml$/,
            /^\/robots\.txt$/,
            /^\/~/,
          ],
          runtimeCaching: [
            {
              urlPattern: ({ request }) => request.mode === "navigate",
              handler: "NetworkFirst",
              options: {
                cacheName: "html",
                networkTimeoutSeconds: 3,
                expiration: { maxEntries: 50, maxAgeSeconds: 60 * 60 * 24 },
              },
            },
            {
              urlPattern: ({ request }) => request.destination === "image",
              handler: "StaleWhileRevalidate",
              options: {
                cacheName: "images",
                expiration: { maxEntries: 120, maxAgeSeconds: 60 * 60 * 24 * 30 },
              },
            },
            {
              urlPattern: ({ url }) => url.origin === "https://fonts.gstatic.com",
              handler: "CacheFirst",
              options: {
                cacheName: "google-fonts",
                expiration: { maxEntries: 30, maxAgeSeconds: 60 * 60 * 24 * 365 },
              },
            },
            // Article/CMS API responses — WordPress plugin REST endpoints.
            // NetworkFirst with a long offline-readable cache window.
            {
              urlPattern: ({ url }) =>
                url.pathname.startsWith("/wp-json/wp/v2/") ||
                url.pathname.startsWith("/wp-json/kpopblog/"),
              handler: "NetworkFirst",
              options: {
                cacheName: "cms-api",
                networkTimeoutSeconds: 4,
                expiration: { maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 7 },
                cacheableResponse: { statuses: [0, 200] },
              },
            },
            // Supabase REST/Storage reads (when CMS provider is Supabase).
            {
              urlPattern: ({ url }) =>
                url.hostname.endsWith(".supabase.co") &&
                (url.pathname.startsWith("/rest/v1/") || url.pathname.startsWith("/storage/v1/object/public/")),
              handler: "NetworkFirst",
              options: {
                cacheName: "supabase-read",
                networkTimeoutSeconds: 4,
                expiration: { maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 7 },
                cacheableResponse: { statuses: [0, 200] },
              },
            },

          ],
        },
      }),
    ],
  },
});
