import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { useRuntimeData } from "@/services/cms/runtimeData";
import { buildHead } from "@/components/layout/seo";
import { SectionHeader } from "@/components/layout/SectionHeader";

export const Route = createFileRoute("/search")({
  head: () => buildHead({ title: "Search", canonical: "/search" }),
  component: Search,
});

function Search() {
  const { data } = useRuntimeData();
  const [q, setQ] = useState("");
  const ql = q.toLowerCase();
  const articles = q ? data.articles.filter((a) => a.title.toLowerCase().includes(ql)) : [];
  const artists = q ? data.artists.filter((a) => a.name.toLowerCase().includes(ql)) : [];
  const members = q ? data.members.filter((m) => m.stageName.toLowerCase().includes(ql)) : [];
  const threads = q ? data.threads.filter((t) => t.title.toLowerCase().includes(ql)) : [];

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <SectionHeader as="h1" eyebrow="Search" title="Find anything" />
      <input
        autoFocus
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="Search articles, artists, members, threads..."
        className="w-full h-12 px-4 rounded-xl bg-card border border-input text-base"
      />
      {!q && (
        <div className="mt-6 text-sm text-muted-foreground">
          <div className="mb-2">
            Trending: <span className="text-foreground">comeback, debut, world tour, MV</span>
          </div>
        </div>
      )}
      {q && (
        <div className="mt-6 space-y-6">
          <section>
            <h3 className="font-display font-bold mb-2">Articles ({articles.length})</h3>
            {articles.slice(0, 5).map((a) => (
              <Link
                key={a.id}
                to="/news/$slug"
                params={{ slug: a.slug }}
                className="block p-2 hover:bg-accent rounded"
              >
                {a.title}
              </Link>
            ))}
          </section>
          <section>
            <h3 className="font-display font-bold mb-2">Artists ({artists.length})</h3>
            {artists.slice(0, 5).map((a) => (
              <Link
                key={a.id}
                to="/artist/$slug"
                params={{ slug: a.slug }}
                className="block p-2 hover:bg-accent rounded"
              >
                {a.name}
              </Link>
            ))}
          </section>
          <section>
            <h3 className="font-display font-bold mb-2">Members ({members.length})</h3>
            {members.slice(0, 5).map((m) => (
              <Link
                key={m.id}
                to="/member/$slug"
                params={{ slug: m.slug }}
                className="block p-2 hover:bg-accent rounded"
              >
                {m.stageName}
              </Link>
            ))}
          </section>
          <section>
            <h3 className="font-display font-bold mb-2">Threads ({threads.length})</h3>
            {threads.slice(0, 5).map((t) => (
              <Link
                key={t.id}
                to="/thread/$threadSlug"
                params={{ threadSlug: t.slug }}
                className="block p-2 hover:bg-accent rounded"
              >
                {t.title}
              </Link>
            ))}
          </section>
        </div>
      )}
    </div>
  );
}
