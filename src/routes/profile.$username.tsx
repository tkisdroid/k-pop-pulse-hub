import { createFileRoute, Link } from "@tanstack/react-router";
import { useCallback, useEffect, useState } from "react";
import { buildHead } from "@/components/layout/seo";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/useAuth";
import { communityProvider } from "@/services/community";
import type { PublicProfile } from "@/types";

export const Route = createFileRoute("/profile/$username")({
  head: ({ params }) =>
    buildHead({ title: params.username, canonical: `/profile/${params.username}` }),
  component: ProfilePage,
});

function ProfilePage() {
  const { username } = Route.useParams();
  const { user: me } = useAuth();
  const resolvedUsername = username === "me" ? me?.username : username;
  const [profile, setProfile] = useState<PublicProfile | null>(null);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({ displayName: "", bio: "", country: "", language: "" });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!resolvedUsername) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const next = await communityProvider.getProfile(resolvedUsername);
      setProfile(next);
      if (next)
        setForm({
          displayName: next.displayName,
          bio: next.bio ?? "",
          country: next.country ?? "",
          language: next.language ?? "",
        });
    } catch (loadError) {
      setError((loadError as Error).message);
    } finally {
      setLoading(false);
    }
  }, [resolvedUsername]);

  useEffect(() => {
    void load();
  }, [load]);
  const isMe = Boolean(me && profile && me.id === profile.id);

  async function save() {
    if (!isMe || saving) return;
    setSaving(true);
    setError(null);
    try {
      const updated = await communityProvider.updateProfile(form);
      setProfile(updated);
      setEditing(false);
      setNotice("Profile updated.");
    } catch (saveError) {
      setError((saveError as Error).message);
    } finally {
      setSaving(false);
    }
  }

  if (loading)
    return (
      <div className="mx-auto max-w-5xl px-4 py-16 text-center text-muted-foreground">
        Loading profile…
      </div>
    );
  if (!resolvedUsername)
    return (
      <div className="mx-auto max-w-5xl px-4 py-16 text-center">
        <p>Log in to view your profile.</p>
        <Button asChild className="mt-4">
          <Link to="/login">Log in</Link>
        </Button>
      </div>
    );
  if (!profile)
    return (
      <div className="mx-auto max-w-5xl px-4 py-16 text-center">
        <h1 className="text-2xl font-bold">Profile not found</h1>
      </div>
    );

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      {error && (
        <div
          className="mb-4 rounded-md border border-destructive/40 p-3 text-sm text-destructive"
          role="alert"
        >
          {error}{" "}
          <Button size="sm" variant="ghost" onClick={() => void load()}>
            Retry
          </Button>
        </div>
      )}
      {notice && (
        <p className="mb-4 rounded-md bg-accent p-3 text-sm" role="status">
          {notice}
        </p>
      )}
      <div className="mb-8 flex flex-col gap-6 md:flex-row">
        {profile.avatar && <img src={profile.avatar} alt="" className="size-24 rounded-full" />}
        <div className="min-w-0 flex-1">
          <h1 className="font-display text-3xl font-bold">{profile.displayName}</h1>
          <div className="text-sm text-muted-foreground">
            @{profile.username} · {profile.role} · Trust level {profile.trustLevel}
          </div>
          <p className="mt-2 whitespace-pre-wrap text-sm">
            {profile.bio || "This member has not added a bio."}
          </p>
          <div className="mt-3 flex flex-wrap gap-3 text-xs text-muted-foreground">
            <span>{profile.points} points</span>
            {profile.country && <span>Country: {profile.country}</span>}
            {profile.language && <span>Language: {profile.language.toUpperCase()}</span>}
            <span>Joined {new Date(profile.createdAt).toLocaleDateString()}</span>
          </div>
        </div>
        {isMe && (
          <Button variant="outline" onClick={() => setEditing((value) => !value)}>
            {editing ? "Cancel" : "Edit profile"}
          </Button>
        )}
      </div>

      {editing && (
        <form
          className="mb-8 space-y-3 rounded-xl border border-border bg-card p-4"
          onSubmit={(event) => {
            event.preventDefault();
            void save();
          }}
        >
          <label className="block text-sm font-medium" htmlFor="profile-display-name">
            Display name
          </label>
          <input
            id="profile-display-name"
            required
            minLength={2}
            maxLength={80}
            value={form.displayName}
            onChange={(event) => setForm({ ...form, displayName: event.target.value })}
            className="h-10 w-full rounded-md border border-input bg-background px-3"
          />
          <label className="block text-sm font-medium" htmlFor="profile-bio">
            Bio
          </label>
          <textarea
            id="profile-bio"
            maxLength={500}
            value={form.bio}
            onChange={(event) => setForm({ ...form, bio: event.target.value })}
            className="min-h-24 w-full rounded-md border border-input bg-background p-3"
          />
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium" htmlFor="profile-country">
                Country
              </label>
              <input
                id="profile-country"
                maxLength={64}
                value={form.country}
                onChange={(event) => setForm({ ...form, country: event.target.value })}
                className="h-10 w-full rounded-md border border-input bg-background px-3"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium" htmlFor="profile-language">
                Language
              </label>
              <input
                id="profile-language"
                maxLength={12}
                value={form.language}
                onChange={(event) => setForm({ ...form, language: event.target.value })}
                className="h-10 w-full rounded-md border border-input bg-background px-3"
              />
            </div>
          </div>
          <div className="flex justify-end">
            <Button type="submit" disabled={saving}>
              {saving ? "Saving…" : "Save profile"}
            </Button>
          </div>
        </form>
      )}

      <div className="grid gap-4 md:grid-cols-2">
        <section className="rounded-xl border border-border bg-card p-4">
          <h2 className="font-display text-xl font-bold">Badges</h2>
          {profile.badges.length ? (
            <div className="mt-3 flex flex-wrap gap-2">
              {profile.badges.map((badge) => (
                <span key={badge} className="rounded-full bg-accent px-3 py-1 text-xs">
                  {badge}
                </span>
              ))}
            </div>
          ) : (
            <p className="mt-2 text-sm text-muted-foreground">No badges yet.</p>
          )}
        </section>
        <section className="rounded-xl border border-border bg-card p-4">
          <h2 className="font-display text-xl font-bold">Followed artists</h2>
          <p className="mt-2 text-sm text-muted-foreground">
            {profile.followedArtists.length} artist subscriptions managed by this account.
          </p>
        </section>
      </div>
    </div>
  );
}
