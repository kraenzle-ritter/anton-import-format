<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat;

use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\Validator as OpisValidator;
use RuntimeException;

/**
 * Loads the bundled anton-import schema and exposes it via opis/json-schema.
 *
 * Single point of entry for the schema document. Caches the parsed schema
 * by $id so repeated lookups don't re-parse.
 */
final class SchemaLoader
{
    public const SCHEMA_ID = 'https://kraenzle-ritter.ch/schemas/anton-import/0.3/schema.json';

    private static ?OpisValidator $validator = null;

    /**
     * Path to the bundled schema JSON file.
     */
    public static function schemaPath(): string
    {
        return dirname(__DIR__) . '/schema/anton-import.schema.json';
    }

    /**
     * Returns the schema document as a decoded array.
     *
     * @return array<string, mixed>
     */
    public static function schemaArray(): array
    {
        $path = self::schemaPath();
        if (! is_readable($path)) {
            throw new RuntimeException(sprintf('Schema file not readable at %s', $path));
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException(sprintf('Schema file unreadable: %s', $path));
        }
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Schema file did not decode to an array.');
        }

        return $decoded;
    }

    /**
     * Returns the major.minor version embedded in the schema's $id.
     */
    public static function schemaVersion(): string
    {
        $schema = self::schemaArray();
        $id = $schema['$id'] ?? '';
        if (! is_string($id) || ! preg_match('#/anton-import/(\d+\.\d+)/schema\.json$#', $id, $matches)) {
            throw new RuntimeException('Schema $id does not embed a major.minor version.');
        }

        return $matches[1];
    }

    /**
     * Returns a configured opis Validator with the schema registered.
     */
    public static function validator(): OpisValidator
    {
        if (self::$validator instanceof OpisValidator) {
            return self::$validator;
        }

        $validator = new OpisValidator();
        $resolver = $validator->resolver();
        if ($resolver instanceof SchemaResolver) {
            $resolver->registerFile(self::SCHEMA_ID, self::schemaPath());
        }

        self::$validator = $validator;

        return $validator;
    }

    /**
     * Reset cached state — primarily for tests.
     */
    public static function reset(): void
    {
        self::$validator = null;
    }
}
