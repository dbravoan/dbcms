# AI Providers & Dynamic Model Catalog (`docs/12_AI_PROVIDERS_AND_MODEL_CATALOG.md`)

> **Scope**: `modules/AiAssistant` provider layer, model discovery and routing.
> **Principle**: provider registration is configuration-driven. Live catalogs supply text models;
> visual adapters may declare supported model IDs. Gateway transport rules are
> explicitly encoded in `AiEndpointFamily`.

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
        │                              ├─ OpenCodeProvider (Zen + Go)
        │                              ├─ OllamaProvider / VllmProvider
        │                              └─ MagnificProvider / HiggsfieldProvider
        │
        ├──────────────► AiModelCatalogService ──► Cache (12h; misses 5min)
        └──────────────► AiProviderSettings      ──► module settings + env fallback
```

| Component | Responsibility |
| :--- | :--- |
| `AiProviderInterface` | Strategy contract: `definition()`, `listModels()`, `chat()`. Image/video use optional modality contracts. |
| `AiProviderRegistry` | Resolves adapters by id from `modules/AiAssistant/config/providers.php`. |
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

- `GET /<ADMIN_PATH>/ai-assistant/providers/models?provider=<id>[&refresh=1]` returns the
  live catalog for a provider (cached 12 h, invalidated when a key is saved).
- The backoffice hub ("Modelos & Conexiones de API") loads the catalog on save and
  through the **Actualizar modelos** button. Missing credentials, an unset local
  endpoint or an unreachable listing produces no models; failures are cached
  for five minutes. There is no seed fallback. Adapters without model listing
  expose declared support only after configuration; this is not an account test.
- Listing endpoints per provider: `/models` (OpenAI-compatible), `/v1beta/models`
  (Gemini), `/v1/models` (Anthropic), `/zen/v1/models` + `/zen/go/v1/models` (OpenCode).

---

## 4. OpenCode (Zen + Go)

The adapter tries both catalogs with one `OPENCODE_API_KEY` and merges those
that respond. Access to Go depends on the account; one listing can fail while
the other succeeds. Duplicate IDs retain the first source (Zen before Go). Each model carries its `source` (`zen`/`go`) and its transport family,
resolved by `AiEndpointFamily::fromModelId()` (prefix rules, with chat
completions as the default):

| Prefix | Family | Endpoint |
| :--- | :--- | :--- |
| `deepseek*`, `glm*`, `kimi*`, `minimax*`, `mimo*`, `ling*`, `nemotron*`, `longcat*`, `hy*`, `big-pickle` | `chat_completions` | `/chat/completions` |
| `claude-*`, `qwen*` | `messages` | `/messages` |
| `gpt-*`, `grok-*`, `muse-spark*` | `responses` | `/responses` |
| `gemini-*` | `google` | `/models/{id}:generateContent` (experimental) |
| `jev-*` | `systemone` | excluded (not conversational) |

The gateway base URL is selected per model from the catalog `source`
(`ProviderDefinition::$sourceBaseUrls`).

---

Connection state is `unconfigured`, `configured`, `verified` or `unavailable`.
A successful listing marks listing-capable adapters verified; it does not verify
every generation modality. Visual adapters without listing remain configured.
Stored blank/masked secrets are preserved. Changing an endpoint when a key
exists requires an explicit replacement key; cloud endpoints require HTTPS
and URL-policy validation. Local/custom endpoints have their own policy.

## 5. Quality gates

- `declare(strict_types=1)` everywhere, readonly DTOs, enum-based routing.
- Required checks: PHPStan Level 8 and Pint. This document does not record a current execution.
- Tests: `tests/Unit/AiAssistant/AiEndpointFamilyTest.php` and
  `tests/Feature/AiAssistant/AiModelCatalogAndProviderConnectionTest.php`
  (remote listing, cache, invalidation, endpoint and OpenCode merging).
