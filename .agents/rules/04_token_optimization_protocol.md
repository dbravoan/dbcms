# Rule 04 · Token Optimization Protocol

**Canonical source:** [`docs/03_TOKEN_OPTIMIZATION_AND_KV_CACHE.md`](../../docs/03_TOKEN_OPTIMIZATION_AND_KV_CACHE.md) and `AGENTS.md` (Section 5).

Apply, do not restate:

- Keep the static prompt header (`AGENTS.md`, core interfaces) unchanged across turns.
- Prefer surgical diffs (≤ 40 lines) over whole-file rewrites.
- Pass structured JSON summaries between agents, not conversational prose.
