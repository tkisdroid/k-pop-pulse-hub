import { useRouter } from "@tanstack/react-router";
import type { MouseEvent, ReactNode } from "react";

const URL_PATTERN = /(https?:\/\/[^\s<]+[^\s<.,;:!?)"'”’])/g;

function isSameSite(href: string) {
  if (typeof window === "undefined") return false;
  try {
    return new URL(href, window.location.href).origin === window.location.origin;
  } catch {
    return false;
  }
}

/**
 * Plain text with clickable links. Same-site links navigate inside the app;
 * other links open in a new tab so the site stays open. A bullet line written
 * as "• Headline — https://…" links the headline itself.
 */
export function LinkifiedText({ text, className }: { text: string; className?: string }) {
  const router = useRouter();

  const renderLink = (href: string, label: ReactNode, key: string) => {
    if (isSameSite(href)) {
      const url = new URL(href, window.location.href);
      const onClick = (event: MouseEvent<HTMLAnchorElement>) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        router.history.push(url.pathname + url.search + url.hash);
      };
      return (
        <a key={key} href={url.pathname + url.search + url.hash} onClick={onClick} className="text-primary hover:underline">
          {label}
        </a>
      );
    }
    return (
      <a key={key} href={href} target="_blank" rel="nofollow noopener noreferrer" className="underline decoration-muted-foreground/40">
        {label}
      </a>
    );
  };

  const lines = text.split("\n");
  return (
    <div className={className}>
      {lines.map((line, index) => {
        const bullet = line.match(/^•\s+(.+?)\s+—\s+(https?:\/\/\S+)$/);
        if (bullet) {
          return (
            <div key={index} className="pl-1">
              • {renderLink(bullet[2], bullet[1], `b${index}`)}
            </div>
          );
        }
        if (line.trim() === "") return <div key={index} className="h-3" />;
        const parts = line.split(URL_PATTERN);
        return (
          <div key={index}>
            {parts.map((part, partIndex) => {
              if (!/^https?:\/\//.test(part)) return part;
              const label = isSameSite(part) ? new URL(part).pathname : part.replace(/^https?:\/\/(www\.)?/, "");
              return renderLink(part, label, `${index}-${partIndex}`);
            })}
          </div>
        );
      })}
    </div>
  );
}
