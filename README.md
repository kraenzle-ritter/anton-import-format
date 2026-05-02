# anton-import-format

JSON-Schema (Draft 2020-12) and Validator for the **Anton** archive
system's `metadata.json` import format.

This package is the single source of truth for the wire-format
contract between two writers and one reader:

- **agate** — the SIP producer pipeline. Writes `metadata.json` from
  `CreateMetadataJsonStep` and validates it before finalize via
  `ValidateInitializedStep`.
- **Anton's Excel-Import** — second writer; archivists upload an
  Excel sheet, Anton renders it to the same `metadata.json` shape,
  and the importer should validate it before DB insert.
- **Anton's `AgateImportHelper`** — reader for finalized SIPs from
  agate. Validates incoming `metadata.json` so a schema mismatch
  is caught with a clear error instead of half-written DB rows.

## Installation

```bash
composer require kraenzle-ritter/anton-import-format
```

## Usage

```php
use KraenzleRitter\AntonImportFormat\Validator;

$validator = new Validator();

// Accepts string, array, or stdClass — all three are normalised
// internally and produce identical results.
$result = $validator->validate(file_get_contents('path/to/metadata.json'));

if ($result->valid) {
    // Schema-conformant; safe to import.
} else {
    foreach ($result->errors as $error) {
        echo "[{$error->keyword}] {$error->path}: {$error->message}\n";
    }
}
```

## What the schema validates

The Anton import format is a **top-level JSON array** of entries.
Each entry is either a `collection` (a non-leaf node in the
hierarchy) or a `record` (a leaf carrying files).

**Required fields per entry type:**

- `collection`: `uuid`, `type`, `title`
- `record`: `uuid`, `type`, `title`, `files` (array, minItems 1)
- file (inside `record.files`): `uuid`, `name`, `file_path`,
  `mime_type`

All other fields agate or Anton happen to write — `parent_uuid`,
`level_of_description`, `creation_*`, `extent_text`,
`scope_and_content`, `internal_note`, `object_count`, `object_type`,
`md5sum`, `size`, `modified`, `pronom_id`, `nara_risk`, `nara_action`
— are tolerated via `additionalProperties: true` so a writer can
emit a legitimate subset.

## Design notes

- **Framework-free.** No Laravel, no Symfony container — the
  validator is a plain class so Anton, agate, and any standalone
  CLI can consume it identically.
- **Errors as data, not exceptions.** `validate()` returns a
  `ValidationResult` with a list of `ValidationError` objects
  (`path`, `keyword`, `message`). The caller decides whether to
  log, throw, or render.
- **Schema-library: `opis/json-schema`.** Draft 2020-12 support,
  small, MIT, actively maintained. The library choice is
  encapsulated behind the `Validator` class; consumers don't
  see it.

## Related

- [agate](https://github.com/kraenzle-ritter/agate) — the SIP
  producer that originated the format
- [puidentify](https://github.com/kraenzle-ritter/puidentify),
  [exif-extract](https://github.com/kraenzle-ritter/exif-extract),
  [nara-risk](https://github.com/kraenzle-ritter/nara-risk) —
  sibling Composer packages used by agate

## License

MIT — see [LICENSE](LICENSE).
