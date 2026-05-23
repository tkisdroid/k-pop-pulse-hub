#!/usr/bin/env bash
# Build the React app and package it as a WordPress plugin (kpopblog.zip).
# Run from the project root: bash wordpress-plugin/build-plugin.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_DIR="$ROOT/wordpress-plugin/kpopblog"
ASSETS_DIR="$PLUGIN_DIR/assets"

echo "→ Building React app (vite build)…"
cd "$ROOT"
bun run build

echo "→ Copying built assets into the plugin…"
rm -rf "$ASSETS_DIR"
mkdir -p "$ASSETS_DIR"

# vite/tanstack-start writes the SPA bundle under dist/client/_build/assets.
# Adjust SOURCE if your build output differs.
SOURCE="$ROOT/dist/client"
if [ ! -d "$SOURCE" ]; then
  echo "✗ Build output not found at $SOURCE — check vite.config.ts outDir." >&2
  exit 1
fi

cp -R "$SOURCE/." "$ASSETS_DIR/"

# Generate a tiny manifest so PHP knows which hashed JS/CSS to enqueue.
node - <<'JS' > "$ASSETS_DIR/manifest.json"
const fs = require('fs'); const path = require('path');
const root = process.env.ASSETS_DIR || './wordpress-plugin/kpopblog/assets';
function walk(d){return fs.readdirSync(d).flatMap(f=>{const p=path.join(d,f);return fs.statSync(p).isDirectory()?walk(p):[p];});}
const all = walk(root).map(p=>p.replace(root+'/',''));
const js  = all.find(f=>/index.*\.js$/.test(f))  || all.find(f=>f.endsWith('.js'));
const css = all.find(f=>/index.*\.css$/.test(f)) || all.find(f=>f.endsWith('.css'));
process.stdout.write(JSON.stringify({js:'assets/'+js, css:'assets/'+css}, null, 2));
JS

echo "→ Zipping plugin…"
cd "$ROOT/wordpress-plugin"
rm -f kpopblog.zip
zip -r kpopblog.zip kpopblog -x '*.DS_Store' >/dev/null

echo "✓ Done: $ROOT/wordpress-plugin/kpopblog.zip"
echo "  Upload via WP admin → Plugins → Add New → Upload."
