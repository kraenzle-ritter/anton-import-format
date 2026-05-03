# Changelog

## v0.1.0 — Initial release (2026-05-03)

### Pre-tag refinements (2026-05-03 evening)

After the initial main push, two cross-repo issues from agate's side
prompted the following adjustments before the v0.1.0 tag:

- **`Validator::validate()` returns `ValidationResult`**, a
  `final readonly` value object with a `valid: bool` flag and an
  `errors: list<ValidationError>` array (instead of a flat
  `array<int, array{...}>`). Aligns with agate's existing wiring and
  the sibling-package convention (`puidentify`, `nara-risk`).
  Self-documenting `if (! $result->valid)` instead of
  `count($errors) === 0`.
- **`tenant` is optional** at the top level (and nullable). Producer
  pipelines that don't know the target Anton tenant (notably agate)
  may omit it; Anton's importer overrides with `setting('slug')`
  regardless.
- **New `docs/producers.md`** — concrete field-by-field mapping guide
  for agate's pre-restructure emit (`parent_uuid`, `creation_*`,
  `scope_and_content`, …) to v0.1 wrapper shape.
- **New `tests/Fixtures/agate-target/folder-input.json`** — schema-valid
  reference for what agate's `CreateMetadataJsonStep` should emit
  after its restructure. Companion to the read-only
  `legacy-agate-output/` snapshots.

### Initial release contents

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
