import { useEffect } from "react";
import { useRouterState } from "@tanstack/react-router";

/**
 * Global scroll-reveal observer.
 * Any element with [data-reveal] or [data-reveal-children] gets an
 * `.is-visible` class when 12% of it enters the viewport, triggering
 * CSS fade-up transitions defined in styles.css.
 */
export function RevealOnScroll() {
  const pathname = useRouterState({ select: (s) => s.location.pathname });

  useEffect(() => {
    if (typeof window === "undefined") return;
    if (typeof IntersectionObserver === "undefined") {
      document.querySelectorAll<HTMLElement>("[data-reveal], [data-reveal-children]")
        .forEach((el) => el.classList.add("is-visible"));
      return;
    }

    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        }
      },
      { rootMargin: "0px 0px -8% 0px", threshold: 0.12 },
    );

    const scan = () => {
      document
        .querySelectorAll<HTMLElement>("[data-reveal]:not(.is-visible), [data-reveal-children]:not(.is-visible)")
        .forEach((el) => io.observe(el));
    };

    // Initial scan + a couple of frames after route renders
    scan();
    const t1 = window.setTimeout(scan, 80);
    const t2 = window.setTimeout(scan, 280);

    return () => {
      window.clearTimeout(t1);
      window.clearTimeout(t2);
      io.disconnect();
    };
  }, [pathname]);

  return null;
}
