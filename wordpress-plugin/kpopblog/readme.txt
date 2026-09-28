=== KpopBlog ===
Contributors: kpopblog
Tags: kpop, blog, community, music, react
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.2
License: GPLv2 or later

Premium K-pop community blog as a WordPress plugin. Manage every content
surface (articles, artists, members, comebacks, charts, videos, forum threads,
polls, and member submissions) from the WordPress admin and render the React front-end via the
[kpopblog] shortcode or the "KpopBlog App" Gutenberg block.

Administrators also receive a K-pop Pulse Hub operations dashboard with
content and subscriber counts, direct management links, and health checks for
assets, homepage configuration, permalinks, registration, and cron. Plugin
deactivation preserves all WordPress content and plugin data.

Community submissions are stored as pending WordPress content for review.
Administrators can publish them from Community Posts and review user reports,
pending posts, and pending replies from Community Moderation. Report decisions
are protected by WordPress nonces and written to the plugin audit log.

Newsletter confirmation and unsubscribe links use separate random tokens that
are stored only as salted hashes. Subscriber administration includes status
filters, confirmation resend, suppression, CSV export, and WordPress privacy
export/erase integration. Durable per-user notification inboxes support topic
and artist preferences, persistent read state, cron-batched admin broadcasts,
and optional HMAC-signed webhooks.

The AdSense settings page validates the publisher ID and responsive display-unit
slot IDs against approved app placements. WordPress mode does not render demo ad
placeholders or load the advertising script before advertising consent. Configure
a Google-certified consent management platform in AdSense Privacy & messaging
before serving ads in every region where Google requires one.

The release verification keeps the initial WordPress application entry below
200 KiB gzip and the shared stylesheet below 25 KiB gzip. Route-level screens
remain lazy-loaded from separate chunks.

== AI and search discoverability ==

Published articles are present in the initial /news/{slug} HTML with canonical
metadata and NewsArticle JSON-LD. /comebacks includes a semantic schedule
fallback and Event structured data. React replaces the fallback in browsers;
crawlers and no-script clients receive the same published WordPress content.

The plugin serves /robots.txt, /sitemap.xml, /rss.xml, and /llms.txt from
published WordPress records. Search, user-directed AI retrieval, and
model-training crawlers are allowed. Drafts, password-protected content,
administrator and authentication routes, and private notification APIs are
excluded.

== Installation ==

1. Build the React app and copy the bundle into /assets:
     bash wordpress-plugin/build-plugin.sh
2. Zip the `kpopblog` folder and upload via Plugins → Add New → Upload.
3. Activate. A new admin menu appears with: KpopBlog Artists, Members,
   Comebacks, Charts, Videos, Community Posts, Submissions, Forum threads, Polls, and Community
   Moderation. Articles use the built-in WordPress Posts type so editors keep
   the standard authoring UX.
4. Create a page, paste `[kpopblog]`, publish. The full SPA mounts inside
   that page using the WP REST API as the CMS.

== REST API ==

Unified namespace: /wp-json/kpopblog/v1
  GET /articles, /articles/{slug}
  GET /artists,  /artists/{slug}
  GET /members,  /members/{slug}
  GET /comebacks, /comebacks/{slug}
  GET /charts,    /charts/{slug}
  GET /videos,    /videos/{slug}
  GET /threads,   /threads/{slug}
  GET /polls,     /polls/{slug}
  GET, POST /community
  GET /forum/categories
  POST /threads
  GET, POST /threads/{slug}/replies
  GET, POST /articles/{slug}/comments
  GET, POST /videos/{slug}/comments
  POST /submissions
  POST /reports
  GET /moderation/reports
  POST /moderation/reports/{id}
  GET, POST /subscriptions
  GET /notifications
  POST /notifications/{id}/read
  POST /notifications/read-all
  POST /notifications/broadcast
  GET /newsletter/settings
  POST /newsletter/subscribe
  POST /newsletter/confirm
  POST /newsletter/unsubscribe
  GET /bundle    (homepage hydration)

Responses already match the React app's TypeScript types — no transform layer.
