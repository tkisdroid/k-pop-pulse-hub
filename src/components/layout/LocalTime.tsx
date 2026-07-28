import { useEffect, useState } from "react";

interface Props {
  value: string | number | Date;
  mode?: "date" | "time" | "datetime";
  className?: string;
}

function toDate(value: string | number | Date): Date {
  return value instanceof Date ? value : new Date(value);
}

// Locale-independent fallback so server and client first renders match.
function fallbackText(value: string | number | Date): string {
  const d = toDate(value);
  return Number.isNaN(d.getTime()) ? "" : d.toISOString().slice(0, 10);
}

function localText(value: string | number | Date, mode: NonNullable<Props["mode"]>): string {
  const d = toDate(value);
  if (Number.isNaN(d.getTime())) return "";
  if (mode === "date") return d.toLocaleDateString();
  if (mode === "time") return d.toLocaleTimeString();
  return d.toLocaleString();
}

/**
 * Renders a date/time in the visitor's locale without hydration mismatches:
 * SSR and the client first render emit the same UTC fallback, then an effect
 * swaps in the locale-formatted string after mount.
 */
export function LocalTime({ value, mode = "datetime", className }: Props) {
  const [text, setText] = useState(() => fallbackText(value));

  useEffect(() => {
    setText(localText(value, mode));
  }, [value, mode]);

  const date = toDate(value);
  return (
    <time dateTime={Number.isNaN(date.getTime()) ? undefined : date.toISOString()} className={className}>
      {text}
    </time>
  );
}
