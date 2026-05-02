<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat;

/**
 * One concrete validation failure.
 *
 * Errors travel as data, not exceptions: the Validator collects
 * them in a ValidationResult and the caller decides how to react.
 */
final readonly class ValidationError
{
    public function __construct(
        /**
         * JSON-Pointer-style path into the validated input, e.g.
         * "/0/files/2/uuid". The empty string "" denotes the root.
         */
        public string $path,
        /**
         * The schema keyword that was violated, e.g. "required",
         * "type", "enum", "minItems", or the synthetic "parse_error"
         * for inputs that are not even valid JSON.
         */
        public string $keyword,
        /**
         * Human-readable message produced by the underlying schema
         * library, lightly normalised. Useful for logging or UI;
         * not meant for machine matching — use `keyword` for that.
         */
        public string $message,
    ) {}
}
