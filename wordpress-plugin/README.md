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
   ├── KpopBlog Threads     → kb_thread
   └── KpopBlog Polls       → kb_poll       (+ options JSON)
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
