# Tabular (Excel) Import Format

This is the **flat, column-based** input shape that Anton's importer consumes
natively — an `.xlsx` spreadsheet (or any source `getData()` can turn into
header-keyed rows) where one row is one archival record and one column is one
field. It is the older and most widely used of Anton's import shapes; the
[JSON wrapper](../README.md) (`metadata.json`) is the newer structured
exchange format that producers like [agate](https://github.com/kraenzle-ritter/agate)
emit and that `MetadataJsonExporter` derives *from* these flat rows.

This document is the **canonical specification** of the tabular format.

> Unlike the JSON wrapper, the tabular format has **no JSON Schema and no
> `Validator` class** in this package — it is not JSON. Validation lives in
> Anton itself (`ImportValidator`), driven by `config/import.php`. This
> document mirrors that source of truth; when the two disagree, the code wins
> and this document is the bug.

## Two shapes, when to use which

| | Tabular (this doc) | JSON wrapper ([README](../README.md)) |
|---|---|---|
| Carrier | `.xlsx` spreadsheet | `metadata.json` |
| Authored by | archivists, by hand, in Excel | machines (agate, SIP producers) |
| Hierarchy | row order + `parent` reference | `parent: {uuid}` reference |
| Multilingual | single locale only (see Restrictions) | first-class `{de, fr, …}` objects |
| Validation | `ImportValidator` in Anton | JSON Schema + `Validator` in this package |
| Typical use | data migration, bulk cataloguing at one level, media batches | digital-preservation ingest, cross-system SIP exchange |

Reach for the tabular format whenever a human is producing the data in a
spreadsheet. Reach for the wrapper whenever a pipeline is producing it.

## How a file is read

1. **Header row → canonical names.** Row 1 holds column headers. Each header
   is looked up in the mapping table (`Mapping::get()`, seeded from
   `config/import.php` plus tenant-derived dynamic columns) and renamed to its
   canonical internal name. German and English aliases both resolve
   (`signatur` and `identifier` both map to `identifier`). Headers that match
   nothing are reported as *unmatched* and silently dropped — extra columns are
   allowed and ignored.
2. **Validation.** `ImportValidator` checks required columns, per-field rules,
   parent resolution, and media presence. Nothing is written until validation
   passes (unless explicitly skipped).
3. **Import.** Each row becomes (or updates) one `AntonObject`, with its
   events, notes, keywords, places, locations, languages, and media attached.

## Required columns

A create-mode file must contain **at least**:

```
parent
verzeichnungsstufe        (level_of_description)
```

In **update mode** (`--update`) the only required column is the match key —
`identifier` by default, or whatever `--default-excel-column` names.

Everything else is optional. A row with only `parent` + `verzeichnungsstufe`
imports an empty, untitled record at that hierarchy position.

## Core columns

These are the static columns from `config/import.php`. The **Header** column
lists accepted spellings (the German archival term is canonical for hand-built
files; the internal name is also accepted). The **Field** column is the
internal name the importer uses.

| Header(s) | Field | Rules |
|---|---|---|
| `parent` | `parent` | **required**, ≤100 chars. Must resolve to an existing record — see [Parent resolution](#parent-resolution). |
| `verzeichnungsstufe` | `level_of_description` | **required**, must be an existing level label — see [Level of description](#level-of-description). |
| `signatur` | `identifier` | ≤100 chars, unique. If empty, Anton generates one. |
| `altsignatur` | `identifier_old` | ≤100 chars. |
| `titel` | `title` | free text, single locale only. |
| `datum_von` | `creation_date_start` | Anton date (`YYYY`, `YYYY-MM`, or `YYYY-MM-DD`). Shortcut for `creation_date_start`. |
| `datum_bis` | `creation_date_end` | Anton date. |
| `datum_von_ca` / `datum_bis_ca` | `creation_date_*_ca` | `0` or `1` — flags the date as approximate. |
| `provenienz_autor` | `creation_actors` | shortcut for the creation event's actor(s) — see [Authority columns](#authority-columns-actors-places-keywords). |
| `objekttyp` | `object_type` | must be an existing object-type label (tenant-specific list). |
| `umfang_zahl` | `object_count` | integer; counts items of `object_type`. |
| `extent_rm` / `extent_lfm` | `extent_cm` | numeric (running metres → cm). |
| `sprache` / `sprachen` | `languages` | ISO-639-2/B codes — see [Languages](#languages). |
| `standort` | `location` | must be an existing location name (create in Admin → Locations first). |
| `vacat` / `fehlt` | `vacat` | `0` or `1` (empty ⇒ `0`). Marks a gap/missing unit. |
| `privat` / `private` | `private` | `0` or `1` (empty ⇒ `0`). Hides the record from the public view. |
| `schutzfrist` | `period_of_protection` | must be an existing protection period — see [Controlled vocabularies](#controlled-vocabularies). |
| `status_beschreibung` | `status_of_description` | controlled value (`draft` / `final`). |
| `detail_beschreibung` | `detail_of_description` | controlled value (`minimal` / `partial` / `full`). |
| `bilder` / `media` | `media` | ≤500 chars, one or more asset filenames — see [Media columns](#media-columns). |
| `private_bilder` / `private_media` | `private_media` | as `media`, but not publicly visible. |
| `digitalisiert_von` | `digitisation_actors` | shortcut for the digitisation event's actor(s). |

## Composed columns

Three column families are **composed** from a prefix plus a field name. Event
types come from a fixed enum; notes and user events are read from the tenant's
database and so vary per archive. Use `--show-possible-columns` against the
target tenant to print the exact accepted set.

### Event columns

Events link records to actors and places over time. A column is built by
prefixing an **event type** to a field name:

```
<eventtype>_actors
<eventtype>_place
<eventtype>_place_address      (only when the `import_addresses` setting is on)
<eventtype>_date_start
<eventtype>_date_end
<eventtype>_date_start_ca
<eventtype>_date_end_ca
<eventtype>_event_details
```

For example, the creation/runtime event uses `creation_actors`,
`creation_place`, `creation_date_start`, … The event types are a **fixed set**
(the `Eventtype` enum — every event stores its enum value as `event_type_id`):

```
creation  acquisition  reproduction  digitisation  reception  performance
provenance  preservation  engravation  writing  coloring  edition
production  text_author  ingest  other
```

`provenienz_autor`, `datum_von`, `datum_bis`, … (see Core columns) are
convenience aliases for the `creation_*` event columns.

### Note columns

ISAD(G) note fields are addressed as `note.<bare-name>`. They are all free
text. The available notes are the tenant's `antonfields` of type `note`; the
common set:

| Header | Note |
|---|---|
| `note.scopecontent` | Form und Inhalt |
| `note.accruals` | Neuzugänge |
| `note.appraisal` | Bewertung und Kassation |
| `note.arrangement` | Ordnung und Klassifikation |
| `note.bioghist` | Verwaltungsgeschichte / Biographie |
| `note.condition_of_access` | Zugangsbedingungen |
| `note.condition_of_reproduction` | Reproduktionsbestimmungen |
| `note.custod_hist` | Bestandsgeschichte |
| `note.date_comment` | Kommentar zur Datierung |
| `note.extent_text` | Umfang (Beschreibung) |
| `note.finding_aids` | Findmittel |
| `note.general_note` | Allgemeine Anmerkungen |
| `note.internal_note` | Archivinterne Bemerkungen (private) |
| `note.language_script` | Sprache/Schrift |
| `note.location_details` | Standort (Detail) |
| `note.physical_description` | Physische Beschaffenheit |
| `note.provenance` | Provenienz |
| `note.publications` | Publikationen |
| `note.related_units` | Verwandte Verzeichnungseinheiten |
| `note.reproductions` | Kopien/Reproduktionen |
| `note.rules_note` | Verzeichnungsgrundsätze |

The bare name without the `note.` prefix (e.g. `scopecontent`) is also
accepted; the German label slugged with underscores resolves too.

### User-event columns

For tenants that define user events (`antonfields` of type `user_event`):

```
<userevent>_users
<userevent>_date_start
<userevent>_date_end
<userevent>_event_details
```

## Parent resolution

`parent` says where the new record hangs in the hierarchy. The value is
resolved as:

- a **non-numeric** string → matched against `AntonObject.identifier`
  (signature);
- a **pure integer** → matched against `AntonObject.id` (read it off the
  record's permalink). Use this for units that have no signature, such as
  classes or record groups.

`ImportValidator::checkParents()` resolves each row's `parent` against either
a record already in the database **or** an `identifier` declared in an
**earlier** row of the same file. A parent declared in a *later* row does not
count — the file must be ordered parents-before-children. The two failure
modes (out-of-order vs. genuinely missing) are reported distinctly. The check
is skipped under `--skip-parent-validation` and `--update`.

## Level of description

`verzeichnungsstufe` must be one of the seven ISAD(G) levels. German label ⇄
internal name:

| Label (header value) | Internal name |
|---|---|
| Archiv | `collection` |
| Bestandsgruppe | `recordgroup` |
| Bestand | `fonds` |
| Klasse | `class` |
| Serie | `series` |
| Dossier | `file` |
| Einzelstück | `item` |

## Authority columns (actors, places, keywords)

Actor, place, and keyword columns accept free-text names, existing IDs, or —
for actors — an external-resource prefix. Multiple values are split on the
field's [separator](#separators).

### Actors (e.g. `creation_actors`, `digitalisiert_von`)

≤500 chars. Each value may be:

- a **name**, optionally with life dates in round brackets:
  `Müller, Martina (1934–1977)`. Round brackets are reserved for life
  dates — do not use them otherwise. Names are **not** format-validated.
- an existing **actor id** (integer);
- an external **resource reference** with a lowercase prefix and no space,
  e.g. `gnd:118519522`. The resource must be unique within Anton. If the
  actor does not exist yet, it is created from the GND/authority data.

Resolution-on-miss is governed by the `--create-actors` flag / `create-actors`
setting: when on, unknown actors are created; when off, they are skipped.

```
Müller, Martina (1934–1977) :: Rechtsabteilung
```

### Places (e.g. `creation_place`)

A place name or an existing place id (integer). A name may carry:

- alternative names, separated by `/`;
- a town/municipality;
- a canton/state in round brackets after the municipality.

Governed by `--create-places` / `create-places`.

### Keywords

| Header(s) | Field |
|---|---|
| `schlagworte` / `sachschlagworte` / `keywords` | `keywords_terms` |
| `schlagworte_akteure` / `schlagworte_names` / `keywords_actors` | `keywords_actors` |
| `schlagworte_orte` / `schlagworte_places` / `keywords_places` | `keywords_places` |

Governed by `--create-keywords` / `create-keywords`.

## Languages

`sprache` / `sprachen` takes one or more languages, each either an
[ISO-639-2/B](https://en.wikipedia.org/wiki/List_of_ISO_639-2_codes) code
(`ger` not `deu`, `fre` not `fra`) or a label exactly as it appears in the
tenant's language list. **Language is the one field separated only by `::`** —
comma and semicolon are not accepted here:

```
ger :: fre :: lat
```

## Media columns

`bilder`/`media` (public) and `private_bilder`/`private_media` (hidden) hold
asset filenames, separated by `::`, `,`, or `;`:

```
erstes_bild.tif :: zweites_bild.tif
```

A value may also be an `http(s)` URL; the validator fetches it locally during
the media check. All referenced files must be uploaded to the tenant's
`assets_to_import/` directory **before** import — `ImportValidator::checkMedia()`
fails on any missing or duplicate file.

## Separators

Separators are configurable per tenant (`import_separators` setting). Defaults:

| Field | Allowed separators |
|---|---|
| generic / actors | `::`, `;` |
| assets (media) | `::`, `,`, `;` |
| keywords | `::`, `,`, `;` |
| languages | `::` **only** |

`--show-separators` prints the active set for a tenant.

## Controlled vocabularies

Several columns must hold a value that already exists in the tenant's
vocabulary. The lists below are the common defaults; the authoritative set is
tenant-specific.

| Column | Common allowed values |
|---|---|
| `schutzfrist` (period_of_protection) | `public`, `standard`, `prolonged` |
| `status_beschreibung` (status_of_description) | `draft`, `final` |
| `detail_beschreibung` (detail_of_description) | `minimal`, `partial`, `full` |
| `objekttyp` (object_type) | tenant-defined (Akte, Bild, Band, Film, …) |
| `standort` (location) | tenant-defined |

## Restrictions

- **Two columns are mandatory** in create mode: `parent` and
  `verzeichnungsstufe`.
- **Parents before children.** Row order is significant; a parent referenced
  by `identifier` must appear in an earlier row (or already be in the DB).
- **No multilingual titles.** `titel` imports into a single locale; per-locale
  title columns are not yet supported on the tabular side (the JSON wrapper
  has them).
- **Authority strings are not pre-validated.** A malformed
  `Name (dates)` string imports as-is; round brackets are reserved for life
  dates.
- **Media must be uploaded first** and must be unique within the file.
- **Extra columns are ignored**, not rejected — a typo'd header silently drops
  that data. Check the *unmatched columns* warning during validation.
- **Controlled-vocabulary and location values must already exist** — the
  importer does not create object types, protection periods, or locations.
- **Update mode requires a match key** (`identifier` or
  `--default-excel-column`) and skips parent validation.
- **The dynamic column set is tenant-specific** — event types, notes, user
  events, object types, and locations vary per archive. Validate against the
  actual target tenant.

## Command-line usage

The browser import lives under `/import` (Excel tab). On the CLI:

```bash
# validate only (no --import)
php artisan anton:import --env=<slug> --file="test.xlsx"

# really import
php artisan anton:import --env=<slug> --file="test.xlsx" --import
```

The file is expected in `customers/<slug>/metadata_to_import/`; referenced
media in `customers/<slug>/assets_to_import/`.

### Options

| Option | Effect |
|---|---|
| `--import` | actually run the import (otherwise validate only) |
| `--update` | update existing records only (`identifier` required) |
| `--no-backup` | skip the pre-import database backup |
| `--dont-validate` | skip validation |
| `--skip-parent-validation` | build a hierarchy from a single file (parents not yet in DB) |
| `--create-actors` | create unknown actors |
| `--create-places` | create unknown places |
| `--create-keywords` | create unknown keywords |
| `--parent=<id>` | parent for an EAD import |
| `--from-ead` | source file is EAD XML (combine with `--parent` and `--dont-validate`) |

### Introspection flags

Useful for figuring out what a given tenant accepts:

| Flag | Output |
|---|---|
| `--show-columns` | original headers found in the file |
| `--show-column-mapping` | headers with their canonical mapping |
| `--show-possible-columns` | every accepted column name for this tenant |
| `--show-mapping` | the full mapping table |
| `--show-rules` | validation rules for this file |
| `--show-separators` | active separators |

## Relationship to the JSON wrapper

The two formats meet in `MetadataJsonExporter`:
`anton:import <file>.xlsx --dump-metadata-json` reads the flat rows and emits
the equivalent v0.x wrapper — the cross-system fixture/round-trip bridge
between Anton and agate. The reverse direction (`AgateMetadataMapper`) maps a
wrapper back into the flat `import_array` the importer consumes. So every
field documented here has a wrapper counterpart; see
[`docs/producers.md`](producers.md) for the per-field mapping.
