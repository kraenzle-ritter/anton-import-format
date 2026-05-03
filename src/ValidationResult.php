<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat;

/**
 * Outcome of a validator call.
 *
 * Immutable. `valid` is the self-documenting truth flag — consumers
 * `if (! $result->valid)` instead of inspecting array length. The
 * `errors` array carries structured details when validation failed
 * (or version-mismatch warnings flagged by validateWithVersionWarning).
 */
final readonly class ValidationResult
{
    /**
     * @param  list<ValidationError>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    /**
     * @param  list<ValidationError>  $errors
     */
    public static function valid(array $errors = []): self
    {
        return new self(true, $errors);
    }

    /**
     * @param  list<ValidationError>  $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }

    /**
     * Convenience: serialise the whole result to a plain array suitable
     * for JSON-encoding (Anton's ImportEvent.details, agate's step-result
     * checks payload).
     *
     * @return array{valid: bool, errors: list<array{path: string, keyword: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(static fn (ValidationError $e): array => $e->toArray(), $this->errors),
        ];
    }
}
