interface Props { title: string; subtitle?: string; eyebrow?: string; }
export function SectionHeader({ title, subtitle, eyebrow }: Props) {
  return (
    <div className="mb-6 flex items-end justify-between gap-4">
      <div>
        {eyebrow && <div className="text-xs uppercase tracking-widest text-primary font-semibold mb-1">{eyebrow}</div>}
        <h2 className="font-display text-2xl md:text-3xl font-bold">{title}</h2>
        {subtitle && <p className="text-sm text-muted-foreground mt-1">{subtitle}</p>}
      </div>
    </div>
  );
}
