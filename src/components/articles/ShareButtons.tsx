import { useState } from "react";
import { Twitter, Facebook, Link as LinkIcon, MessageCircle, Check } from "lucide-react";
import { Button } from "@/components/ui/button";

export function ShareButtons({ title, url }: { title: string; url?: string }) {
  const [copied, setCopied] = useState(false);
  const shareUrl = url ?? (typeof window !== "undefined" ? window.location.href : "");
  const encodedUrl = encodeURIComponent(shareUrl);
  const encodedText = encodeURIComponent(title);

  function open(href: string) {
    window.open(href, "_blank", "noopener,noreferrer,width=600,height=600");
  }

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    } catch {
      /* noop */
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-2" aria-label="Share">
      <Button
        size="sm"
        variant="outline"
        onClick={() =>
          open(`https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`)
        }
        aria-label="Share on X"
      >
        <Twitter className="size-3" /> X
      </Button>
      <Button
        size="sm"
        variant="outline"
        onClick={() => open(`https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`)}
        aria-label="Share on Facebook"
      >
        <Facebook className="size-3" /> Facebook
      </Button>
      <Button
        size="sm"
        variant="outline"
        onClick={() => open(`https://story.kakao.com/share?url=${encodedUrl}`)}
        aria-label="Share on KakaoStory"
      >
        <MessageCircle className="size-3" /> Kakao
      </Button>
      <Button size="sm" variant="outline" onClick={copyLink} aria-label="Copy link">
        {copied ? <Check className="size-3" /> : <LinkIcon className="size-3" />}
        {copied ? "Copied" : "Copy link"}
      </Button>
    </div>
  );
}
