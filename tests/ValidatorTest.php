<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat\Tests;

use KraenzleRitter\AntonImportFormat\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Validator-API expectations independent of the schema content:
 * accepts three input shapes equivalently, returns errors as data,
 * stays robust against malformed inputs.
 */
final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function string_array_and_stdclass_inputs_yield_identical_results(): void
    {
        $jsonString = file_get_contents(__DIR__.'/Fixtures/agate-folder-input.metadata.json');
        $assocArray = json_decode($jsonString, true);
        $stdClass = json_decode($jsonString);

        $a = $this->validator->validate($jsonString);
        $b = $this->validator->validate($assocArray);
        $c = $this->validator->validate($stdClass);

        $this->assertSame($a->valid, $b->valid);
        $this->assertSame($a->valid, $c->valid);

        $this->assertSame(
            $this->errorTriples($a),
            $this->errorTriples($b),
            'string and array inputs must produce identical errors',
        );
        $this->assertSame(
            $this->errorTriples($a),
            $this->errorTriples($c),
            'string and stdClass inputs must produce identical errors',
        );
    }

    #[Test]
    public function broken_json_does_not_throw_and_reports_parse_error(): void
    {
        $result = $this->validator->validate('{not valid json');

        $this->assertFalse($result->valid);
        $this->assertCount(1, $result->errors);
        $this->assertSame('parse_error', $result->errors[0]->keyword);
        $this->assertSame('', $result->errors[0]->path);
        $this->assertNotEmpty($result->errors[0]->message);
    }

    #[Test]
    public function valid_payload_returns_empty_errors(): void
    {
        $result = $this->validator->validate(file_get_contents(__DIR__.'/Fixtures/agate-folder-input.metadata.json'));

        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
    }

    #[Test]
    public function composer_json_declares_no_framework_dependency(): void
    {
        $composer = json_decode(file_get_contents(__DIR__.'/../composer.json'), true);

        $require = $composer['require'] ?? [];

        $this->assertArrayNotHasKey('laravel/framework', $require);
        $this->assertArrayNotHasKey('illuminate/contracts', $require);
        $this->assertArrayNotHasKey('illuminate/support', $require);

        foreach (array_keys($require) as $pkg) {
            $this->assertStringStartsNotWith(
                'illuminate/',
                $pkg,
                'Package must remain framework-free; found illuminate/* dependency: '.$pkg
            );
        }
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function errorTriples(\KraenzleRitter\AntonImportFormat\ValidationResult $result): array
    {
        return array_map(
            fn ($e) => [$e->path, $e->keyword, $e->message],
            $result->errors,
        );
    }
}
