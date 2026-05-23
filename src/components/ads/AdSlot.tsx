import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

export type AdSlotVariant =
  | "leaderboard"   // 728x90 / responsive top banner
  | "billboard"     // 970x250 large
  | "rectangle"     // 300x250 / 336x280 in-feed/in-article
  | "skyscraper"    // 300x600 sidebar
  | "sidebar"       // responsive sidebar
  | "in-feed"       // native in-feed card
  | "in-article"    // in-article block
  | "sticky-footer" // mobile sticky bottom
  | "mobile-banner"; // 320x50 / 320x100

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

export interface AdSlotProps {
  /** Logical slot id for analytics / ad server (e.g. "home-top"). */
  slotId: string;
  variant?: AdSlotVariant;
  className?: string;
  /** Optional label shown to indicate placement; defaults to "Advertisement". */
  label?: string;
}

/**
 * Reusable ad placement. Renders a labeled placeholder by default.
 * To inject real ad code, set `window.__kbAdRender = (slotId, el) => {...}`
 * (e.g. AdSense, GAM) before the page mounts.
 */
export function AdSlot({ slotId, variant = "rectangle", className, label }: AdSlotProps) {
  const [filled, setFilled] = useState(false);

  useEffect(() => {
    const w = window as unknown as {
      __kbAdRender?: (slotId: string, el: HTMLElement) => boolean | void;
    };
    const el = document.querySelector<HTMLElement>(`[data-ad-slot="${slotId}"]`);
    if (el && typeof w.__kbAdRender === "function") {
      const result = w.__kbAdRender(slotId, el);
      if (result !== false) setFilled(true);
    }
  }, [slotId]);

  return (
    <aside
      aria-label={label ?? "Advertisement"}
      data-ad-slot={slotId}
      data-ad-variant={variant}
      className={cn(
        "mx-auto my-6 w-full rounded-xl border border-dashed border-border bg-muted/30",
        "grid place-items-center text-center px-4 py-3",
        VARIANT_STYLES[variant],
        className,
      )}
    >
      {!filled && (
        <div className="flex flex-col items-center gap-1">
          <span className="text-[10px] uppercase tracking-[0.18em] text-muted-foreground/70">
            {label ?? "Advertisement"}
          </span>
          <span className="text-xs text-muted-foreground/60 font-mono">
            {VARIANT_LABEL[variant]} · {slotId}
          </span>
        </div>
      )}
    </aside>
  );
}

/** Sticky bottom ad — typically mobile only. */
export function StickyFooterAd({ slotId = "global-sticky-footer" }: { slotId?: string }) {
  return (
    <div className="lg:hidden sticky bottom-16 z-30 px-2 pb-2 pointer-events-none">
      <div className="pointer-events-auto bg-background/95 backdrop-blur rounded-xl shadow-lg">
        <AdSlot slotId={slotId} variant="sticky-footer" className="my-0" />
      </div>
    </div>
  );
}
