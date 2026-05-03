# anton-import-format

Canonical JSON Schema (Draft 2020-12) and a framework-free PHP Validator
for the `metadata.json` shape that Anton's importers consume.

This package is the single source of truth for "what does an Anton-import
look like" — used both by Anton itself (read-side validation in
`AgateImportHelper`, write-side dump via `anton:import --dump-metadata-json`)
and by external producers (notably [agate](https://github.com/kraenzle-ritter/agate),
the digital-preservation pipeline).

## Install

This package is distributed via VCS / GitHub. Add the repository entry and
the package as a Composer dependency:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/kraenzle-ritter/anton-import-format"
    }
  ],
  "require": {
    "kraenzle-ritter/anton-import-format": "^0.1"
  }
}
```

## Quickstart

```php
use KraenzleRitter\AntonImportFormat\Validator;

$validator = new Validator();

$errors = $validator->validate($metadataJson); // string | array | stdClass
if ($errors === []) {
    // valid — proceed with import
} else {
    // each $error has 'path', 'keyword', 'message' keys
    foreach ($errors as $error) {
        printf("[%s] %s: %s\n", $error['keyword'], $error['path'], $error['message']);
    }
}
```

### Version-aware validation

If you want a structured warning when the document declares a `version`
that does not match the loaded schema's major.minor:

```php
$errors = $validator->validateWithVersionWarning($metadataJson);
// errors may include {path: '/version', keyword: 'schema_version_mismatch', ...}
```

## Schema reference

A document is a top-level wrapper object with these required fields:

```json
{
  "version": "0.1",
  "tenant": "<anton-tenant-slug>",
  "generator": "<producer@version>",
  "defaults": { "match_by": "label", "on_not_found": "create" },
  "entries": [ ... ]
}
```

### Entries

Each entry is a `collection` or `record` and includes (among others):

- `uuid` (required) — UUID of the AntonObject. Primary identifier across
  the import; works even before DB ids exist.
- `type` — `"collection"` or `"record"`.
- `level_of_description` — ISAD(G) level: `collection`, `recordgroup`,
  `fonds`, `series`, `class`, `file`, `item`.
- `identifier` — archival signature (e.g. `KBA 1.1.1`).
- `title` — multilingual object: `{de: "...", fr: "...", en: "..."}`.
  Keys MUST be ISO-639-1 two-letter codes.
- `parent` — object reference: `{uuid: "..."}` (preferred), or
  `{identifier: "..."}` / `{id: 42}` for already-persisted parents.
- `events`, `notes`, `keywords`, `places`, `languages` — see below.
- `files` — only on `record` entries. Each file has at minimum
  `name`, `mime_type`, `md5sum`.

### References to other AntonObjects

Use `parent` or any other object-reference slot with the resolution order
**uuid > identifier > id**:

```json
"parent": { "uuid": "0193e8f7-..." }            // preferred (always works)
"parent": { "identifier": "KBA 1" }              // works if parent in DB
"parent": { "id": 42 }                           // works if parent in DB
```

### Authority references (Actor, Place, Keyword)

Two mutually-exclusive forms:

```json
// id-form: existing DB record
"actor": { "id": 42 }

// inline-form: match-or-create with explicit policy
"actor": {
  "label":    { "de": "Anna Müller" },
  "type":     "person",
  "match_by": "label",
  "on_not_found": "create"
}
```

`match_by` enum: `label` (any locale), `label.de`, `label.fr`, `label.en`,
`label.it`, `alternative_names`.

`on_not_found` enum: `create`, `error`, `skip`.

Both default from the wrapper's `defaults` object; per-spec values override.

### Multilingual content

Keys are ISO-639-1 two-letter codes (`de`, `fr`, `en`, `it`). The
`languages[]` array on entries uses ISO-639-2 three-letter codes (`ger`,
`fre`, `eng`, `lat`) — matching Anton's `languages.name` column.

### Files

Files are nested inside record entries (1:N):

```json
"files": [
  {
    "name":       "brief.pdf",
    "mime_type":  "application/pdf",
    "md5sum":     "5d41402abc4b2a76b9719d911017c592",
    "size_bytes": 20697,
    "pronom_id":  "fmt/14",
    "nara_risk":  "low"
  }
]
```

`md5sum` is required and must match `^[a-f0-9]{32}$`. `nara_risk` enum:
`low`, `moderate`, `high`, `unknown`.

## Version policy

- `0.x.y` releases may break across both `y` (point) and `x` (minor)
  while the schema iterates.
- `1.0.0` will be the first stability commitment — breaking changes from
  then on require a major bump.
- Consumers should pin `^0.1` while the schema is in 0.x.

## Test fixtures

Under `tests/Fixtures/`:

- `valid/minimal.json` — smallest valid document.
- `valid/full.json` — exercises every schema feature.
- `broken/*.json` — intentionally-broken cases, each pinning the
  validator's error reporting against regression.

## Development

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan level 8
```

## Consumers

- [Anton](https://github.com/kraenzle-ritter/anton) — read-side validation
  in `AgateImportHelper`, write-side dump option in `anton:import`.
- [agate](https://github.com/kraenzle-ritter/agate) — pipeline emits
  this shape in `CreateMetadataJsonStep` and validates pre-finalize via
  `ValidateInitializedStep`.

## License

MIT — see [LICENSE](LICENSE).
