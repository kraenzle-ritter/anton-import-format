# Changelog

## v0.2.1 — Note.tracks for time-stamped media content (2026-05-15)

Additive patch: optional `tracks` array on `Note`. Producers MAY omit;
consumers MUST treat as additive. Pre-tracks Notes remain valid.

### Added

- **`Note.tracks`** — optional array of `{timestamp, content}` objects.
  Timecode format is producer-defined (typical: `HH:MM:SS` or
  `HH:MM:SS.mmm`); consumers SHOULD treat as opaque text. Both fields
  are required and must be non-empty strings.
- 4 new `ValidatorTest` cases pinning the contract:
  `test_note_without_tracks_is_valid`,
  `test_note_with_tracks_is_valid`,
  `test_note_with_malformed_track_item_fails` (missing `content`),
  `test_note_with_empty_track_string_fails` (empty `timestamp`).

### Why

Foundation for Anton's KI-Erschliessung change (`add-ai-assisted-description`):
the existing `Notetype::movie_content` workflow stores per-scene
descriptions one-row-per-timestamp. To round-trip movie descriptions
through the import-format (so agate/AI-pipelines can emit them and
Anton can re-import them), the time-stamped structure had to make it
into the wire-level Note schema. Used initially by AI-generated
movie/audio transcripts; mapping into Anton's existing per-row
`movie_content` storage stays an importer detail.

### Compatibility

- **Backward**: pre-v0.2.1 documents without `tracks` validate
  unchanged.
- **Forward**: a v0.2.1 document with `tracks` validates against the
  v0.2.0 schema too, because `additionalProperties: true` is set on
  `Note` — the new field passes through as unknown. Consumers that
  want to enforce the structure must load the v0.2.1 schema.

## v0.2.0 — NARA category enum (2026-05-10)

Additive minor: optional `nara_category` field on `RecordEntry` and `File`.
Pre-v0.2 documents continue to validate (no required field added).

### Added

- **`$defs/NaraCategory`** — string enum covering NARA's 16 file-format categories: Audio, Video, Cinema, StillImage, Geospatial, NavCharts, DesignVector, Textual, Spreadsheets, Presentation, Code, Email, Web, Databases, StructuredData, Calendars.
- **`RecordEntry.nara_category`** — optional, references `NaraCategory`. Used by the consumer (Anton) to map to its tenant `object_types` taxonomy. Producers (notably agate) emit the raw NARA category and stop carrying Anton-internal labels.
- **`File.nara_category`** — same enum, optional, alongside the existing `nara_risk`. Lets per-file categorization survive into Anton's media records.
- **`tests/Fixtures/valid/with-nara-category.json`** — schema-valid example with `nara_category` populated on a record entry and two file rows.
- **`tests/Fixtures/broken/unknown-nara-category.json`** — confirms an off-list value (`Banana`) is rejected by the enum.

### Changed

- **`$id` and `SchemaLoader::SCHEMA_ID`** bumped to `0.2`. Schema document `description` updated to reflect the additive change.
- **`SchemaTest::test_schema_version_helper_returns_major_minor`** asserts `'0.2'`.
- **`ValidatorTest::test_no_version_warning_when_versions_match`** now uses `version: '0.2'`.

### Why

Producer-side hardcoded mapping of NARA-category-to-Anton-vocabulary is a layer violation: the producer ends up knowing the consumer's per-tenant taxonomy. This release moves the contract: producers emit NARA-standard categories, Anton owns the resolution to its own (potentially per-tenant) `object_types`. See anton#199 for the full rationale and Anton-side OpenSpec at `openspec/changes/nara-object-type-mapping/` in the anton repo.

### Compatibility

- **Backward**: pre-v0.2 documents that don't emit `nara_category` validate unchanged. The schema's top-level `version` field is a pattern (`^[0-9]+\.[0-9]+$`), not a hard-coded string, so a v0.1 document still passes structural validation against v0.2.
- **Forward**: a v0.2 document with `nara_category` will fail validation against the v0.1 schema (the field was undefined and `additionalProperties: true` was set on the entry — actually, additive fields don't reject in v0.1's permissive base — but the enum constraint is only enforced when v0.2 schema is loaded). Producers should target v0.2 once the consumer wiring lands.

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
