<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat\Tests;

use KraenzleRitter\AntonImportFormat\SchemaLoader;
use KraenzleRitter\AntonImportFormat\Validator;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function test_schema_id_embeds_major_minor_version(): void
    {
        $schema = SchemaLoader::schemaArray();
        $this->assertArrayHasKey('$id', $schema);
        $this->assertMatchesRegularExpression(
            '#^https://[^/]+/schemas/anton-import/\d+\.\d+/schema\.json$#',
            $schema['$id']
        );
    }

    public function test_schema_version_helper_returns_major_minor(): void
    {
        $this->assertSame('0.3', SchemaLoader::schemaVersion());
    }

    /**
     * @dataProvider validFixtureProvider
     */
    public function test_valid_fixture_passes(string $fixture): void
    {
        $result = $this->validator->validate($this->loadFixture('valid/' . $fixture));
        $this->assertTrue(
            $result->valid,
            sprintf(
                'Fixture %s expected valid, got errors: %s',
                $fixture,
                json_encode($result->toArray()['errors'])
            )
        );
        $this->assertSame([], $result->errors);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validFixtureProvider(): iterable
    {
        yield 'minimal' => ['minimal.json'];
        yield 'full' => ['full.json'];
        yield 'agate-target/folder-input' => ['../agate-target/folder-input.json'];
    }

    /**
     * @dataProvider brokenFixtureProvider
     */
    public function test_broken_fixture_fails_with_at_least_one_error(string $fixture): void
    {
        $result = $this->validator->validate($this->loadFixture('broken/' . $fixture));
        $this->assertFalse(
            $result->valid,
            sprintf('Broken fixture %s should produce a failed result', $fixture)
        );
        $this->assertNotEmpty($result->errors);
        foreach ($result->errors as $error) {
            $this->assertNotSame('', $error->path);
            $this->assertNotSame('', $error->keyword);
            $this->assertNotSame('', $error->message);
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function brokenFixtureProvider(): iterable
    {
        yield 'missing-version' => ['missing-version.json'];
        yield 'bare-string-title' => ['bare-string-title.json'];
        yield 'mixed-authority-form' => ['mixed-authority-form.json'];
        yield 'locale-639-2-in-title' => ['locale-639-2-in-title.json'];
        yield 'locale-639-1-in-languages' => ['locale-639-1-in-languages.json'];
        yield 'file-no-md5sum' => ['file-no-md5sum.json'];
        yield 'empty-parent-ref' => ['empty-parent-ref.json'];
        yield 'unknown-on-not-found' => ['unknown-on-not-found.json'];
    }

    private function loadFixture(string $relativePath): string
    {
        $path = __DIR__ . '/Fixtures/' . $relativePath;
        $contents = file_get_contents($path);
        $this->assertIsString($contents, "Fixture not readable: {$path}");

        return $contents;
    }
}
