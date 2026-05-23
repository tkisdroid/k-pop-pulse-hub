# Supabase Schema — Future Integration

This documents the target Supabase schema for KpopBlog. **Not applied during this build.** Migrations will run after credentials are provided.

## Tables

1. **profiles** — id (uuid → auth.users), username, display_name, avatar_url, bio, country, language, created_at
2. **artists** — id, slug, name, korean_name, type, agency, debut_date, fandom_name, generation, status, nationality, bio, image_url, follower_count
3. **members** — id, slug, artist_id (fk), stage_name, full_name, korean_name, birthday, nationality, position[], mbti, image_url, facts[]
4. **articles** — id, wp_id, slug, title, subtitle, excerpt, content, featured_image, author_id, category, tags[], language, source, status, view_count, comment_count, reaction_count, published_at, modified_at
5. **article_artist_links** — article_id, artist_id (composite pk)
6. **forum_categories** — id, slug, name, description, icon
7. **forum_threads** — id, slug, category_id, title, body, author_id, flair, pinned, locked, official, rumor, views, last_activity_at, created_at
8. **forum_posts** — id, thread_id, parent_id, author_id, body, created_at, edited_at
9. **reactions** — id, user_id, target_type, target_id, type
10. **comments** — id, article_id, author_id, parent_id, body, created_at
11. **follows** — user_id, target_type (artist|user|thread), target_id
12. **bookmarks** — user_id, target_type, target_id
13. **reports** — id, reporter_id, target_type, target_id, reason, status, created_at
14. **comeback_events** — id, artist_id, title, type, release_at, description, image_url, thread_id
15. **polls** — id, slug, title, description, ends_at, artist_id
16. **poll_options** — id, poll_id, label
17. **poll_votes** — poll_id, user_id, option_id (unique poll_id + user_id)
18. **translations** — id, target_type, target_id, language, translated_text, machine, created_at
19. **notifications** — id, user_id, type, body, url, read, created_at
20. **badges** — id, name, description, icon, color
21. **user_badges** — user_id, badge_id, awarded_at
22. **moderation_logs** — id, moderator_id, action, target_type, target_id, notes, created_at

## User roles

Stored in a separate `user_roles` table (NEVER on `profiles`) with enum `app_role` (`member`, `contributor`, `trusted_member`, `moderator`, `editor`, `admin`). Use a `has_role(uid, role)` SECURITY DEFINER function in RLS policies to avoid recursion.

## RLS notes

- Users can read public content (articles, threads, posts, polls).
- Users can insert/update/delete only their own profiles, posts, comments, reactions, bookmarks, follows.
- Moderators/admins (via `has_role`) can update/hide any post, thread, comment, and resolve reports.
- Admins manage articles, badges, site settings.
- Poll votes: one row per (poll_id, user_id).
- Reports visible only to reporter and moderators/admins.
- Sanitize all user-generated HTML/Markdown server-side before storage.
- Rate-limit posting/commenting/voting at the application layer.

## Indexes

- articles(slug), articles(published_at desc), articles(category)
- forum_threads(slug), forum_threads(category_id, last_activity_at desc)
- forum_posts(thread_id, created_at)
- comeback_events(release_at)
- follows(user_id), follows(target_id)
