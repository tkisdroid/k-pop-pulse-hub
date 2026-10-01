import { useEffect, useState } from "react";
import { X, ChevronLeft, ChevronRight } from "lucide-react";

export function useProseLightbox(containerRef: React.RefObject<HTMLElement | null>) {
  const [images, setImages] = useState<{ src: string; alt: string }[]>([]);
  const [index, setIndex] = useState<number | null>(null);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;
    const imgs = Array.from(container.querySelectorAll<HTMLImageElement>("img"));
    const list = imgs.map((img) => ({ src: img.currentSrc || img.src, alt: img.alt || "" }));
    setImages(list);
    const handlers: Array<() => void> = [];
    imgs.forEach((img, i) => {
      img.style.cursor = "zoom-in";
      const handler = () => setIndex(i);
      img.addEventListener("click", handler);
      handlers.push(() => img.removeEventListener("click", handler));
    });
    return () => handlers.forEach((h) => h());
  }, [containerRef]);

  useEffect(() => {
    if (index === null) return;
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setIndex(null);
      if (e.key === "ArrowRight") setIndex((i) => (i === null ? null : (i + 1) % images.length));
      if (e.key === "ArrowLeft")
        setIndex((i) => (i === null ? null : (i - 1 + images.length) % images.length));
    }
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [index, images.length]);

  const lightbox =
    index !== null && images[index] ? (
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Image viewer"
        className="fixed inset-0 z-[100] bg-black/95 flex items-center justify-center"
        onClick={() => setIndex(null)}
      >
        <button
          onClick={(e) => {
            e.stopPropagation();
            setIndex(null);
          }}
          className="absolute top-4 right-4 size-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center"
          aria-label="Close"
        >
          <X className="size-5" />
        </button>
        {images.length > 1 && (
          <>
            <button
              onClick={(e) => {
                e.stopPropagation();
                setIndex((i) => (i === null ? null : (i - 1 + images.length) % images.length));
              }}
              className="absolute left-4 size-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center"
              aria-label="Previous"
            >
              <ChevronLeft className="size-5" />
            </button>
            <button
              onClick={(e) => {
                e.stopPropagation();
                setIndex((i) => (i === null ? null : (i + 1) % images.length));
              }}
              className="absolute right-4 size-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center"
              aria-label="Next"
            >
              <ChevronRight className="size-5" />
            </button>
            <div className="absolute bottom-4 left-1/2 -translate-x-1/2 text-xs text-white/70">
              {index + 1} / {images.length}
            </div>
          </>
        )}
        <img
          src={images[index].src}
          alt={images[index].alt}
          onClick={(e) => e.stopPropagation()}
          className="max-h-[90vh] max-w-[92vw] object-contain"
        />
      </div>
    ) : null;

  return { lightbox };
}
