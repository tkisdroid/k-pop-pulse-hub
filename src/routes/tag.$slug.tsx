import { createFileRoute } from "@tanstack/react-router";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/tag/$slug")({
  head: ({ params }) => buildHead({ title: `#${params.slug}`, canonical: `/tag/${params.slug}` }),
  component: TagPage,
});

function TagPage() {
  const { slug } = Route.useParams();
  const { data } = useRuntimeData();
  const articles = data.articles.filter((a) => a.tags.includes(slug));
  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <SectionHeader eyebrow="Tag" title={`#${slug}`} />
      {articles.length === 0 ? <div className="py-16 text-center text-muted-foreground">No articles tagged.</div> : (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{articles.map((a) => <ArticleCard key={a.id} article={a} />)}</div>
      )}
    </div>
  );
}
