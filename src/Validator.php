<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator as OpisValidator;

/**
 * Validates `metadata.json` payloads against the Anton import format
 * schema. See README and schema/anton-import.schema.json for what
 * the format actually is; this class just wires opis/json-schema
 * to a small, framework-free, errors-as-data API.
 */
final class Validator
{
    private const DEFAULT_SCHEMA_PATH = __DIR__.'/../schema/anton-import.schema.json';

    private OpisValidator $opis;

    private \stdClass $schema;

    public function __construct(?string $schemaPath = null)
    {
        $path = $schemaPath ?? self::DEFAULT_SCHEMA_PATH;
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Cannot read schema at {$path}");
        }

        $decoded = json_decode($contents);
        if (! $decoded instanceof \stdClass) {
            throw new \RuntimeException(
                "Schema at {$path} is not a JSON object: ".json_last_error_msg()
            );
        }

        $this->schema = $decoded;
        $this->opis = new OpisValidator();
    }

    /**
     * Validate a metadata.json payload.
     *
     * Accepts the three representations agate and Anton internally
     * use: a raw JSON string (from disk), an associative array
     * (`json_decode($s, true)`), or a stdClass tree (default
     * `json_decode($s)`). All three normalise to the same internal
     * representation and produce identical results.
     */
    public function validate(string|array|\stdClass $input): ValidationResult
    {
        $data = $this->normalise($input);

        if ($data instanceof ValidationError) {
            return new ValidationResult(valid: false, errors: [$data]);
        }

        $result = $this->opis->validate($data, $this->schema);

        if ($result->isValid()) {
            return new ValidationResult(valid: true);
        }

        $formatter = new ErrorFormatter();
        $multiple = $formatter->formatOutput($result->error(), 'verbose');

        $errors = [];
        $this->collectErrors($multiple, $errors);

        if ($errors === []) {
            // opis reported invalid but produced no granular errors —
            // surface a synthetic top-level error so callers always
            // see SOMETHING when valid:false.
            $errors[] = new ValidationError(
                path: '',
                keyword: 'unknown',
                message: 'Schema validation failed without specific error details.',
            );
        }

        return new ValidationResult(valid: false, errors: $errors);
    }

    /**
     * Normalise a string|array|stdClass input into the form
     * opis/json-schema expects (stdClass for objects, array for
     * sequential lists). Returns a ValidationError with the
     * synthetic `parse_error` keyword if the input is a string
     * that does not parse as JSON.
     */
    private function normalise(string|array|\stdClass $input): mixed
    {
        if (is_string($input)) {
            $decoded = json_decode($input);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new ValidationError(
                    path: '',
                    keyword: 'parse_error',
                    message: 'Could not parse input as JSON: '.json_last_error_msg(),
                );
            }

            return $decoded;
        }

        if (is_array($input)) {
            // Array (PHP-side decoded with assoc=true) — re-encode and
            // decode without assoc so opis sees the canonical
            // stdClass-for-objects, array-for-lists shape.
            $encoded = json_encode($input);
            if ($encoded === false) {
                return new ValidationError(
                    path: '',
                    keyword: 'parse_error',
                    message: 'Could not re-encode array input: '.json_last_error_msg(),
                );
            }

            return json_decode($encoded);
        }

        // stdClass — pass through.
        return $input;
    }

    /**
     * Walk the verbose opis error tree and emit one ValidationError
     * per leaf failure. Intermediate "All array items must match"
     * / "The data should match exactly one schema" wrapper messages
     * are skipped — only the most specific leaf is useful for
     * callers.
     *
     * @param  array<int, ValidationError>  $into
     */
    private function collectErrors(array $node, array &$into): void
    {
        $children = $node['errors'] ?? null;
        if (is_array($children) && $children !== []) {
            foreach ($children as $child) {
                if (is_array($child)) {
                    $this->collectErrors($child, $into);
                }
            }

            return;
        }

        if (isset($node['error']) && is_string($node['error'])) {
            $into[] = new ValidationError(
                path: $this->normalisePath($node['instanceLocation'] ?? ''),
                keyword: $this->keywordFromLocation($node['keywordLocation'] ?? ''),
                message: $node['error'],
            );
        }
    }

    private function keywordFromLocation(string $location): string
    {
        if ($location === '') {
            return 'unknown';
        }

        $segments = explode('/', $location);
        $last = array_pop($segments);

        // opis URL-encodes $defs / $ref segments as %24defs / %24ref
        $last = rawurldecode($last);

        // Skip $ref / $defs / oneOf-index segments that are not real
        // schema keywords; walk back to a meaningful one.
        while ($last !== null && ($last === '' || $last === '$ref' || $last === '$defs' || ctype_digit($last))) {
            $last = array_pop($segments);
            if ($last !== null) {
                $last = rawurldecode($last);
            }
        }

        return $last ?? 'unknown';
    }

    private function normalisePath(string $location): string
    {
        if ($location === '' || $location === '#') {
            return '';
        }

        // opis prefixes paths with `#`; the spec calls for plain
        // JSON-Pointer (e.g. `/0/files/2`).
        return ltrim($location, '#');
    }
}
