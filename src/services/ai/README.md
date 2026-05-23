# AI Content Pipeline

Two paths to populate the site with AI-generated content. **Both write through the same Zod schemas in `src/schemas/ai.ts`** — the UI never trusts AI output without validation.

## 1. Build-time generation (works today, no backend needed)

```bash
# 1. Make LOVABLE_API_KEY available (locally or in CI)
export LOVABLE_API_KEY=lv_...

# 2. Generate seed content in English
bun run scripts/ai/generate.ts --kind=article    --count=10 --locale=en
bun run scripts/ai/generate.ts --kind=artist     --count=12 --locale=en
bun run scripts/ai/generate.ts --kind=member     --count=40 --locale=en
bun run scripts/ai/generate.ts --kind=comeback   --count=20 --locale=en
bun run scripts/ai/generate.ts --kind=chart      --count=2  --locale=en
bun run scripts/ai/generate.ts --kind=forum_thread --count=20 --locale=en

# 3. Translate the en bundle into all 17 locales
bun run scripts/ai/translate.ts                    # all locales
bun run scripts/ai/translate.ts --locales=ko,ja    # subset
```

Output lands in `src/data/ai-generated/<locale>.json`. The static provider picks these up automatically — refresh the page.

## 2. Runtime generation (requires backend)

The runtime provider is wired and ready, but disabled until a backend hosts the gateway call. To enable:

1. Create a server function (TanStack Start) or Supabase Edge Function at e.g. `/api/ai/generate` that:
   - reads `LOVABLE_API_KEY` from `process.env`
   - forwards to `https://ai.gateway.lovable.dev/v1/chat/completions`
   - returns the parsed JSON content
2. Set `VITE_AI_ENDPOINT=/api/ai/generate` in your environment.

That's it — `aiProvider.generate(...)` will start working and responses are Zod-validated on the client.

## Schema contract

Every AI response is parsed by `AiBundleSchema` (bundle files) or one of the per-kind schemas (runtime responses). Malformed AI output is **rejected, not rendered**. When you add a field to a content type, add it in **both** `src/types/index.ts` and `src/schemas/ai.ts`.

## Where to plug the data

- Articles, charts, comebacks, forum threads → `src/services/cms/demoProvider.ts` reads `aiProvider.list(...)` and merges with the static seed.
- Artist/member profile pages → same flow via `src/services/artists/index.ts`.
- Runtime-only flows (e.g. "AI write summary" button) → call `aiProvider.generate(...)` from a server function, never directly from the client.
