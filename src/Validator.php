<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError as OpisValidationError;
use stdClass;

/**
 * Framework-free validator for Anton's import metadata.json.
 *
 * Returns a `ValidationResult` (with `valid` flag and an `errors` array
 * of `ValidationError` objects). Exceptions are reserved for catastrophic
 * conditions (malformed JSON in string input, schema file missing);
 * validation failures are always reported via the result.
 */
final class Validator
{
    /**
     * Validate input against the bundled anton-import schema.
     *
     * @param  string|array<mixed>|stdClass  $input
     *
     * @throws JsonException        When $input is a malformed JSON string.
     * @throws \RuntimeException    When the schema file is unreadable.
     */
    public function validate(string|array|stdClass $input): ValidationResult
    {
        $data = $this->normalize($input);

        $opisValidator = SchemaLoader::validator();
        $result = $opisValidator->validate($data, SchemaLoader::SCHEMA_ID);

        if ($result->isValid()) {
            return ValidationResult::valid();
        }

        $error = $result->error();
        if ($error === null) {
            return ValidationResult::valid();
        }

        return ValidationResult::invalid($this->formatErrors($error));
    }

    /**
     * Validate and additionally compare the input's `version` field with
     * the loaded schema's major.minor. A mismatch is appended to the
     * result's errors as a structured warning (path /version, keyword
     * `schema_version_mismatch`); the `valid` flag still reflects only
     * structural validation.
     *
     * @param  string|array<mixed>|stdClass  $input
     *
     * @throws JsonException
     * @throws \RuntimeException
     */
    public function validateWithVersionWarning(string|array|stdClass $input): ValidationResult
    {
        $base = $this->validate($input);

        $data = $this->normalize($input);
        $declaredVersion = $this->extractDeclaredVersion($data);
        if ($declaredVersion === null) {
            return $base;
        }

        $schemaVersion = SchemaLoader::schemaVersion();
        if ($declaredVersion === $schemaVersion) {
            return $base;
        }

        $warning = new ValidationError(
            path: '/version',
            keyword: 'schema_version_mismatch',
            message: sprintf(
                'Document declares version "%s" but loaded schema is "%s".',
                $declaredVersion,
                $schemaVersion
            ),
        );

        $errors = [...$base->errors, $warning];

        return new ValidationResult($base->valid, $errors);
    }

    /**
     * @param  string|array<mixed>|stdClass  $input
     *
     * @throws JsonException
     */
    private function normalize(string|array|stdClass $input): mixed
    {
        if (is_string($input)) {
            return json_decode($input, false, 512, JSON_THROW_ON_ERROR);
        }
        if (is_array($input)) {
            // opis/json-schema needs object-typed input; round-trip through JSON.
            $json = json_encode($input, JSON_THROW_ON_ERROR);

            return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        }

        return $input;
    }

    /**
     * @return list<ValidationError>
     */
    private function formatErrors(OpisValidationError $error): array
    {
        $formatter = new ErrorFormatter();
        $formatted = $formatter->format($error, true);

        $list = [];
        foreach ($formatted as $path => $messages) {
            $pathString = is_string($path) ? $path : '/';
            if (is_array($messages)) {
                foreach ($messages as $message) {
                    $list[] = new ValidationError(
                        path: $pathString,
                        keyword: $error->keyword(),
                        message: is_string($message) ? $message : (string) json_encode($message),
                    );
                }
            } else {
                $list[] = new ValidationError(
                    path: $pathString,
                    keyword: $error->keyword(),
                    message: is_string($messages) ? $messages : (string) json_encode($messages),
                );
            }
        }

        if ($list === []) {
            $list[] = new ValidationError(
                path: '/',
                keyword: $error->keyword(),
                message: $error->message(),
            );
        }

        return $list;
    }

    private function extractDeclaredVersion(mixed $data): ?string
    {
        if ($data instanceof stdClass && isset($data->version) && is_string($data->version)) {
            return $data->version;
        }
        if (is_array($data) && isset($data['version']) && is_string($data['version'])) {
            return $data['version'];
        }

        return null;
    }
}
