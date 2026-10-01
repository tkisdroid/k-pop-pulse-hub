import { useEffect } from "react";
import { WordPressSeo } from "@/components/layout/WordPressSeo";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
  Outlet,
  createRootRouteWithContext,
  useRouter,
  useRouterState,
  HeadContent,
  Scripts,
  Link,
} from "@tanstack/react-router";
import { registerPwa } from "@/pwa/register";
import { setupQueryPersistence } from "@/pwa/queryPersist";
import { localNotifications } from "@/services/notifications/local";
import { applyBranding } from "@/services/cms/branding";
import { OfflineBadge } from "@/components/layout/OfflineBadge";

import appCss from "../styles.css?url";
import { ThemeProvider } from "@/hooks/useTheme-provider";
import { I18nProvider } from "@/hooks/useI18n-provider";
import { AuthProviderShell } from "@/hooks/useAuth-provider";
import { AuthModalProvider } from "@/hooks/useAuthModal-provider";
import { Header } from "@/components/layout/Header";
import { Footer } from "@/components/layout/Footer";
import { MobileBottomNav } from "@/components/layout/MobileBottomNav";
import { AuthModal } from "@/components/auth/AuthModal";
import { AdSlot, AutoAdsLoader, StickyFooterAd } from "@/components/ads/AdSlot";
import { Breadcrumbs } from "@/components/layout/Breadcrumbs";
import { KeepExploring } from "@/components/layout/KeepExploring";
import { ScrollProgress } from "@/components/layout/ScrollProgress";
import { RevealOnScroll } from "@/components/layout/RevealOnScroll";
import { ConsentBanner } from "@/components/privacy/ConsentBanner";
import { TrafficTracker } from "@/components/analytics/TrafficTracker";

function NotFoundComponent() {
  return (
    <div className="min-h-screen grid place-items-center p-4">
      <div className="text-center max-w-md">
        <h1 className="font-display text-7xl font-bold text-gradient">404</h1>
        <p className="mt-3 text-muted-foreground">This page slipped past the editorial desk.</p>
        <Link
          to="/"
          className="mt-6 inline-block px-4 py-2 rounded-md bg-primary text-primary-foreground"
        >
          Go home
        </Link>
      </div>
    </div>
  );
}

function ErrorComponent({ error, reset }: { error: Error; reset: () => void }) {
  console.error(error);
  const router = useRouter();
  return (
    <div className="min-h-screen grid place-items-center p-4">
      <div className="text-center max-w-md">
        <h1 className="font-display text-2xl font-bold">Something went wrong</h1>
        <p className="mt-2 text-sm text-muted-foreground">{error.message}</p>
        <button
          onClick={() => {
            router.invalidate();
            reset();
          }}
          className="mt-4 px-4 py-2 rounded-md bg-primary text-primary-foreground"
        >
          Try again
        </button>
      </div>
    </div>
  );
}

export const Route = createRootRouteWithContext<{ queryClient: QueryClient }>()({
  head: () =>
    typeof window !== "undefined" && window.kpopblogConfig?.apiUrl
      ? {}
      : {
          meta: [
            { charSet: "utf-8" },
            { name: "viewport", content: "width=device-width, initial-scale=1" },
            { title: "KpopBlog — Your global K-pop newsroom & fan community" },
            {
              name: "description",
              content:
                "K-pop news, artist profiles, comeback calendar, polls and a global fan community.",
            },
            { property: "og:site_name", content: "KpopBlog" },
            { property: "og:type", content: "website" },
            { name: "twitter:card", content: "summary_large_image" },
            { name: "theme-color", content: "#0b0b10" },
            { name: "mobile-web-app-capable", content: "yes" },
            { name: "apple-mobile-web-app-capable", content: "yes" },
            { name: "apple-mobile-web-app-status-bar-style", content: "black-translucent" },
            { name: "apple-mobile-web-app-title", content: "KpopBlog" },
            {
              property: "og:title",
              content: "KpopBlog — Your global K-pop newsroom & fan community",
            },
            {
              name: "twitter:title",
              content: "KpopBlog — Your global K-pop newsroom & fan community",
            },
            {
              property: "og:description",
              content:
                "K-pop news, artist profiles, comeback calendar, polls and a global fan community.",
            },
            {
              name: "twitter:description",
              content:
                "K-pop news, artist profiles, comeback calendar, polls and a global fan community.",
            },
            {
              property: "og:image",
              content: "https://thekpopblog.com/og-default.jpg",
            },
            {
              name: "twitter:image",
              content: "https://thekpopblog.com/og-default.jpg",
            },
          ],
          links:
            typeof window !== "undefined" && window.kpopblogConfig?.apiUrl
              ? []
              : [
                  { rel: "stylesheet", href: appCss },
                  { rel: "manifest", href: "/manifest.webmanifest" },
                  { rel: "apple-touch-icon", sizes: "180x180", href: "/apple-touch-icon.png?v=2" },
                  { rel: "icon", type: "image/x-icon", href: "/favicon.ico?v=2" },
                  { rel: "icon", type: "image/svg+xml", href: "/favicon.svg?v=2" },
                  { rel: "icon", type: "image/png", sizes: "32x32", href: "/favicon-32.png?v=2" },
                  { rel: "preconnect", href: "https://fonts.googleapis.com" },
                  { rel: "preconnect", href: "https://fonts.gstatic.com", crossOrigin: "" },
                  {
                    rel: "stylesheet",
                    href: "https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap",
                  },
                  {
                    rel: "alternate",
                    type: "application/rss+xml",
                    title: "KpopBlog — Latest news",
                    href: "/rss.xml",
                  },
                  { rel: "sitemap", type: "application/xml", href: "/sitemap.xml" },
                ],
          scripts: [
            {
              type: "application/ld+json",
              children: JSON.stringify({
                "@context": "https://schema.org",
                "@type": "Organization",
                name: "KpopBlog",
                url: "https://thekpopblog.com/",
                description: "Global K-pop news and fan community.",
              }),
            },
            {
              type: "application/ld+json",
              children: JSON.stringify({
                "@context": "https://schema.org",
                "@type": "WebSite",
                name: "KpopBlog",
                url: "https://thekpopblog.com/",
                potentialAction: {
                  "@type": "SearchAction",
                  target: "https://thekpopblog.com/search?q={search_term_string}",
                  "query-input": "required name=search_term_string",
                },
              }),
            },
          ],
        },
  shellComponent: RootShell,
  component: RootComponent,
  notFoundComponent: NotFoundComponent,
  errorComponent: ErrorComponent,
});

function RootShell({ children }: { children: React.ReactNode }) {
  // Inside WordPress the app is mounted into #kpopblog-root within the page's own
  // <body>. Rendering <html>/<head>/<body> there makes React 19 adopt the real
  // document elements as part of a tree that lives inside <body>, and its event
  // dispatcher then loops forever on document-level events such as
  // "selectionchange" (the page freezes as soon as any input is focused).
  // <title>/<meta>/<link> from HeadContent are still hoisted into <head>.
  if (typeof window !== "undefined" && window.kpopblogConfig?.apiUrl) {
    return (
      <>
        <HeadContent />
        {children}
      </>
    );
  }
  return (
    <html lang="en" className="dark">
      <head>
        <HeadContent />
      </head>
      <body>
        {children}
        <Scripts />
      </body>
    </html>
  );
}

function RouteFadeOutlet() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  return (
    <main className="flex-1 pb-20 lg:pb-0">
      <div key={pathname} className="route-fade">
        <Outlet />
      </div>
    </main>
  );
}

function RootComponent() {
  const { queryClient } = Route.useRouteContext();
  useEffect(() => {
    applyBranding();
    setupQueryPersistence(queryClient);
    registerPwa();
    localNotifications.hydrate();
  }, [queryClient]);

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <I18nProvider>
          <AuthProviderShell>
            <AuthModalProvider>
              <ScrollProgress />
              <RevealOnScroll />
              <div className="min-h-screen flex flex-col">
                <Header />
                <div className="mx-auto max-w-7xl px-4 w-full">
                  <AdSlot slotId="global-top-leaderboard" variant="leaderboard" />
                </div>
                <Breadcrumbs />
                <RouteFadeOutlet />
                <KeepExploring />
                <div className="mx-auto max-w-7xl px-4 w-full">
                  <AdSlot slotId="global-pre-footer" variant="billboard" />
                </div>
                <StickyFooterAd />
                <AutoAdsLoader />
                <Footer />
                <MobileBottomNav />
              </div>
              <OfflineBadge />
              <AuthModal />
              <ConsentBanner />
              <TrafficTracker />
              <WordPressSeo />
            </AuthModalProvider>
          </AuthProviderShell>
        </I18nProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
