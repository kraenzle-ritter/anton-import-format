# Producer Mapping Guide

This guide tells producers (agate, Anton's Excel-Import, future ingest
tools) how to emit `metadata.json` that conforms to v0.1 of the schema.
It is the practical companion to the schema reference in
[../README.md](../README.md): "what does the validator accept" vs. "what
should I emit".

The canonical example for each producer lives under
`tests/Fixtures/<producer-target>/` — see `agate-target/folder-input.json`
for the agate side.

## Top-level wrapper

| Field       | Required | Notes                                                                                                  |
|-------------|----------|--------------------------------------------------------------------------------------------------------|
| `version`   | yes      | `"0.1"` for the current schema. Validator's `validateWithVersionWarning()` flags mismatches.            |
| `tenant`    | no       | Producer-side pipelines (notably agate) often don't know the target Anton tenant — leave it out or set `null`. Anton's importer overrides this with its own `setting('slug')` regardless. |
| `generator` | yes      | Convention: `<package>@<version>` (e.g. `agate@0.1.0`, `anton-excel-import@0.58.2`). |
| `defaults`  | no       | Producer-wide defaults for `match_by` / `on_not_found`. agate has no sensible default policy → leave out (schema falls back to `match_by: label` / `on_not_found: create`). Anton's Excel-Import may set `defaults.on_not_found` based on the `--create-actors`/`--create-keywords`/`--create-places` flag combination. |
| `entries`   | yes      | Top-level array of records. Order matters for parent resolution when `parent.identifier`/`parent.id` is used; UUID-only refs are order-independent. |

## Per-entry mapping (agate-side)

agate's pre-restructure `CreateMetadataJsonStep` emits flat fields per
entry (`title`, `parent_uuid`, `creation_*`, `scope_and_content`, …).
The new wrapper form expects nested first-class blocks. Mapping:

| agate field today                          | v0.1 target                                                                                |
|--------------------------------------------|--------------------------------------------------------------------------------------------|
| `title: "X"` (plain string)                | `title: {"de": "X"}` — wrap into a multilingual object using agate's default locale (German for the Swiss-archives use-case; configurable per pipeline). |
| `parent_uuid: "abc..."`                    | `parent: {"uuid": "abc..."}` (object form).                                                |
| `parent_uuid: null` (root)                 | omit `parent` key entirely.                                                                |
| `level_of_description: "class"`            | `level_of_description: "class"` — same enum: `collection`, `recordgroup`, `fonds`, `series`, `class`, `file`, `item`. |
| `creation_actors: ["Anna Müller"]`         | `events[0]: {type: "creation", actor: {label: {"de": "Anna Müller"}, type: "person"}}`. Use inline-form because agate has no DB-id; producer-side adapters omit `match_by`/`on_not_found` so the consumer's defaults apply. |
| `creation_place: ["Berlin"]`               | `events[0].place: {label: {"de": "Berlin"}}` — inline-form. |
| `creation_date_start`, `creation_date_end` | `events[0].date_start`, `events[0].date_end` — keep the existing `0000-00-00`-tolerant string convention. |
| `creation_date_start_ca`, `_end_ca`        | `events[0].date_start_ca`, `events[0].date_end_ca`. |
| `creation_event_details: "..."`            | `events[0].details: "..."`. |
| `scope_and_content: "..."`                 | `notes[]: [{type: "scopecontent", locale: "de", text: "..."}]` — Anton's DB stores these as polymorphic notes with a `note_type_id` (Notetype enum value, `scopecontent = 18`). |
| `extent_text: "..."`                       | `notes[]: [{type: "extent_text", locale: "de", text: "..."}]` — Notetype enum value 11. |
| `internal_note: "..."`                     | `notes[]: [{type: "internal_note", locale: "de", text: "..."}]` — Notetype 42. Anton's UI marks these as private. |
| `object_count: 2`, `object_type: "..."`    | keep at top-level — `additionalProperties: true` on the entry lets these pass through; Anton's importer maps them to the AntonObject's `object_count` / `object_type_id` columns. |

## Per-file mapping

The file-level metadata that agate's PRONOM/ExifTool/NARA-risk pipeline
collects maps cleanly into the v0.1 file schema:

| agate field      | v0.1 target                                                                  |
|------------------|------------------------------------------------------------------------------|
| `name`           | `name` (required).                                                           |
| `file_path`      | `file_path`.                                                                  |
| `mime_type`      | `mime_type` (required).                                                       |
| `md5sum`         | `md5sum` (required, must match `^[a-f0-9]{32}$`).                            |
| `size`           | `size_bytes` (rename for clarity; numeric).                                   |
| `pronom_id`      | `pronom_id`.                                                                  |
| `nara_risk: "Low"` | `nara_risk: "low"` — schema enum is lowercase: `low` / `moderate` / `high` / `unknown`. Producer normalizes case. |
| `nara_action`    | passes through via `additionalProperties: true`; Anton has no first-class column for this yet but stores it under `media.custom_properties`. |
| `modified`       | passes through. agate's `File:FileModifyDate` from ExifTool — Anton ignores it for v0.1, may map to a future `modified_at` field. |
| `exiftool: {...}` | `exiftool` as a free-form object (already in schema as `type: object`). |

## Per-entry mapping (Excel-Import side)

Anton's `anton:import file.xlsx --dump-metadata-json` emits the same
wrapper form. Mapping is described in the Anton-Repo's
`anton-import-format-validation` OpenSpec change (`tasks.md` task 7,
`MetadataJsonExporter` service): per-locale-suffixed columns
(`title-de`, `title-fr`) collapse into the multilingual object;
per-event-role columns (`event_creator_actor`, `event_recipient_actor`)
become entries in the `events[]` array; etc.

## Defaults and inheritance

Top-level `defaults` is inherited by every authority spec that omits
`match_by` / `on_not_found`:

```json
{
  "defaults": { "match_by": "label", "on_not_found": "create" },
  "entries": [
    {
      "type": "record",
      "uuid": "...",
      "title": {"de": "..."},
      "events": [
        {
          "type": "creation",
          "actor": {
            "label": {"de": "Anna Müller"},
            "type": "person"
            // inherits match_by: "label", on_not_found: "create"
          }
        }
      ]
    }
  ]
}
```

If `defaults` is omitted, the schema-level fallbacks apply:
`match_by = label`, `on_not_found = create`.

## What producers MUST handle

- **Multilingual wrapping**: even if the source data has only one
  locale, the producer wraps it into `{<locale>: "X"}`. The schema
  rejects bare strings.
- **UUID generation**: every entry needs a UUID. agate already
  generates them; Anton's Excel-Import auto-generates when the row
  doesn't carry one (and persists the new UUID back to the row).
- **Locale-code split**: ISO-639-1 (two letters) for content keys
  (`title`, `label`, note `locale`); ISO-639-2 (three letters) for
  entries' `languages[]` array.
- **Authority normalisation**: free-text actor/place names from the
  source ("Anna Müller, Heinrich Schmidt") need to be split into
  individual inline-form entries. Producer is responsible for the
  split heuristic.

## What producers MAY skip

- `tenant` (set to `null` or omit entirely if unknown).
- `defaults` (schema-level fallbacks apply).
- `match_by` / `on_not_found` on individual specs (inherits from
  `defaults` or schema-level).
- Empty arrays — `events: []` may be omitted; same for `notes`,
  `keywords`, `places`, `languages`, `files`.
- Optional file-level fields (everything except `name`, `mime_type`,
  `md5sum`).
