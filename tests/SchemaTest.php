<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat\Tests;

use KraenzleRitter\AntonImportFormat\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Schema-level expectations: real agate outputs pass, deliberately
 * malformed payloads fail with the keyword the spec promises.
 */
final class SchemaTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function agate_folder_input_output_passes(): void
    {
        $payload = file_get_contents(__DIR__.'/Fixtures/agate-folder-input.metadata.json');

        $result = $this->validator->validate($payload);

        $this->assertTrue(
            $result->valid,
            'Folder-input agate output must validate. Errors: '
            .json_encode(array_map(fn ($e) => "[{$e->keyword}] {$e->path}: {$e->message}", $result->errors))
        );
    }

    #[Test]
    public function agate_kost_output_passes(): void
    {
        $payload = file_get_contents(__DIR__.'/Fixtures/agate-kost-eCH1.3F.metadata.json');

        $result = $this->validator->validate($payload);

        $this->assertTrue(
            $result->valid,
            'KOST agate output must validate. Errors: '
            .json_encode(array_map(fn ($e) => "[{$e->keyword}] {$e->path}: {$e->message}", $result->errors))
        );
    }

    #[Test]
    public function top_level_object_instead_of_array_is_rejected(): void
    {
        $result = $this->validator->validate('{}');

        $this->assertFalse($result->valid);
        $this->assertContains(
            'type',
            array_map(fn ($e) => $e->keyword, $result->errors),
            'Top-level type mismatch must surface a "type" error.'
        );
    }

    #[Test]
    public function collection_without_uuid_is_rejected(): void
    {
        $payload = json_encode([
            ['type' => 'collection', 'title' => 'Mappe X'],
        ]);

        $result = $this->validator->validate($payload);

        $this->assertFalse($result->valid);
        $this->assertContains(
            'required',
            array_map(fn ($e) => $e->keyword, $result->errors),
            'Missing uuid on collection must surface a "required" error.'
        );
    }

    #[Test]
    public function collection_without_title_is_rejected(): void
    {
        $payload = json_encode([
            ['type' => 'collection', 'uuid' => 'abc-123'],
        ]);

        $result = $this->validator->validate($payload);

        $this->assertFalse($result->valid);
        $this->assertContains(
            'required',
            array_map(fn ($e) => $e->keyword, $result->errors),
        );
    }

    #[Test]
    public function record_with_empty_files_array_is_rejected(): void
    {
        $payload = json_encode([
            [
                'type' => 'record',
                'uuid' => 'abc-123',
                'title' => 'Sample',
                'files' => [],
            ],
        ]);

        $result = $this->validator->validate($payload);

        $this->assertFalse($result->valid);
        $this->assertContains(
            'minItems',
            array_map(fn ($e) => $e->keyword, $result->errors),
            'Empty files array must surface a "minItems" error.'
        );
    }

    #[Test]
    public function unknown_type_is_rejected(): void
    {
        $payload = json_encode([
            ['type' => 'dossier', 'uuid' => 'abc-123', 'title' => 'X'],
        ]);

        $result = $this->validator->validate($payload);

        $this->assertFalse($result->valid);
        // Both branches of the oneOf reject this on `type` (one
        // expects 'collection', the other 'record').
        $keywords = array_map(fn ($e) => $e->keyword, $result->errors);
        $this->assertTrue(
            in_array('const', $keywords, true) || in_array('oneOf', $keywords, true) || in_array('enum', $keywords, true),
            'Unknown type must produce a const/oneOf/enum-shaped failure.'
        );
    }

    #[Test]
    public function file_without_file_path_is_rejected(): void
    {
        $payload = json_encode([
            [
                'type' => 'record',
                'uuid' => 'abc-123',
                'title' => 'Sample',
                'files' => [
                    [
                        'uuid' => 'file-1',
                        'name' => 'foo.txt',
                        'mime_type' => 'text/plain',
                    ],
                ],
            ],
        ]);

        $result = $this->validator->validate($payload);

        $this->assertFalse($result->valid);
        $this->assertContains(
            'required',
            array_map(fn ($e) => $e->keyword, $result->errors),
        );
    }

    #[Test]
    public function file_without_mime_type_is_rejected(): void
    {
        $payload = json_encode([
            [
                'type' => 'record',
                'uuid' => 'abc-123',
                'title' => 'Sample',
                'files' => [
                    [
                        'uuid' => 'file-1',
                        'name' => 'foo.txt',
                        'file_path' => 'content/foo.txt',
                    ],
                ],
            ],
        ]);

        $result = $this->validator->validate($payload);

        $this->assertFalse($result->valid);
        $this->assertContains(
            'required',
            array_map(fn ($e) => $e->keyword, $result->errors),
        );
    }
}
