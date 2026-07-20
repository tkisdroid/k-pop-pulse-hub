# K-pop Pulse Hub Identity and Community Design

## Goal

Replace every WordPress-mode demo or no-op identity/community path with
WordPress-owned users, profiles, community posts, forum threads/replies,
article comments, reports, and moderation records. Standalone preview mode may
retain demo providers; a mounted WordPress app may not silently use them.

## Existing Constraints

- WordPress cookie sessions and REST nonces already power login, logout, and
  registration.
- `kb_thread` already exists and is managed in WordPress administration.
- Article comments already use WordPress comments, but forum replies and public
  comment reads are missing.
- Community, forum, profile, submit, and moderation routes currently contain
  demo arrays or no-op forms.
- WordPress 7.0/PHP 8.2 is the verified runtime; plugin source remains PHP
  7.4-compatible.
- Existing content, users, and the user's uncommitted app-shell change must be
  preserved.

## Data Ownership

- WordPress users remain the only account and session records.
- Public profiles expose mapped community fields but never email addresses.
- `kb_community` is a non-hierarchical public custom post type with author,
  editor, custom-fields, and comments support. New member posts default to
  `pending`; users with `publish_posts` may publish immediately.
- `kb_thread` remains the forum thread type. `kb_forum_category` is a
  hierarchical taxonomy managed in wp-admin. Replies are WordPress comments on
  the thread, which keeps moderation, ownership, timestamps, and spam tooling
  in core.
- Reports use `{prefix}kb_reports`, created idempotently with `dbDelta`. Rows
  contain reporter, target type/id, reason, status, resolver, resolution note,
  and UTC timestamps. A composite lookup prevents duplicate pending reports by
  the same reporter for the same target.
- Moderation actions also write the existing immutable audit table.

## Public REST Contract

- `GET /profiles/{username}` returns a public profile without email.
- `POST /profile/me` updates display name, bio, country, and language for the
  authenticated user and returns a fresh mapped user.
- `GET /community` is paginated and returns only published posts publicly;
  authenticated owners and moderators can query their pending items.
- `POST /community` validates and sanitizes 1-2000 characters, applies a user
  rate limit, and returns the server-selected status.
- `GET /forum/categories`, `GET /threads`, and `GET /threads/{slug}` return
  WordPress categories, threads, authors, and pagination totals.
- `POST /threads` creates a pending or published thread using an existing term.
- `GET /threads/{slug}/replies` and `POST /threads/{slug}/replies` use WordPress
  comments and reject replies to locked threads.
- `GET /articles/{slug}/comments` exposes approved article comments; the
  existing POST route returns the mapped created comment and pending state.
- `POST /reports` accepts only known target types that resolve to real objects,
  requires login, rate limits the reporter, and returns conflict for a duplicate
  pending report.
- `GET /moderation/reports` and `POST /moderation/reports/{id}` require
  `kb_moderate_community`; resolution changes are audited.

All collection responses set `X-WP-Total` and `X-WP-TotalPages`. REST error
responses use stable codes and accurate 400/401/403/404/409/429/500 statuses.

## Authentication Safety

- Registration respects `users_can_register` and never promotes a WordPress
  role supplied by the browser.
- Account creation uses per-IP and per-identity transient throttles derived from
  `REMOTE_ADDR` and a salted hash; raw addresses and passwords are never stored
  or logged.
- Login failures use a generic response. Password-reset always uses a generic
  response to prevent account discovery.
- React derives access display from server data, but PHP capability checks are
  authoritative for every privileged action.

## Administrator Experience

- `Community Posts` and `Forum Categories` are available under the single
  K-pop Pulse Hub menu while retaining native WordPress list/edit screens.
- A Moderation submenu lists pending reports and links to pending WordPress
  comments/community posts. Moderators can resolve or dismiss reports with a
  required nonce and optional sanitized note.
- Dashboard counts include community posts and open reports.

## Front-end Behavior

- A dedicated WordPress community provider owns community, forum, profile, and
  report requests. It never catches a WordPress API failure and substitutes
  demo content.
- Pages show loading, empty, validation, pending-review, error, and retry states.
- Forms disable repeated submission and render server messages. Login accepts
  username or email. Registration states the real password requirements and
  does not claim success if optional newsletter enrollment fails silently; the
  account result remains authoritative.
- Public bodies render as text. No arbitrary user HTML is injected.

## Verification

- PHP smoke tests prove registration gating and throttling, public profile email
  privacy, profile ownership, CPT/taxonomy presence, post/reply creation,
  locked-thread rejection, report deduplication, moderation capability checks,
  audit insertion, and pagination headers.
- Front-end production and WordPress builds must pass.
- A browser test in the Docker runtime covers signup, logout/login, profile
  update, community submission, thread/reply submission, report creation, and
  administrator moderation visibility.
