import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { demoData } from "@/data/demo";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/forum/$categorySlug")({
  loader: ({ params }) => {
    const cat = demoData.categories.find((c) => c.slug === params.categorySlug);
    if (!cat) throw notFound();
    return cat;
  },
  head: ({ loaderData }) => buildHead({ title: loaderData?.name ?? "Category", canonical: `/forum/${loaderData?.slug}` }),
  component: CategoryPage,
});

function CategoryPage() {
  const cat = Route.useLoaderData();
  const threads = demoData.threads.filter((t) => t.categoryId === cat.id);
  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <div className="flex items-center justify-between mb-6 gap-4">
        <div className="flex items-center gap-3">
          <div className="size-12 grid place-items-center rounded-lg bg-accent text-2xl">{cat.icon}</div>
          <div>
            <h1 className="font-display text-3xl font-bold">{cat.name}</h1>
            <p className="text-sm text-muted-foreground">{cat.description}</p>
          </div>
        </div>
        <Button>Create thread</Button>
      </div>
      <div className="flex gap-2 mb-4">
        {["Newest", "Hot", "Top", "Unanswered"].map((s) => <button key={s} className="px-3 py-1.5 rounded-full text-sm bg-accent">{s}</button>)}
      </div>
      <div className="grid gap-2">
        {threads.length === 0 ? <div className="py-16 text-center text-muted-foreground">No threads yet — be the first.</div> :
          threads.map((t) => (
            <Link key={t.id} to="/thread/$threadSlug" params={{ threadSlug: t.slug }} className="p-3 rounded-xl bg-card border border-border hover:border-primary/40">
              <div className="font-semibold">{t.title}</div>
              <div className="text-xs text-muted-foreground">{t.replies} replies · {t.views} views</div>
            </Link>
          ))}
      </div>
    </div>
  );
}
