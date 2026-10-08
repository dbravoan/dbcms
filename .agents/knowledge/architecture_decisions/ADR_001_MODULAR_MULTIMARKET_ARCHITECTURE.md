# ADR 001: Modular Multi-Market & Multilanguage Architecture with Pluggable Lifecycle

## Status
ACCEPTED

## Context
The CMS needs to support multiple international markets (e.g. ES, US, MX, Global) and multiple languages per market without duplicating content in the database. Additionally, features like the Blog, Galleries, Forms, and Ads must be pluggable, discoverable modules (`modules/{Name}/`) that can be activated, deactivated, installed (migrations), or uploaded as ZIP packages from the backoffice.

## Decision
1. **Core Foundation**:
   - `markets` and `languages` tables linked by `market_language` pivot.
   - Normalized translation tables (`{entity}_translations`) linked to entities and optionally overridden per market.
   - `market_{entity}` pivots defining which markets a piece of content is published to.
2. **Module System (`App\Core\Modules`)**:
   - `ModuleManager` scans `modules/` for `module.json`.
   - `isPageable(): bool` dictates whether the module registers standalone entity routes (e.g., `/blog/{slug}`).
   - `getWidgets(): array` exposes embeddable components for composite CMS pages.
3. **Execution**:
   - Canonical Laravel Sail (`./vendor/bin/sail`).
   - Zero host PHP execution.

## Consequences
- Eliminates duplicate database rows across markets.
- High cohesion and zero monolithic entanglement: extensions exist cleanly in `modules/`.
- Automatic multi-directional `hreflang` and Schema.org `inLanguage` generation.
