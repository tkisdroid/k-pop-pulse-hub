# AI Content Pipeline (OpenAI & Gemini)

This site is wired to use **OpenAI** and **Google Gemini** APIs directly. No Lovable AI Gateway. All AI output is validated with the Zod schemas in `src/schemas/ai.ts` before being persisted or rendered.

## 1. Build-time generation (works today, no backend needed)

Export the provider's API key locally, then run:

```bash
# --- OpenAI ---
export OPENAI_API_KEY=sk-...
bun run ai:generate -- --provider=openai --model=gpt-4o-mini --kind=article    --count=10 --locale=en
bun run ai:generate -- --provider=openai --model=gpt-4o-mini --kind=artist     --count=12 --locale=en
bun run ai:generate -- --provider=openai --model=gpt-4o-mini --kind=member     --count=40 --locale=en
bun run ai:generate -- --provider=openai --model=gpt-4o-mini --kind=comeback   --count=20 --locale=en
bun run ai:generate -- --provider=openai --model=gpt-4o-mini --kind=chart      --count=2  --locale=en
bun run ai:generate -- --provider=openai --model=gpt-4o-mini --kind=forum_thread --count=20 --locale=en

# --- Gemini (Google AI Studio key — https://aistudio.google.com/apikey) ---
export GEMINI_API_KEY=...
bun run ai:generate -- --provider=gemini --model=gemini-2.5-flash --kind=article --count=10 --locale=en

# --- Translate en → 16 other locales ---
bun run ai:translate -- --provider=gemini                 # all 16 locales
bun run ai:translate -- --provider=openai --locales=ko,ja # subset
```

CLI flags:

| flag         | values                                                        | default              |
|--------------|---------------------------------------------------------------|----------------------|
| `--provider` | `openai` \| `gemini`                                          | `gemini`             |
| `--model`    | any OpenAI chat model / Gemini model name                     | provider-specific    |
| `--kind`     | `article` \| `artist` \| `member` \| `comeback` \| `chart` \| `forum_thread` | `article` |
| `--count`    | integer                                                       | `3`                  |
| `--locale`   | one of the 17 supported locales                               | `en`                 |
| `--locales`  | (translate only) comma-separated locale list                  | all 16 non-en        |

Output lands in `src/data/ai-generated/<locale>.json`. The static provider picks these up automatically on next page load.

## 2. Runtime generation (requires a backend later)

The runtime provider (`runtimeAiProvider`) is ready but disabled until a backend hosts the provider call. To enable:

1. Create a server function (TanStack Start) or Supabase Edge Function at e.g. `/api/ai/generate` that:
   - reads `OPENAI_API_KEY` and/or `GEMINI_API_KEY` from `process.env`
   - dispatches to OpenAI (`https://api.openai.com/v1/chat/completions`) or Gemini (`https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`) based on the request's `provider` field
   - returns the parsed JSON content
2. Set in the project env:
   - `VITE_AI_ENDPOINT=/api/ai/generate`
   - `VITE_AI_PROVIDER=openai` or `gemini` (default)

After that, `aiProvider.generate(...)` works from the UI and every response is Zod-validated on the client.

**Security:** never put `OPENAI_API_KEY` or `GEMINI_API_KEY` in `VITE_*` env vars — those are bundled into the browser. Provider keys must stay server-side.

## Schema contract

Every AI response is parsed by the corresponding Zod schema. Malformed output is **rejected, not rendered**. When you add a field to a content type, add it in both `src/types/index.ts` and `src/schemas/ai.ts`.

## Where the data is consumed

- Articles → `src/services/cms/demoProvider.ts` reads `aiProvider.list("article")` and merges with the static seed (AI content first).
- Artists / members / comebacks / charts / forum threads → adapter helpers in `src/services/ai/adapters.ts` map the AI schema into the runtime types. Wire them into the corresponding service the same way `demoProvider` does.
- Runtime-only flows (e.g. "AI write summary" button) → call `aiProvider.generate(...)`; behind the scenes it hits your server function, which calls OpenAI or Gemini.
