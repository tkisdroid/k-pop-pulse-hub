import { createFileRoute } from "@tanstack/react-router";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { SectionHeader } from "@/components/layout/SectionHeader";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/category/$slug")({
  head: ({ params }) => buildHead({ title: `${params.slug} news`, canonical: `/category/${params.slug}` }),
  component: CategoryPage,
});

function CategoryPage() {
  const { slug } = Route.useParams();
  const { data } = useRuntimeData();
  const articles = data.articles.filter((a) => a.category.toLowerCase() === slug.toLowerCase());
  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <SectionHeader eyebrow="Category" title={slug.charAt(0).toUpperCase() + slug.slice(1)} subtitle={`All ${slug} stories from the KpopBlog team and contributors.`} />
      {articles.length === 0 ? <div className="py-16 text-center text-muted-foreground">No articles in this category yet.</div> : (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{articles.map((a) => <ArticleCard key={a.id} article={a} />)}</div>
      )}
    </div>
  );
}
