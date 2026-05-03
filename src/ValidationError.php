<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat;

/**
 * Single structured validation error.
 *
 * Immutable. The shape mirrors what consumers serialise into their own
 * error sinks (Anton's ImportEvent.details, agate's pipeline-step result).
 */
final readonly class ValidationError
{
    public function __construct(
        public string $path,
        public string $keyword,
        public string $message,
    ) {}

    /**
     * @return array{path: string, keyword: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'keyword' => $this->keyword,
            'message' => $this->message,
        ];
    }
}
