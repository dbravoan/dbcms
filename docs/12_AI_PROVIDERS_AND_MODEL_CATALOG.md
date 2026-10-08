# AI Providers & Dynamic Model Catalog (`docs/12_AI_PROVIDERS_AND_MODEL_CATALOG.md`)

> **Scope**: `modules/AiAssistant` provider layer, model discovery and routing.
> **Principle**: providers and models are **data-driven**; nothing is hardcoded per model.

---

## 1. Architecture (Strategy + Registry)

```
AiAssistantDomainService
        │
        ▼
AiConfigBridge ──────────────► AiProviderRegistry ──► AiProviderInterface adapters
        │                              │
        │                              ├─ GeminiProvider
        │                              ├─ OpenAiProvider
        │                              ├─ DeepSeekProvider
        │                              ├─ AnthropicProvider
        │                              ├─ CustomOpenAiProvider (Ollama/LMStudio)
        │                              └─ OpenCodeProvider (Zen + Go)
        │
        ├──────────────► AiModelCatalogService ──► Cache (12h) + seed fallback
        └──────────────► AiProviderSettings      ──► module settings + env fallback
```

| Component | Responsibility |
| :--- | :--- |
| `AiProviderInterface` | Strategy contract: `definition()`, `seedModels()`, `listModels()`, `chat()`. |
| `AiProviderRegistry` | Resolves adapters by id from `config/ai_providers.php`. |
| `AiModelCatalogService` | Fetches/caches the live catalog per provider. |
| `AiProviderSettings` | Single source of truth for credentials/base URLs. |
| `AiConfigBridge` | Thin facade used by controllers, views and the MCP layer. |

DTOs are immutable (`ProviderDefinition`, `ModelOption`, `ChatRequest`,
`CompletionResult`, `ConnectionResult`); model transport is typed with the
`AiEndpointFamily` enum.

---

## 2. Adding a provider

1. Implement `AiProviderInterface` under `modules/AiAssistant/Providers/`.
2. Append the class to `modules/AiAssistant/config/providers.php`.
3. Done: catalog, backoffice hub, validation rules and routing pick it up.

### Optional credentials

Keys may be stored from the backoffice or provided through `.env` via
`config/services.php`:

```
GEMINI_API_KEY=
OPENAI_API_KEY=
DEEPSEEK_API_KEY=
ANTHROPIC_API_KEY=
OPENCODE_API_KEY=   # Single key for both OpenCode Zen and Go
```

---

## 3. Dynamic model catalog

- `GET /admin/ai-assistant/providers/models?provider=<id>[&refresh=1]` returns the
  live catalog for a provider (cached 12 h, invalidated when a key is saved).
- The backoffice hub ("Modelos & Conexiones de API") loads the catalog on save and
  through the **Actualizar modelos** button; a curated seed list is used as
  fallback when there is no key or the network fails.
- Listing endpoints per provider: `/models` (OpenAI-compatible), `/v1beta/models`
  (Gemini), `/v1/models` (Anthropic), `/zen/v1/models` + `/zen/go/v1/models` (OpenCode).

---

## 4. OpenCode (Zen + Go)

A single `OPENCODE_API_KEY` unlocks both catalogs, which are merged into one
list. Each model carries its `source` (`zen`/`go`) and its transport family,
resolved from the model id:

| Prefix | Family | Endpoint |
| :--- | :--- | :--- |
| `deepseek*`, `glm*`, `kimi*`, `minimax*`, `mimo*`, `ling*`, `nemotron*`, `longcat*`, `hy*`, `big-pickle` | `chat_completions` | `/chat/completions` |
| `claude*`, `qwen*` | `messages` | `/messages` |
| `gpt*`, `grok*`, `muse-spark*` | `responses` | `/responses` |
| `gemini*` | `google` | `/models/{id}:generateContent` (experimental) |
| `jev*` | `systemone` | excluded (not conversational) |

The gateway base URL is selected per model from the catalog `source`
(`ProviderDefinition::$sourceBaseUrls`).

---

## 5. Quality gates

- `declare(strict_types=1)` everywhere, readonly DTOs, enum-based routing.
- PHPStan Level 8 and Pint clean.
- Tests: `tests/Unit/AiAssistant/AiEndpointFamilyTest.php` and
  `tests/Feature/AiAssistant/AiModelCatalogAndProviderConnectionTest.php`
  (remote listing, cache, invalidation, endpoint and OpenCode merging).
