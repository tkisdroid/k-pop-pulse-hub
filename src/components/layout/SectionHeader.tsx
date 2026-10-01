interface Props {
  title: string;
  as?: "h1" | "h2";
  subtitle?: string;
  eyebrow?: string;
}
export function SectionHeader({ title, subtitle, eyebrow, as: Heading = "h2" }: Props) {
  return (
    <div className="mb-6 flex items-end justify-between gap-4">
      <div>
        {eyebrow && (
          <div className="text-xs uppercase tracking-widest text-primary font-semibold mb-1">
            {eyebrow}
          </div>
        )}
        <Heading className="font-display text-2xl md:text-3xl font-bold">{title}</Heading>
        {subtitle && <p className="text-sm text-muted-foreground mt-1">{subtitle}</p>}
      </div>
    </div>
  );
}
