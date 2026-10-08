# Feature Specification: Backoffice Multilingual Content, Rich Section Customization & Widget Demo Presets

> **Spec ID**: `FEAT-009`  
> **Status**: APPROVED  
> **Author**: `SpecArchitect` Agent  
> **Date**: 2026-09-09  

---

## 1. Domain Objective & Context

In the backoffice CMS editing experience:
1. **Multilingual Content & Block Loading**: Content must default to the primary system language (`es`). When switching language tabs, existing translations must load accurately. When an entity or section is missing translations for the target locale, the user must be offered a seamless one-click copy tool to import content, meta attributes, and modular blocks from any desired source locale.
2. **Rich Section Customization**: Section blocks in the Block Manager must allow comprehensive visual design options:
   - Custom background color selector (hex/rgb/swatches).
   - Configurable top and bottom borders (enable/disable, color, width, style).
   - Decorative SVG shape dividers (waves, tilt, curves, triangles, spikes) at top and bottom.
   - Section container width modes: Centered (`max-w-7xl mx-auto`), Narrow (`max-w-4xl mx-auto`), or Full Viewport Width (`100%`).
   - Media backgrounds: High-resolution images or HTML5 background videos (`.mp4`/`.webm`) with customizable opacity overlays for legibility.
   - Scroll-driven animations via ScrollMagic: viewport video playback, continuous rotation on scroll (`scroll-rotate`), and frame-scrubbing.
3. **Widget Insertion Presets**: When adding widgets or content blocks, administrators can choose between "✨ Insertar con Datos Demo" (realistic, production-ready presets) or "📄 Insertar Vacío" (blank template).

---

## 2. Algebraic Data Types & DTO Contracts

### `SectionStylingDTO`
```php
namespace App\DTOs\Cms;

use App\DTOs\BaseDTO;

readonly class SectionStylingDTO extends BaseDTO
{
    public function __construct(
        public ?string $bgColor = null,
        public ?array $borderTop = null,
        public ?array $borderBottom = null,
        public ?array $dividerTop = null,
        public ?array $dividerBottom = null,
        public string $contentWidth = 'centered',
        public string $bgType = 'none',
        public ?string $bgImageUrl = null,
        public ?string $bgImageOverlay = null,
        public ?string $bgVideoUrl = null,
        public ?string $bgVideoPoster = null,
        public ?string $bgVideoOverlay = null,
        public ?array $scrollMagic = null,
        public string $containerStyle = 'cyber-card',
    ) {}
}
```

---

## 3. Formal Invariants (Hoare Logic: $P \{Q\} R$)

- **Preconditions ($P$)**:
  - Request originates from an authenticated user with `access:admin` or `manage:posts`/`manage:pages` permissions.
  - Multi-language payload contains valid locale codes registered in the `languages` table.
  - `sections_json` can be provided either as a flat array of blocks or as an associative dictionary keyed by locale code (`{"es": [...], "en": [...]}`).
- **State Mutations ($Q$)**:
  - `syncSections` runs inside `DB::transaction()`.
  - When saving localized blocks, sections maintain persistent sorting order and create or update matching `SectionTranslation` records per active language.
- **Postconditions ($R$)**:
  - Frontoffice routes load localized section content for the requested locale with fallback to primary locale if missing.
  - Section HTML output contains the full styling manifest: inline background styling, borders, SVG shape dividers, container sizing classes, and `data-scroll-*` attributes.

---

## 4. Test Invariant Checklist

- [ ] Admin page/post/product edit forms initialize with the default locale (`es`).
- [ ] Switching to a locale with existing translations displays that locale's translations.
- [ ] Switching to an empty locale shows empty fields and a copy prompt from other languages.
- [ ] Copy action transfers title, slug, meta tags, and blocks from the source locale to the target locale.
- [ ] `SectionRendererService` renders background colors, borders, SVG shape dividers, container widths, and background video/image overlays.
- [ ] `SectionRendererService` generates ScrollMagic attributes for video playback and scroll-driven rotation.
- [ ] Block manager provides modal selection for demo data vs blank template.
- [ ] All 146+ tests continue to pass with 0 regressions.
- [ ] PHPStan Level 8 passes with 0 errors.
