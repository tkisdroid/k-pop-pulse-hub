import { useEffect, useState } from "react";

/** Thin gradient progress bar pinned to the top of the viewport. */
export function ScrollProgress() {
  const [pct, setPct] = useState(0);

  useEffect(() => {
    if (typeof window === "undefined") return;
    let raf = 0;
    const onScroll = () => {
      if (raf) return;
      raf = window.requestAnimationFrame(() => {
        const h = document.documentElement;
        const max = Math.max(1, h.scrollHeight - h.clientHeight);
        setPct(Math.min(100, (window.scrollY / max) * 100));
        raf = 0;
      });
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
    return () => {
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
      if (raf) cancelAnimationFrame(raf);
    };
  }, []);

  return (
    <div className="fixed top-0 inset-x-0 z-50 h-[3px] pointer-events-none">
      <div
        className="h-full gradient-neon origin-left transition-[width] duration-150 ease-out"
        style={{ width: `${pct}%`, boxShadow: pct > 1 ? "0 0 12px color-mix(in oklab, var(--neon-pink) 60%, transparent)" : undefined }}
      />
    </div>
  );
}
