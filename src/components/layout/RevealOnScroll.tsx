import { useEffect } from "react";

/**
 * Global scroll-reveal observer.
 * Any element with [data-reveal] or [data-reveal-children] gets an
 * `.is-visible` class as soon as it enters the viewport, triggering
 * CSS fade-up transitions defined in styles.css.
 *
 * The hidden starting state only applies while <html> carries `kb-reveal`,
 * which is set here, so content stays visible if this never runs. Elements
 * are picked up whenever they are added to the DOM (lists usually render
 * after their data loads, long after the route changed), and any overlap
 * with the viewport counts: on phones a one-column grid can be many screens
 * tall, so a percentage threshold would never be reached.
 */
export function RevealOnScroll() {
  useEffect(() => {
    if (typeof window === "undefined") return;
    if (typeof IntersectionObserver === "undefined" || typeof MutationObserver === "undefined") return;

    const root = document.documentElement;
    const selector = "[data-reveal]:not(.is-visible), [data-reveal-children]:not(.is-visible)";
    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        }
      },
      { rootMargin: "0px 0px -24px 0px", threshold: 0 },
    );

    const scan = () => {
      document.querySelectorAll<HTMLElement>(selector).forEach((el) => io.observe(el));
    };

    let frame = 0;
    const mo = new MutationObserver(() => {
      if (frame) return;
      frame = window.requestAnimationFrame(() => {
        frame = 0;
        scan();
      });
    });

    root.classList.add("kb-reveal");
    scan();
    mo.observe(document.body, { childList: true, subtree: true });

    return () => {
      if (frame) window.cancelAnimationFrame(frame);
      mo.disconnect();
      io.disconnect();
      root.classList.remove("kb-reveal");
    };
  }, []);

  return null;
}
