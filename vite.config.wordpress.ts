// Separate build target for the WordPress plugin — produces a pure
// client-side (no SSR, no TanStack Start) bundle of src/entry-wordpress.tsx.
// Deliberately does NOT use @lovable.dev/vite-tanstack-config: that preset
// always wires in the tanstackStart plugin, which is exactly what breaks
// embedding inside an existing WordPress page (see entry-wordpress.tsx).
// Plugin choices below (tailwindcss, tsconfig-paths, react, the "@" alias,
// and the dedupe list) mirror what that preset configures so this build
// stays visually/behaviorally identical to the main `vite build` output.
import path from "node:path";
import { defineConfig, loadEnv } from "vite";
import tailwindcss from "@tailwindcss/vite";
import { tanstackRouter } from "@tanstack/router-plugin/vite";
import tsConfigPaths from "vite-tsconfig-paths";
import viteReact from "@vitejs/plugin-react";

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "VITE_");
  const envDefine: Record<string, string> = {};
  for (const [key, value] of Object.entries(env)) {
    envDefine[`import.meta.env.${key}`] = JSON.stringify(value);
  }

  return {
    define: envDefine,
    resolve: {
      alias: { "@": path.resolve(__dirname, "src") },
      dedupe: [
        "react",
        "react-dom",
        "react/jsx-runtime",
        "react/jsx-dev-runtime",
        "@tanstack/react-query",
        "@tanstack/query-core",
      ],
    },
    plugins: [
      tanstackRouter({
        target: "react",
        autoCodeSplitting: true,
        routeTreeFileFooter: [
          `import type { getRouter } from './router.tsx'
import type { startInstance } from './start.ts'
declare module '@tanstack/react-start' {
  interface Register {
    ssr: true
    router: Awaited<ReturnType<typeof getRouter>>
    config: Awaited<ReturnType<typeof startInstance.getOptions>>
  }
}`,
        ],
      }),
      tailwindcss(),
      tsConfigPaths({ projects: ["./tsconfig.json"] }),
      viteReact(),
    ],
    // Assets are served from a nested WordPress plugin path
    // (/wp-content/plugins/kpopblog/assets/assets/...), so chunk/asset URLs
    // must resolve relative to the bundle itself, not the site root.
    base: "./",
    build: {
      outDir: "dist/wordpress",
      emptyOutDir: true,
      rollupOptions: {
        input: path.resolve(__dirname, "src/entry-wordpress.tsx"),
        output: {
          entryFileNames: "assets/index-[hash].js",
          chunkFileNames: "assets/chunk-[hash].js",
          assetFileNames: "assets/[name]-[hash][extname]",
        },
      },
    },
  };
});
