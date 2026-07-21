import { createFileRoute } from "@tanstack/react-router";
import { buildHead } from "@/components/layout/seo";

export const Route = createFileRoute("/copyright")({
  head: () => buildHead({ title: "DMCA / Copyright", canonical: "/copyright" }),
  component: () => (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="font-display text-4xl font-bold mb-6">DMCA / Copyright</h1>
      <div className="space-y-4 text-muted-foreground"><p>KpopBlog respects intellectual property rights. To request review of material you believe is unauthorized, use the private submission form and choose “News tip.”</p><p>Include the protected work, the exact KpopBlog URL, the location of the material on that page, your contact details, the basis for your request, and confirmation that the information you provide is accurate and that you are authorized to act for the rights holder. The editorial team may request additional information before acting.</p><a href="/submit" className="text-primary hover:underline">Submit a copyright request</a></div>
    </div>
  ),
});
