import { createFileRoute } from "@tanstack/react-router";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { ArticleCard } from "@/components/articles/ArticleCard";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/author/$slug")({
  head: ({ params }) =>
    buildHead({ title: `Author · ${params.slug}`, canonical: `/author/${params.slug}` }),
  component: AuthorPage,
});

function AuthorPage() {
  const { slug } = Route.useParams();
  const { data } = useRuntimeData();
  const name = slug
    .split("-")
    .map((s: string) => s[0]?.toUpperCase() + s.slice(1))
    .join(" ");
  const articles = data.articles.filter(
    (a) => a.author.toLowerCase().replace(/\s+/g, "-") === slug,
  );
  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <div className="flex items-center gap-4 mb-8">
        <div className="size-20 rounded-full gradient-neon" />
        <div>
          <h1 className="font-display text-3xl font-bold">{name}</h1>
          <p className="text-muted-foreground">Contributor · {articles.length} stories</p>
        </div>
      </div>
      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {articles.map((a) => (
          <ArticleCard key={a.id} article={a} />
        ))}
      </div>
    </div>
  );
}
