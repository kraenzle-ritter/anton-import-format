# Changelog

## v0.1.0 — Initial release (2026-05-03)

First public release of the canonical Anton-import format.

### Schema (`schema/anton-import.schema.json`)

- Top-level wrapper with `version`, `tenant`, `generator`, optional
  `defaults`, and `entries[]`.
- ObjectReference: `uuid` (primary), `identifier`, or `id`. Resolution
  order documented; SIP/Directory imports rely on UUID-only.
- Multilingual content fields keyed by ISO-639-1 two-letter codes
  (`title`, authority `label`, note locale).
- Authorities (Actor / Place / Keyword): id-form (`{id: N}`) OR
  inline-form (`{label, type?, match_by?, on_not_found?, ...}`),
  mutually exclusive.
- `match_by` enum: `label`, `label.de`, `label.fr`, `label.en`,
  `label.it`, `alternative_names`.
- `on_not_found` enum: `create`, `error`, `skip`.
- Top-level `defaults` block; per-spec overrides allowed.
- `files[]` nested in record entries; required `name`, `mime_type`,
  `md5sum`.
- `languages[]` array uses ISO-639-2 three-letter codes.
- `additionalProperties: true` on the base schema (forward-compatible).

### Validator (`src/Validator.php`)

- Framework-free, accepts `string | array | stdClass`.
- Returns a list of structured errors (`{path, keyword, message}`);
  empty list = valid.
- No exceptions for validation failures (only for malformed JSON
  string input or unreadable schema file).
- Optional `validateWithVersionWarning()` adds a structured warning
  when the document's `version` doesn't match the loaded schema.

### Tests

- 20 PHPUnit tests, 98 assertions covering happy-path fixtures
  (minimal, full) and intentionally-broken fixtures (8 cases:
  missing version, bare-string title, mixed authority form,
  locale-code mismatches, file without md5sum, empty parent ref,
  unknown on_not_found enum value).

### Stability

`0.x.y` may break across point and minor releases. Consumers should
pin `^0.1`. `1.0.0` will be the first stability commitment.
