<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat;

/**
 * Outcome of a single Validator::validate() call.
 *
 * `$valid === true` implies `$errors === []`. The reverse implication
 * does not hold; an empty errors list with `valid: false` is treated
 * as a programming error and the constructor enforces consistency.
 */
final readonly class ValidationResult
{
    /**
     * @param  list<ValidationError>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {
        if ($valid && $errors !== []) {
            throw new \InvalidArgumentException(
                'ValidationResult cannot be valid AND carry errors.'
            );
        }
        if (! $valid && $errors === []) {
            throw new \InvalidArgumentException(
                'ValidationResult cannot be invalid with an empty errors list.'
            );
        }
    }
}
