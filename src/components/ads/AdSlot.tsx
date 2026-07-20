import { useEffect, useRef } from "react";
import { cn } from "@/lib/utils";
import { useConsent } from "@/hooks/useConsent";

export type AdSlotVariant =
  | "leaderboard"
  | "billboard"
  | "rectangle"
  | "skyscraper"
  | "sidebar"
  | "in-feed"
  | "in-article"
  | "sticky-footer"
  | "mobile-banner";

const VARIANT_STYLES: Record<AdSlotVariant, string> = {
  leaderboard: "min-h-[90px] md:min-h-[90px] max-w-[970px]",
  billboard: "min-h-[180px] md:min-h-[250px] max-w-[970px]",
  rectangle: "min-h-[250px] max-w-[336px]",
  skyscraper: "min-h-[600px] max-w-[300px]",
  sidebar: "min-h-[250px] w-full",
  "in-feed": "min-h-[120px] w-full",
  "in-article": "min-h-[250px] w-full max-w-[640px]",
  "sticky-footer": "min-h-[60px] w-full",
  "mobile-banner": "min-h-[60px] w-full max-w-[480px]",
};

const VARIANT_LABEL: Record<AdSlotVariant, string> = {
  leaderboard: "728 × 90",
  billboard: "970 × 250",
  rectangle: "300 × 250",
  skyscraper: "300 × 600",
  sidebar: "Sidebar",
  "in-feed": "In-Feed Native",
  "in-article": "In-Article",
  "sticky-footer": "Sticky Footer",
  "mobile-banner": "320 × 50",
};

type AdsConfig = {
  enabled: boolean;
  publisherId: string;
  slots: Record<string, string>;
};

type WordPressConfig = { apiUrl?: string; ads?: AdsConfig };
type AdSenseWindow = Window & typeof globalThis & { adsbygoogle?: Array<Record<string, never>> };

export interface AdSlotProps {
  slotId: string;
  variant?: AdSlotVariant;
  className?: string;
  label?: string;
}

function wordpressConfig(): WordPressConfig | null {
  if (typeof window === "undefined") return null;
  return (window as typeof window & { kpopblogConfig?: WordPressConfig }).kpopblogConfig ?? null;
}

function logicalPlacement(slotId: string) {
  if (slotId.startsWith("article-") && slotId.endsWith("-pre-related"))
    return "article-pre-related";
  if (slotId.startsWith("article-") && slotId.endsWith("-inline")) return "article-inline";
  if (slotId.startsWith("watch-") && slotId.endsWith("-sidebar")) return "watch-sidebar";
  if (slotId.startsWith("watch-") && slotId.endsWith("-inline")) return "watch-inline";
  return slotId;
}

function ensureAdSenseScript(publisherId: string) {
  const id = "kpopblog-app-adsense-runtime";
  if (document.getElementById(id)) return;
  const script = document.createElement("script");
  script.id = id;
  script.async = true;
  script.crossOrigin = "anonymous";
  script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(publisherId)}`;
  document.head.appendChild(script);
}

export function AdSlot({ slotId, variant = "rectangle", className, label }: AdSlotProps) {
  const ref = useRef<HTMLModElement>(null);
  const { preferences } = useConsent();
  const config = wordpressConfig();
  const isWordPress = Boolean(config?.apiUrl);
  const ads = config?.ads;
  const adUnit = ads?.slots?.[logicalPlacement(slotId)];
  const canRender = Boolean(
    isWordPress &&
    ads?.enabled &&
    /^ca-pub-\d{16}$/.test(ads.publisherId) &&
    /^\d{4,20}$/.test(adUnit ?? "") &&
    preferences?.advertising,
  );

  useEffect(() => {
    const element = ref.current;
    if (!canRender || !element || !ads) return;
    if (element.dataset.kbAdsenseRequested === "1") return;
    element.dataset.kbAdsenseRequested = "1";
    ensureAdSenseScript(ads.publisherId);
    const adsWindow = window as AdSenseWindow;
    adsWindow.adsbygoogle = adsWindow.adsbygoogle ?? [];
    adsWindow.adsbygoogle.push({});
  }, [ads, canRender]);

  if (isWordPress && !canRender) return null;

  if (canRender && ads && adUnit) {
    return (
      <aside
        aria-label={label ?? "Advertisement"}
        data-ad-slot={slotId}
        className={cn("mx-auto my-6 w-full overflow-hidden", VARIANT_STYLES[variant], className)}
      >
        <ins
          ref={ref}
          className="adsbygoogle block"
          data-ad-client={ads.publisherId}
          data-ad-slot={adUnit}
          data-ad-format="auto"
          data-full-width-responsive="true"
        />
      </aside>
    );
  }

  return (
    <aside
      aria-label={label ?? "Advertisement"}
      data-ad-slot={slotId}
      data-ad-variant={variant}
      className={cn(
        "mx-auto my-6 grid w-full place-items-center rounded-xl border border-dashed border-border bg-muted/30 px-4 py-3 text-center",
        VARIANT_STYLES[variant],
        className,
      )}
    >
      <div className="flex flex-col items-center gap-1">
        <span className="text-[10px] uppercase tracking-[0.18em] text-muted-foreground/70">
          {label ?? "Advertisement"}
        </span>
        <span className="font-mono text-xs text-muted-foreground/60">
          {VARIANT_LABEL[variant]} · {slotId}
        </span>
      </div>
    </aside>
  );
}

export function StickyFooterAd({ slotId = "global-sticky-footer" }: { slotId?: string }) {
  if (wordpressConfig()?.apiUrl) return null;
  return (
    <div className="pointer-events-none sticky bottom-16 z-30 px-2 pb-2 lg:hidden">
      <div className="pointer-events-auto rounded-xl bg-background/95 shadow-lg backdrop-blur">
        <AdSlot slotId={slotId} variant="sticky-footer" className="my-0" />
      </div>
    </div>
  );
}
