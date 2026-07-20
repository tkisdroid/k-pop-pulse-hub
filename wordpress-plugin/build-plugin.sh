#!/usr/bin/env bash
# Build the React app and package it as a WordPress plugin (kpopblog.zip).
# Run from the project root: bash wordpress-plugin/build-plugin.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_DIR="$ROOT/wordpress-plugin/kpopblog"
ASSETS_DIR="$PLUGIN_DIR/assets"

echo "→ Building React app for WordPress (vite.config.wordpress.ts)…"
cd "$ROOT"
if grep -qi microsoft /proc/version 2>/dev/null; then
  powershell.exe -NoProfile -Command "npm run build:wordpress"
else
  npm run build:wordpress
fi

echo "→ Copying built assets into the plugin…"
rm -rf "$ASSETS_DIR"
mkdir -p "$ASSETS_DIR"

# vite.config.wordpress.ts — a plain client-side (no SSR/hydration) build
# made specifically for embedding in a WordPress page. Do NOT point this at
# dist/client: that's the TanStack Start/Cloudflare Workers SSR build, whose
# client bundle expects to hydrate server-rendered markup and crashes
# ("Invariant failed") when mounted into WordPress's own document instead.
SOURCE="$ROOT/dist/wordpress"
if [ ! -d "$SOURCE" ]; then
  echo "✗ Build output not found at $SOURCE — check vite.config.wordpress.ts outDir." >&2
  exit 1
fi

cp -R "$SOURCE/." "$ASSETS_DIR/"

# Generate a tiny manifest so PHP knows which hashed JS/CSS to enqueue.
ASSETS_DIR="$ASSETS_DIR" node - <<'JS' > "$ASSETS_DIR/manifest.json"
const fs = require('fs'); const path = require('path');
const root = process.env.ASSETS_DIR;
function walk(d){return fs.readdirSync(d).flatMap(f=>{const p=path.join(d,f);return fs.statSync(p).isDirectory()?walk(p):[p];});}
// Normalize to forward slashes so the manifest is correct on Windows too.
const all = walk(root).map(p=>path.relative(root,p).split(path.sep).join('/'));
const js  = all.find(f=>/index.*\.js$/.test(f))  || all.find(f=>f.endsWith('.js'));
const css = all.find(f=>/index.*\.css$/.test(f)) || all.find(f=>f.endsWith('.css'));
process.stdout.write(JSON.stringify({js:'assets/'+js, css:'assets/'+css}, null, 2));
JS

echo "→ Zipping plugin…"
cd "$ROOT/wordpress-plugin"
rm -f kpopblog.zip
if command -v zip >/dev/null 2>&1; then
  zip -r kpopblog.zip kpopblog -x '*.DS_Store' >/dev/null
else
  # No zip binary (e.g. plain Windows/Git Bash) — fall back to PowerShell.
  powershell.exe -NoProfile -Command "Compress-Archive -Path 'kpopblog' -DestinationPath 'kpopblog.zip' -Force"
fi

echo "✓ Done: $ROOT/wordpress-plugin/kpopblog.zip"
echo "  Upload via WP admin → Plugins → Add New → Upload."
