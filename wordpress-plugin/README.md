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
readiness warnings while demo runtime data remains or AdSense has not been
configured. Service-worker registration stays disabled in WordPress mode so an
invalid `/sw.js` response or stale application cache cannot break the embedded
application.

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

**AdSense** accepts one validated `ca-pub-` publisher ID and explicit responsive
display-unit slot IDs for the app's approved logical placements. WordPress mode
does not show demo ad placeholders, inject Auto Ads, or load Google's advertising
script before the visitor grants advertising consent. Before enabling ads in
production, configure a Google-certified consent management platform through
AdSense Privacy & messaging for every region where Google requires one; the
in-app privacy controls are the local script gate, not a certified CMP.

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

### Automated K-pop news collector

**News Collector** (K-pop Pulse Hub menu) keeps the site filled without any API
key. Every run reads the enabled publisher feeds and a rotating set of
per-artist Soompi tag feeds, keeps only K-pop stories, and tags them with the
artists in **KpopBlog Artists**.

- **Sources.** English sources are on by default: Soompi, Billboard (K-pop),
  The Korea Herald, Yonhap, The Korea Times, KBS World, NME, Rolling Stone,
  Variety, Teen Vogue, Hypebae, Koreaboo, and KBIZoom. Korean-language desks
  (Yonhap, Newsis, Sports Donga, SBS, Chosun) are available but off by default.
  General music and fashion outlets only contribute stories that name a followed
  artist. Gossip-leaning outlets skip rumor-style headlines.
- **On-site summaries.** Each story is published under the **KpopBlog Newsroom**
  byline as a summary of up to four sentences, built from the publisher's own
  standfirst and lead with filler removed. Readers get the story without leaving
  the site. The rendered article adds an "About {artist}" fact box, more
  coverage of the same artist, the next release, and links to the forum thread
  and artist page. A small "Summary based on reporting by …" credit links to
  every outlet and opens in a new tab.
- **Same story, several outlets.** Later reports of a story that is already
  published are credited on the existing article instead of being posted again.
- **Also added:** official label YouTube videos, and release dates stated in
  comeback announcements.
- **Schedule.** Runs hourly on WP-Cron. If a scheduled run is overdue, the next
  public API request starts one after its response has been sent. **Collect
  now** runs one batch; **Backfill all artists** reads every artist feed once.
- **Original articles with AI.** With an OpenAI key (constant, environment
  variable, or the AI Automation page) and "Rewrite each story" on, the model
  selected on the AI Automation page (default `gpt-6-luna`) writes a new
  headline, standfirst, three-paragraph article, and two discussion questions.
  It uses structured output and only facts from the publisher's text. If a
  rewrite fails, the extractive summary is used and the reason is logged.
- **Daily quota.** "Articles per day" (default 48) is paced across the day, so
  the site publishes steadily instead of in bursts.

### Community automation

All automated community posts use the newsroom account and are flagged
official. They never impersonate members, and no counters are inflated.

- **Topic threads.** Fresh artist headlines open threads in the matching board:
  Comebacks, Concerts, Styling and Fashion, Albums and Merch, or News Reactions.
- **Fan hubs.** Each artist has a hub thread in Artist Fandoms, refreshed with
  their latest headlines and next release.
- **Recurring threads.** A daily news roundup (General), plus weekly threads:
  comeback watch (Mondays), concert check-in (Wednesdays), and a fan-art prompt
  (Fridays).
- **Polls.** A weekly "most-followed news" poll and a most-anticipated-release
  poll.
- **Counters.** Article and thread views count at most once per visitor every
  30 minutes. Zero counters are hidden in lists.
- **Member posting.** Threads, replies, comments, and community posts from
  logged-in members publish immediately. Content with three or more links, or
  words from the WordPress moderation or disallowed lists, waits for review.
  Reports and moderators remain the backstop.

### SEO and answer engines

Crawlers, answer engines, and link previews read the first HTML response. The
plugin server-renders the title, description, canonical, Open Graph/Twitter
tags, schema.org JSON-LD, and readable fallback HTML for these routes:

| Route | JSON-LD |
| --- | --- |
| Home | Organization, WebSite with SearchAction, latest-news ItemList |
| `/latest`, `/trending`, `/artists`, `/forum`, `/forum/{board}` | CollectionPage |
| `/artist/{slug}` | MusicGroup or Person, plus an FAQPage (debut, agency, fandom, members, next comeback) |
| `/thread/{slug}` | DiscussionForumPosting with replies |
| `/watch/{id}` | VideoObject |
| `/polls/{slug}` | Question |
| `/news/{slug}`, `/comebacks` | (existing) |

- `/news-sitemap.xml` lists the last 48 hours of articles in Google News format;
  robots.txt links it.
- New or updated articles, threads, artists, polls, and videos are sent to
  IndexNow (Bing, Naver, Yandex, Seznam) as they are published.
- `llms.txt` includes an artist directory.

The app shell prints its own icon set (`/favicon.ico`, SVG, 32 px, 180 px
Apple touch, maskable 192/512 manifest icons) and drops theme and WordPress
default icons. Pages without an image use `icons/og-default.jpg` as their
share image.

AdSense supports **Auto ads**, which needs only the publisher ID, alongside
manual ad units. Consent modes:

- **In-site banner:** ads load after "Accept".
- **Opt-out:** ads load for everyone; visitors who choose "Reject optional" get
  non-personalized ads.
- **Google certified CMP:** use this after publishing the consent message in
  AdSense Privacy & messaging.

### AI and search discoverability

Published WordPress articles are present in the initial `/news/{slug}` HTML with canonical metadata and `NewsArticle` JSON-LD before the React application starts. The `/comebacks` response includes a semantic schedule fallback and `Event` structured data. React replaces this fallback in JavaScript-capable browsers; crawlers and no-script clients receive the same published WordPress content.

The plugin serves `/robots.txt`, `/sitemap.xml`, `/rss.xml`, and `/llms.txt` from published WordPress records. Search, user-directed AI retrieval, and model-training crawlers are allowed. Drafts, password-protected content, administrator routes, authentication routes, and private notification APIs are excluded.

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
