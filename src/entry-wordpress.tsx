/**
 * Pure client-side entry for the WordPress plugin build (vite.config.wordpress.ts).
 *
 * The default TanStack Start entry (used by `vite build`) assumes it owns the
 * whole document — its root route's shellComponent renders <html>/<head>/
 * <body> and the client bootstrap hydrates against SSR-rendered markup. That
 * breaks when mounted inside a WordPress page that already has its own
 * <html>/<body> (see wordpress-plugin/kpopblog/templates/app-shell.php):
 * there's no SSR payload to hydrate, and nothing should try to re-render the
 * document root.
 *
 * This entry sidesteps all of that by using plain @tanstack/react-router
 * client rendering — RouterProvider renders the root route's `component`
 * (not `shellComponent`) as a normal React tree, mounted with createRoot
 * into #kpopblog-root exactly like any other embedded SPA widget.
 */
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { RouterProvider } from "@tanstack/react-router";
import { getRouter } from "./router";
import "./styles.css";

const router = getRouter();
const container = document.getElementById("kpopblog-root");

if (container) {
  createRoot(container).render(
    <StrictMode>
      <RouterProvider router={router} />
    </StrictMode>,
  );
} else {
  console.error("[kpopblog] #kpopblog-root not found in the page — check that the [kpopblog] shortcode or app-shell template rendered it.");
}
