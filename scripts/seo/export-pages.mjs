import fs from "node:fs";
import ts from "typescript";
const pages = {};
const escape = (s) =>
  s.replace(
    /[&<>"']/g,
    (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[c],
  );
const allowed = new Set(["h1", "h2", "h3", "p", "ul", "ol", "li", "a", "strong", "em"]);
function render(node) {
  if (ts.isJsxText(node)) return node.text.replace(/\s+/g, " ");
  if (ts.isJsxExpression(node))
    return node.expression && ts.isStringLiteral(node.expression)
      ? escape(node.expression.text)
      : "";
  if (ts.isJsxElement(node)) {
    const tag = node.openingElement.tagName.getText();
    const body = node.children.map(render).join("").trim();
    if (!allowed.has(tag)) return body;
    let attrs = "";
    if (tag === "a") {
      const href = node.openingElement.attributes.properties.find(
        (p) => p.name?.getText() === "href",
      );
      if (
        href?.initializer &&
        ts.isStringLiteral(href.initializer) &&
        /^(\/|https?:)/.test(href.initializer.text)
      )
        attrs = ` href="${escape(href.initializer.text)}"`;
    }
    return `<${tag}${attrs}>${body}</${tag}>`;
  }
  return "";
}
for (const slug of [
  "about",
  "contact",
  "advertise",
  "privacy",
  "terms",
  "copyright",
  "corrections",
  "community-guidelines",
]) {
  const file = ts.createSourceFile(
    `${slug}.tsx`,
    fs.readFileSync(`src/routes/${slug}.tsx`, "utf8"),
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX,
  );
  let html = "";
  function visit(node) {
    if (ts.isJsxElement(node) && !html) {
      html = render(node);
      return;
    }
    ts.forEachChild(node, visit);
  }
  visit(file);
  const title = html.match(/<h1>(.*?)<\/h1>/)?.[1];
  const description = [...html.matchAll(/<(?:p|li)>(.*?)<\/(?:p|li)>/g)]
    .map((m) => m[1].replace(/<[^>]+>/g, ""))
    .find((p) => !p.startsWith("Last updated:"));
  if (!title || !description) throw new Error(`Missing static SEO content: ${slug}`);
  pages[`/${slug}`] = { title, description: description.slice(0, 160), html };
}
const output = JSON.stringify(pages, null, 2) + "\n";
const target = "wordpress-plugin/kpopblog/data/site-pages.json";
if (process.argv.includes("--check")) {
  if (fs.readFileSync(target, "utf8") !== output)
    throw new Error("Static SEO pages are stale. Run npm run seo:pages.");
} else fs.writeFileSync(target, output);
console.log(`Static SEO content verified: ${Object.keys(pages).length} pages`);
