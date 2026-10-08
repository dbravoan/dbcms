# Universal Agent Orchestration & Superagent Protocol (AGENTS.md)

> **Framework**: Laravel LTS Multi-Agent Enterprise Architecture  
> **Orchestration Model**: Hierarchical Superagent Directed Acyclic Graph (DAG) with Reflexion Loop  
> **Token Economy Tier**: Level 3 (KV-Cache Prefix Invariant, Strict Context Partitioning)  
> **Target Runtimes**: Antigravity AI Engine & OpenCode

---

## 1. Executive Summary & Superagent Architectural Vision

In this enterprise Laravel ecosystem, no single agent operates as a monolithic "do-everything" entity. Instead, system execution is governed by a **Superagent (Meta-Orchestrator Cognitive Kernel)**. The Superagent serves as the centralized deterministic state machine that receives human intent, synthesizes a directed task graph, delegates isolated execution units to specialized subagents, and enforces zero-regression gating through deterministic verification (Pest tests, PHPStan Level 8, Docker Sail containment).

```
========================================================================================================
                                     SUPERAGENT (META-ORCHESTRATOR)
                                  [Cognitive Kernel & State Machine]
========================================================================================================
                                                  |
                    +-----------------------------+-----------------------------+
                    |                             |                             |
                    v                             v                             v
           [Phase 1: Formal Spec]        [Phase 2: Test Harness]       [Phase 3: Domain Synthesis]
             `SpecArchitect`                 `TddCraftsman`                 `DomainEngineer`
            - Hoare Triples (P{Q}R)         - Pest Unit/Arch Tests        - Fat Domain Services
            - DTO Contracts                 - Boundary Assertions         - DB::transaction Safety
            - OpenAPI & JSON Schema         - 100% Red State Check        - Strict Types (Level 8)
                    |                             |                             |
                    +-----------------------------+-----------------------------+
                                                  |
                    +-----------------------------+-----------------------------+
                    |                                                           |
                    v                                                           v
         [Phase 4: Security & Auth]                                  [Phase 5: GEO & SEO Indexing]
            `SecurityAuditor`                                            `GeoSeoSpecialist`
          - Passport OAuth2 Scopes                                     - /llms.txt & /llms-full.txt
          - Cryptographic Isolation                                    - Schema.org JSON-LD Graphs
          - Secrets & PII Masking                                      - Semantic Context Chunking
                    |                                                           |
                    +-----------------------------+-----------------------------+
                                                  |
                                                  v
                                     [Phase 6: LTS & QA Watchdog]
                                           `LtsWatchdog`
                                    - Zero-Host Sail Verification
                                    - Automated Upgrades & CVE Audit
========================================================================================================
```

---

## 2. Formal Cognitive State Machine of the Superagent

The operational lifecycle of the Superagent is modeled as a Deterministic Finite Automaton (DFA) with state set $S$, input alphabet $\Sigma$, transition function $\delta: S \times \Sigma \rightarrow S$, and terminal validation states:

$$\mathcal{M}_{\text{super}} = \langle S, \Sigma, \delta, s_0, F \rangle$$

```
   [S0: Intent Ingestion]
             |
             v
   [S1: Repository State & LTS Check]
             |
             v
   [S2: Spec Synthesis (SDD)] <------+  (Reflexion Retry Loop on Spec Failure)
             |                       |
             v                       |
   [S3: TDD Invariant Authoring]     |
             |                       |
             v                       |
   [S4: Domain Service Impl]         |
             |                       |
             v                       |
   [S5: Zero-Host Verification] -----+  (Tests Failed: Trigger Reflexion to S3/S4)
             |
       (All Green)
             v
   [S6: Security & OAuth2 Audit]
             |
             v
   [S7: GEO & SEO Index Update]
             |
             v
   [S8: Clean Atomic Commit & Handoff]
```

### State Definitions & Operational Invariants

| State | Name | Active Agent | Precondition | Deterministic Exit Gate |
| :--- | :--- | :--- | :--- | :--- |
| **`S0`** | `INTENT_INGESTION` | Superagent | User prompt received | Intent disambiguated into formal requirements. |
| **`S1`** | `LTS_HEALTH_AUDIT` | `LtsWatchdog` | Repository loaded | Laravel LTS version verified; no critical CVEs. |
| **`S2`** | `SPEC_SYNTHESIS` | `SpecArchitect` | Requirements clear | `specs/*.md` valid against `feature_spec.schema.json`. |
| **`S3`** | `TDD_HARNESS` | `TddCraftsman` | Spec approved | Pest tests fail with descriptive domain errors (**Red**). |
| **`S4`** | `DOMAIN_SYNTHESIS` | `DomainEngineer` | Failing tests exist | Fat Services created; `./bin/sail-run pest` is **Green**. |
| **`S5`** | `STATIC_VERIFICATION` | Superagent | Tests green | PHPStan Level 8 passes ($0$ errors); Pint compliant. |
| **`S6`** | `SECURITY_AUDIT` | `SecurityAuditor` | Service complete | Passport OAuth2 scopes mapped and verified. |
| **`S7`** | `GEO_SEO_SYNC` | `GeoSeoSpecialist`| Route functional | `/llms.txt` and Schema.org JSON-LD updated. |
| **`S8`** | `TERMINAL_HANDOFF` | Superagent | All gates passed | Clean Git diff ready for human review. |

---

## 3. Step-by-Step Execution Protocol for Any Agent

Every AI agent operating in this repository MUST execute strictly according to the following 8-step protocol:

```
+---------------------------------------------------------------------------------------------------+
| STEP 1: VERIFY ENVIRONMENT ISOLATION (ZERO-HOST ENFORCEMENT)                                     |
| -> Never run `php`, `composer`, or `artisan` on the host operating system.                       |
| -> All commands MUST use `./bin/sail-run <command>` or `./vendor/bin/sail <command>`.             |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| STEP 2: CONSULT STATIC ANCHORS & SPECS (SPEC-DRIVEN DESIGN)                                       |
| -> Read `specs/templates/FEATURE_SPEC_TEMPLATE.md` before writing code.                          |
| -> Define Algebraic Data Types & DTOs in `app/DTOs/` using immutable `readonly class`.           |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| STEP 3: TDD RED-GREEN-REFACTOR HARNESS                                                            |
| -> Author unit tests in `tests/Unit/Services/` and feature tests in `tests/Feature/`.             |
| -> Verify test fails inside Sail: `./bin/sail-run pest --filter <TestName>`                      |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| STEP 4: IMPLEMENT DOMAIN LOGIC (THIN CONTROLLERS, FAT SERVICES)                                   |
| -> Controllers: Maximum 10 lines per action. No SQL queries, no business branching.               |
| -> Services: Implement `App\Contracts\Services\*Interface`. Wrap in `DB::transaction()`.          |
| -> Models: Pure Eloquent schema ($fillable, $casts, relationships). Zero business algorithms.     |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| STEP 5: VERIFY RIGID STATIC ANALYSIS (PHPSTAN LEVEL 8)                                            |
| -> Execute: `./bin/sail-run phpstan`                                                             |
| -> Zero errors allowed. No baseline ignores. Strict types: `declare(strict_types=1);`.           |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| STEP 6: SECURE VIA PASSPORT OAUTH2 & SCOPES                                                       |
| -> Protect routes in `routes/api.php` with `auth:api` and `scopes:read:*,write:*`.                |
| -> Use typed `App\Enums\OAuthScope` constants. Never hardcode scope strings.                     |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| STEP 7: GENERATE GEO & SEO SURFACES                                                               |
| -> Update `/llms.txt` and `/llms-full.txt` via `App\Services\Geo\LlmsTxtGeneratorService`.        |
| -> Provide Schema.org JSON-LD graphs via `App\Services\Seo\JsonLdSchemaService`.                 |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| STEP 8: TOKEN OPTIMIZATION AUDIT                                                                  |
| -> Apply changes using targeted contiguous chunks (`replace_file_content`).                       |
| -> Never rewrite entire 200+ line files when modifying small methods.                             |
+---------------------------------------------------------------------------------------------------+
```

---

## 4. Multi-Agent Delegation Matrix

| Agent Role | Specialty & Subsystem | Primary Input | Output Artifacts | Strict Constraints |
| :--- | :--- | :--- | :--- | :--- |
| **`Superagent`** | Master Meta-Orchestrator | User Goals & Prompts | DAG Task Plan & Execution Logs | Maintains state machine, prevents hallucinations. |
| **`SpecArchitect`** | Specification & DTOs | RFC Requirements | `specs/*.md`, `app/DTOs/*` | No implementation code; pure schemas & contracts. |
| **`TddCraftsman`** | Pest Testing & Arch Tests | Formal Specs & DTOs | `tests/Unit/*`, `tests/Feature/*` | Tests must fail before implementation begins. |
| **`DomainEngineer`**| Fat Domain Services | Failing Pest Tests | `app/Services/*`, `app/Contracts/*` | Controllers $\le 10$ lines; models schema-only. |
| **`SecurityAuditor`**| Passport OAuth2 Security | Domain Routes & Auth | `config/passport.php`, Auth Tests | Zero plaintext secrets; scopes enforced. |
| **`GeoSeoSpecialist`**| AI Indexing (GEO) & SEO | Domain Models & Content | `/llms.txt`, JSON-LD Schemas | Valid Schema.org; bounded token chunks. |
| **`LtsWatchdog`** | LTS Upgrades & CVEs | `composer.json` / CVE DB | `docs/LTS_UPGRADE_PROPOSAL.md` | Proposes non-breaking LTS updates in Sail. |
| **`TokenEconomist`**| Context & KV-Cache | Agent Transcripts | Diffs, Compressed Payloads | Caps agent turn budgets ($< 2,000$ tokens). |

---

## 5. Token Economy & KV-Cache Preservation Rules

To maximize KV-cache reuse in Large Language Model backends (Gemini, Claude, GPT-4o):

1. **Static Prompt Header Invariance**: The contents of `AGENTS.md`, `.gemini/GEMINI.md`, and core DTO interfaces must remain unmodified across consecutive turns to guarantee cache hits ($C_{\text{read\_cache}} \approx 0.10 \times C_{\text{uncached}}$).
2. **Progressive Skill Disclosure**: Detailed runbooks in `.agents/skills/*/SKILL.md` are mounted dynamically on-demand. Never inject all skills into the initial prompt.
3. **Surgical Diff Mutations**: Use `replace_file_content` targeting small contiguous line chunks ($\le 40$ lines) instead of full-file overwrites.
4. **Inter-Agent Message Compression**: Subagents pass structured JSON summaries with schema IDs rather than conversational prose.

---

## 6. Proactive Startup Routine for the Superagent

Whenever initializing a session in this repository:
```bash
# 1. Verify container runtime health
./bin/sail-run php artisan --version

# 2. Run proactive LTS & CVE Watchdog scan
./bin/sail-run php artisan system:check-lts

# 3. Verify clean baseline state
./bin/sail-run pest tests/Architecture
```
If an LTS patch or security advisory is detected, the Superagent immediately presents the upgrade report from [`docs/LTS_UPGRADE_PROPOSAL.md`](file:///home/david/Proyectos/webs/dbcms/docs/LTS_UPGRADE_PROPOSAL.md) before accepting new feature work.
