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

Upload via **WP admin → Plugins → Add New → Upload**. Activate. Create a
page, paste `[kpopblog]`, publish — the full app mounts inside that page.

The React app auto-detects WordPress via `window.kpopblogConfig` (injected
by `wp_localize_script`) and routes all CMS reads through
`/wp-json/kpopblog/v1`. Outside WordPress (e.g. preview), it falls back to
the demo provider.
