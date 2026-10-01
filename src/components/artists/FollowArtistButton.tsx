import { useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { useAuthModal } from "@/hooks/useAuthModal";
import { cmsProvider } from "@/services/cms";
import type { Artist } from "@/types";

export function FollowArtistButton({
  artist,
  size = "default",
  variant = "default",
}: {
  artist: Artist;
  size?: "sm" | "default";
  variant?: "default" | "secondary" | "outline";
}) {
  const { user } = useAuth();
  const { show } = useAuthModal();
  const [following, setFollowing] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setFollowing(Boolean(user?.followedArtists.includes(artist.id)));
  }, [artist.id, user?.followedArtists]);

  async function toggle() {
    if (!user) {
      show("Log in to follow artists");
      return;
    }
    if (!cmsProvider.toggleFollowArtist || saving) return;
    setSaving(true);
    const result = await cmsProvider.toggleFollowArtist(artist.slug);
    setSaving(false);
    if (!result.ok) {
      toast.error(result.error ?? "Follow preference could not be saved.");
      return;
    }
    setFollowing(Boolean(result.following));
    toast.success(result.following ? `Following ${artist.name}` : `Unfollowed ${artist.name}`);
  }

  return (
    <Button
      type="button"
      size={size}
      variant={following ? "secondary" : variant}
      disabled={saving}
      aria-pressed={following}
      onClick={() => void toggle()}
    >
      {saving && <Loader2 className="size-3 animate-spin" />}
      {following ? "Following" : "Follow"}
    </Button>
  );
}
