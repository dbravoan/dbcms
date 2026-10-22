# 21 · Form Rendering & Field Types

> **Status**: Implemented
> **Scope**: schema-driven Forms and blog comment fields; ordinary backoffice forms use the separate admin component kit
> **Related**: [`01_ARCHITECTURE_AND_CLEAN_CODE.md`](01_ARCHITECTURE_AND_CLEAN_CODE.md) · [`11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md`](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md) · [`14_LOCALIZATION_AND_I18N.md`](14_LOCALIZATION_AND_I18N.md) · [`19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md`](19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md)

---

## 1. One renderer, three sources

Form fields are rendered and validated by a single framework in
`App\Core\Forms`. The same component serves a database-backed form, an inline
array written in Blade and a JSON payload:

```blade
<x-cms-form.fields
    :schema="app(\App\Core\Forms\SchemaFactory::class)->fromModels($form->fields)"
    variant="cyber"
    layout="grid"
    honeypot="_hp_check"
/>
```

| Source | Entry point |
| :--- | :--- |
| Eloquent models (`form_fields`) | `SchemaFactory::fromModels($form->fields)` |
| Inline array / Blade | `SchemaFactory::fromArray([...])` |
| JSON (API / MCP / front-end) | `SchemaFactory::fromJson($json)` |

The component centralizes the honeypot, `old()` and validation errors. The
caller only chooses the **visual variant** (`cyber`, `light`, `comment`,
`comment-<theme>` or an array of class overrides) and the layout (`grid` /
`stack`).

---

## 2. Field types are a registry, not a switch

| Piece | Responsibility |
| :--- | :--- |
| `Contracts\FieldType` | `key()`, `available()`, `stores()`, `rules()`, `view()` |
| `FieldTypeRegistry` | runtime catalog (`resolve()` returns an available registered type, otherwise `null`; there is no unknown-type fallback to `text`) |
| `FieldTypeCatalog` + `config/forms.php` | declared field keys and owners; this catalogue does not register implementations or provide fallback types |
| `CoreFieldType` | the primitive controls (`text`, `email`, `tel`, `number`, `textarea`, `select`, `checkbox`, `radio`) |
| `FieldSchema` | immutable field description (key, type, label, placeholder, required, rules, options) |
| `FormSchemas` | schemas declared in code + `compileRules()` |

Adding a type is a one-class change plus a registration in the module's
`boot()`:

```php
$this->app->make(FieldTypeRegistry::class)->register(new MyFieldType);
```

Rules are returned **keyed by input name** and `stores()` decides whether those
inputs reach the stored payload — that is how an anti-spam field can be
validated but never persisted.

> **Rule format**: pipe-delimited rule strings are split by the framework
> (`max:100` → its own rule). Laravel does not explode pipes inside an array
> element, so a type must return one rule per entry.

---

## 3. The Forms module (database-backed)

- The builder (`modules/Forms/Resources/views/admin/_builder.blade.php`) is driven by
  `FieldTypeOptions`, which lists the **available** registered types: a disabled
  module simply disappears from the builder.
- `FormDomainService::submitForm()` compiles the rules from the registry and
  stores only the inputs of types with `stores() === true`.
- The `widget_form` block uses `forms::components.form-engine` (`<x-forms::form-engine>` with module-owned presentation data) and renders
  the same schema.

## 4. The blog comment form (code-defined schema)

Comments are **not** a Forms record, yet they reuse the framework 100%:

```php
// App\Core\Forms\FormSchemas
$schemas = app(FormSchemas::class);
$request->validate($schemas->compileRules($schemas->blogComment()));
```

and in each theme:

```blade
<x-cms-form.fields
    :schema="app(\App\Core\Forms\FormSchemas::class)->blogComment()"
    variant="comment"
    layout="grid"
    honeypot="_hp_security_check"
/>
```

The same schema supplies view and validation. Custom consumers must continue to
use it in both places; sharing a helper does not prove every future form is correct. Themes may override labels/copy (`blogComment(['content' => __('Tu Mensaje *')])`)
and pass their own `variant`.

---

## 5. Captcha: a field type with pluggable providers

The Captcha provider registers its field when the provider is loaded. The type
is offered and resolved only while its availability check succeeds. A deactivated
module removes it from the builder and stored fields are ignored by rendering
and validation; registration itself does not imply availability.

Provider selection (module settings):

| Driver | Behaviour |
| :--- | :--- |
| `internal` | Self-hosted SVG math challenge + signed token (no dependencies). |
| `recaptcha` | Google reCAPTCHA v3 (invisible; score threshold `min_score`). |
| `turnstile` | Cloudflare Turnstile (managed widget). |
| `both` | Internal **and** Google rendered; the submission passes when **any** of them verifies. |

Rules: an external driver without its `site_key`/`secret_key` silently falls
back to `internal`; verification happens server-side
(`https://www.google.com/recaptcha/api/siteverify` /
`https://challenges.cloudflare.com/turnstile/v0/siteverify`) with a 5s timeout,
and a provider outage returns failure for an external-only driver. In `both` mode,
a valid internal challenge can still satisfy verification; this is not a general
fail-open rule or a guarantee that every submission succeeds.

CSP: the external scripts are loaded with the per-request **nonce** (trusted by
`'strict-dynamic'`), and `SecurityHeaders` allowlists the provider's frames and
network calls (`frame-src` / `connect-src`) only while that driver is active.

---

## 6. Verification

```bash
./vendor/bin/sail pest tests/Unit/Core/Forms tests/Feature/Cms/FormFieldFrameworkTest.php
./vendor/bin/sail pest tests/Feature/Cms/CaptchaDriversTest.php tests/Feature/Blog
```

- `tests/Unit/Core/Forms` — registry, catalog resolution, schema factory (models/array/JSON), rule splitting.
- `tests/Feature/Cms/FormFieldFrameworkTest.php` — rendering from DB, array and JSON.
- `tests/Feature/Cms/FormCaptchaTest.php` — captcha as a builder field.
- `tests/Feature/Cms/CaptchaDriversTest.php` — drivers, score threshold, CSP.
- `tests/Feature/Blog/BlogCommentCaptchaTest.php` — comments through the shared schema.
