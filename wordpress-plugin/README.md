# KpopBlog WordPress Plugin

Packages the React SPA under `src/` as a self-contained WordPress plugin so
**all content** (articles, artists, members, comebacks, charts, forum
threads, polls) is managed from the WordPress admin.

## Architecture

```
WordPress admin (editor UX)
   │
   ├── Posts                → Articles
   ├── KpopBlog Artists     → Custom Post Type kb_artist
   ├── KpopBlog Members     → kb_member
   ├── KpopBlog Comebacks   → kb_comeback   (+ release date meta)
   ├── KpopBlog Charts      → kb_chart      (+ entries JSON)
   ├── Community Posts      → kb_community
   ├── KpopBlog Threads     → kb_thread
   ├── KpopBlog Polls       → kb_poll       (+ options JSON)
   └── Community Moderation → reports and pending content
                │
                ▼
   /wp-json/kpopblog/v1/{articles|artists|members|comebacks|charts|threads|polls}
                │
                ▼
   React SPA mounted by [kpopblog] shortcode or "KpopBlog App" Gutenberg block
```

## Build & install

```bash
bash wordpress-plugin/build-plugin.sh   # → wordpress-plugin/kpopblog.zip
```

Upload via **WP admin → Plugins → Add New → Upload**. Activate, then:

1. **Pages → Add New**, title it anything (e.g. "Home").
2. In **Page Attributes → Template**, choose **KpopBlog App (full page)**.
3. Publish, then set it as the homepage under **Settings → Reading → Your
   homepage displays → A static page**.

The React app auto-detects WordPress via `window.kpopblogConfig` (injected
by `wp_localize_script`) and routes all CMS reads through
`/wp-json/kpopblog/v1`. Outside WordPress (e.g. preview), it falls back to
the demo provider.

## Local WordPress operations

The repository includes a repeatable WordPress runtime backed by Docker
Compose. The credentials in `.env.wordpress.example` are only for local
development; copy them to the ignored local file before starting the stack:

```powershell
Copy-Item .env.wordpress.example .env.wordpress
pwsh -File scripts/wordpress/bootstrap.ps1
pwsh -File scripts/wordpress/verify-foundation.ps1
```

The public site is available at `http://localhost:8088` and WordPress
administration at `http://localhost:8088/wp-admin/`. The bootstrap script is
idempotent: it installs WordPress only when needed, activates KpopBlog, creates
the app homepage, and leaves existing content and users intact.

Administrators can open **K-pop Pulse Hub** from the WordPress sidebar. The
dashboard summarizes content, users, pending comments, and confirmed newsletter
subscribers. Its health section verifies packaged assets, the static homepage,
permalinks, user registration, and WordPress cron. It also displays explicit
readiness warnings while demo runtime data or the service-worker delivery issue
remain.

The same menu contains **Community Posts** and **Community Moderation**.
Subscriber submissions remain pending until an administrator publishes them.
The moderation screen lists open user reports together with pending community
posts and replies; resolving or dismissing a report is nonce-protected and
recorded in the plugin audit log.

**Newsletter subscribers** shows confirmed, pending, and unsubscribed records
with topic, frequency, consent, confirmation, and suppression metadata. Admins
can filter by status, resend a pending confirmation, unsubscribe a record, or
export a CSV. Confirmation and unsubscribe tokens are stored only as salted
hashes; public unsubscribe requests require the secure email link. The plugin
also participates in WordPress personal-data export and erasure workflows.

**Notifications** queues administrator broadcasts and processes recipients in
cron batches. Topic and artist preferences are stored on the WordPress user,
while each inbox item and its read timestamp are stored in plugin tables. The
React header reads this server inbox in WordPress mode and never seeds demo
notifications there. Optional outbound webhooks remain HMAC signed.

`verify-foundation.ps1` builds both front-end distributions and exercises the
installed plugin through real WordPress REST requests. Its identity and
community checks cover registration policy, rate limiting, public/private
profile boundaries, pending submissions, locked threads, duplicate reports,
moderator permissions, report resolution, double opt-in token handling,
subscription privacy, inbox isolation, read state, and broadcast delivery.

Plugin deactivation only clears rewrite rules and scheduled plugin hooks; it
does not remove posts, users, settings, subscriptions, or the audit table. No
repository script removes the named Docker volumes, so local WordPress and
MariaDB data remain available across ordinary bootstrap and verification runs.

### Design/menu parity with the Lovable build

The "full page" template (`includes/template.php` +
`templates/app-shell.php`) renders the SPA with **no active-theme
chrome** — no theme header/footer/sidebar/nav menu, and every other
enqueued stylesheet is dropped from that page — so the deployed site looks
and navigates exactly like the standalone Lovable build, regardless of
which WordPress theme is active. It also catches any URL WordPress can't
otherwise resolve (e.g. `/artists`, `/news/some-slug`) and serves the same
shell instead of the theme's 404 page, so TanStack Router can resolve those
client-side routes once the SPA boots.

If you'd rather embed the app inside an existing theme's layout instead
(losing exact Lovable parity but keeping the theme's header/footer), use
the `[kpopblog]` shortcode or the "KpopBlog App" Gutenberg block on a
normal page/template instead of the full-page template above.
