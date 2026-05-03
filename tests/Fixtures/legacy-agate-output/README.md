# Legacy agate-Output Fixtures

These two files are real `metadata.json` outputs from agate's
`CreateMetadataJsonStep` as it existed on **2026-05-02**, before the
schema-package was restructured to its current Anton-centric form.

They are **not validated against the current v0.1 schema** — they
encode the previous (top-level-array, single-string title, no
ObjectReference, no AuthorityReference, …) wire-form that Andreas
documented in the original agate-side `anton-import-format-package`
proposal.

## Why we keep them

- **Migration baseline.** When agate's `CreateMetadataJsonStep` is
  rewritten to emit the new wrapper shape, these files document
  *what was emitted before* — useful both for diff-checks and for
  reasoning about backward-compat.
- **Test fodder for the agate side.** Once agate's restructure
  ships, an "old → new" converter on the agate side can be tested
  against these files.

## Files

- `folder-input.json` — output of agate's folder-input pipeline
  (small, ~150 lines).
- `kost-eCH1.3F.json` — output of an eCH-0160 KOST SIP ingest
  (large, ~1500 lines, exercises the full real-world shape).

## Status

Read-only. Not run by the test suite. Will be removed when agate's
producer side has migrated to the v0.1 wrapper shape and these
files are no longer the current reality.
