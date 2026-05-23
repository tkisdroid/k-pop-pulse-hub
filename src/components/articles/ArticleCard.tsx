import { Link } from "@tanstack/react-router";
import type { Article } from "@/types";
import { MessageCircle, Heart, Clock } from "lucide-react";

export function ArticleCard({ article, variant = "default" }: { article: Article; variant?: "default" | "hero" | "compact" | "ranked"; rank?: number }) {
  if (variant === "compact") {
    return (
      <Link to="/news/$slug" params={{ slug: article.slug }} className="flex gap-3 group">
        <div className="w-24 h-20 rounded-lg overflow-hidden shrink-0"><img src={article.featuredImage} alt={article.title} className="size-full object-cover" /></div>
        <div className="min-w-0">
          <div className="text-xs uppercase tracking-wider text-primary mb-1">{article.category}</div>
          <h3 className="font-semibold leading-snug line-clamp-2 group-hover:text-primary transition-colors">{article.title}</h3>
        </div>
      </Link>
    );
  }
  if (variant === "hero") {
    return (
      <Link to="/news/$slug" params={{ slug: article.slug }} className="relative block aspect-[16/10] rounded-2xl overflow-hidden group">
        <img src={article.featuredImage} alt={article.title} className="size-full object-cover transition-transform group-hover:scale-105" />
        <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent" />
        <div className="absolute bottom-0 p-6 text-white">
          <span className="inline-block px-2 py-0.5 rounded text-xs uppercase tracking-wider bg-primary mb-3">{article.category}</span>
          <h2 className="font-display text-2xl md:text-3xl font-bold leading-tight">{article.title}</h2>
          <p className="mt-2 text-sm opacity-90 line-clamp-2">{article.excerpt}</p>
        </div>
      </Link>
    );
  }
  return (
    <Link to="/news/$slug" params={{ slug: article.slug }} className="group flex flex-col rounded-xl overflow-hidden bg-card border border-border hover:border-primary/40 transition-colors">
      <div className="aspect-video overflow-hidden"><img src={article.featuredImage} alt={article.title} className="size-full object-cover transition-transform group-hover:scale-105" /></div>
      <div className="p-4 flex-1 flex flex-col">
        <div className="text-xs uppercase tracking-wider text-primary mb-1">{article.category}</div>
        <h3 className="font-display font-semibold leading-snug line-clamp-2 group-hover:text-primary transition-colors">{article.title}</h3>
        <p className="mt-2 text-sm text-muted-foreground line-clamp-2">{article.excerpt}</p>
        <div className="mt-auto pt-3 flex items-center gap-3 text-xs text-muted-foreground">
          <span>{article.author}</span>
          <span className="flex items-center gap-1"><Clock className="size-3" />{article.readingTime}m</span>
          <span className="flex items-center gap-1"><MessageCircle className="size-3" />{article.commentCount}</span>
          <span className="flex items-center gap-1"><Heart className="size-3" />{article.reactionCount}</span>
        </div>
      </div>
    </Link>
  );
}
