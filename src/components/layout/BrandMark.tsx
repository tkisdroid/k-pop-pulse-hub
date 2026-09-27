import { cn } from "@/lib/utils";

/** The KpopBlog "K" mark: the same glyph as the favicon and app icons. */
export function BrandMark({ className }: { className?: string }) {
  return (
    <span className={cn("grid size-8 place-items-center rounded-lg gradient-neon", className)} aria-hidden="true">
      <svg viewBox="0 0 64 64" className="size-[78%]">
        <path d="M19 14h8.5v14.2L39.6 14h10.2L36.4 29.4 50.6 50H40.3L30.7 35.6l-3.2 3.6V50H19z" fill="#fff" />
      </svg>
    </span>
  );
}
