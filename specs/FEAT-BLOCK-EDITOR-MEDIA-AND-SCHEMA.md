# Block editor media and schema contract

## Objective

Make every block form explain its fields consistently, expose media selection at
the point where a payload URL is edited, and keep stored block payloads
compatible. The change is additive: existing keys, defaults and renderers remain
authoritative.

## Schema metadata

`BlockTypeInterface::getEditorSchema()` continues to return an associative map.
The editor recognizes these optional keys:

| Key | Meaning |
|---|---|
| `type` | `text`, `string`, `textarea`, `url`, `email`, `number`, `integer`, `boolean`, `select`, `json`, `array`, `list`, `repeater`, `media` |
| `label` | Visible label; falls back to the key |
| `description` | Help text below the control |
| `placeholder` | Placeholder for text-like controls |
| `default` | Value used by empty insertion; complex values are cloned |
| `rules` | Laravel validation rules; the registry provides safe defaults |
| `required` | Adds `required` to the generated rule and control |
| `options` | Select list or `{value: label}` map |
| `show_when` | Object, or list of objects, evaluated against the bound object |
| `media_kind` | `image` or `video` for `media` fields |
| `alt_key` | Optional sibling key that receives the selected file's alt text |
| `min`, `max`, `step` | Numeric input metadata |

`show_when` accepts:

```php
['field' => 'chart_type', 'equals' => 'donut']
['field' => 'post_type', 'in' => ['bar', 'line']]
['field' => 'post_id', 'falsy' => true]
['field' => 'status', 'not_equals' => 'archived']
```

## Media contract

A `media` field is a string payload value. It renders a preview, an editable URL
input, a picker button and a clear button. The picker receives:

```js
window.dispatchEvent(new CustomEvent('open-filemanager', {
    detail: { target: targetId, kind: 'image' },
}));
```

The modal responds with:

```js
window.dispatchEvent(new CustomEvent('file-selected', {
    detail: { target: targetId, file },
}));
```

Closing without selecting emits:

```js
window.dispatchEvent(new CustomEvent('filemanager-closed', {
    detail: { target: targetId },
}));
```

Consumers keep one pending request at a time. A selection only applies when the
target matches; cancellation clears the request. The file manager filters by
`image`, `video` or `doc`, validates that the chosen file has the requested type,
and rejects mismatched uploads.

Automatic media inference applies to payload keys ending in `_url` whose final
semantic segment is `image`, `photo`, `avatar`, `logo`, `poster`, `thumbnail`,
`cover`, `video`, `movie` or `clip`. Blocks with ambiguous keys declare `media`
explicitly.

## Repeaters

Repeater children use the same field renderer as the root. A child may be text,
textarea, number, boolean, select, media, list or JSON. The add button initializes
the child from schema defaults; empty insertion never copies demo media paths.

## Compatibility

- No block payload key is renamed.
- No block migration is required.
- Existing payloads without a schema field remain valid and untouched.
- Unsafe stored media URLs are emptied at render time, matching normal URL
  fields.
- The server remains authoritative for validation; hidden conditional fields are
  not deleted.

## Verification

- `tests/Feature/Ui/BlockEditorCompositionTest`: every registered block type
  renders a control branch in both admin brands.
- `tests/Feature/Ui/BlockEditorSchemaContractTest`: every field has a supported
  type and label; media fields declare a media kind.
- `tests/Frontend/block-manager.test.mjs`: empty defaults, visibility, media
  selection, alt autofill and cancellation.
- `tests/Feature/Blocks/BlockUrlSafetyTest.php`: executable media URLs are
  rejected in validation and removed from rendered output.
- `tests/Regression/BlockEditorMediaPickerContractTest.php`: appearance pickers
  target the active scope and the media modal exposes type filtering/cancel.
