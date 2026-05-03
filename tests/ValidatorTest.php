<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat\Tests;

use KraenzleRitter\AntonImportFormat\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function test_accepts_string_input(): void
    {
        $json = '{"version":"0.1","tenant":"x","generator":"y","entries":[]}';
        $this->assertSame([], $this->validator->validate($json));
    }

    public function test_accepts_array_input(): void
    {
        $arr = ['version' => '0.1', 'tenant' => 'x', 'generator' => 'y', 'entries' => []];
        $this->assertSame([], $this->validator->validate($arr));
    }

    public function test_accepts_stdclass_input(): void
    {
        $obj = json_decode('{"version":"0.1","tenant":"x","generator":"y","entries":[]}', false);
        $this->assertNotNull($obj);
        $this->assertSame([], $this->validator->validate($obj));
    }

    public function test_returns_structured_errors_on_failure(): void
    {
        $errors = $this->validator->validate(['tenant' => 'x']);
        $this->assertNotEmpty($errors);
        foreach ($errors as $error) {
            $this->assertArrayHasKey('path', $error);
            $this->assertArrayHasKey('keyword', $error);
            $this->assertArrayHasKey('message', $error);
            $this->assertIsString($error['path']);
            $this->assertIsString($error['keyword']);
            $this->assertIsString($error['message']);
        }
    }

    public function test_does_not_throw_on_validation_failure(): void
    {
        $errors = $this->validator->validate(['version' => 'invalid-version-pattern']);
        $this->assertNotEmpty($errors);
    }

    public function test_throws_jsonexception_on_malformed_string(): void
    {
        $this->expectException(\JsonException::class);
        $this->validator->validate('{not valid json');
    }

    public function test_version_warning_when_declared_does_not_match_loaded(): void
    {
        $errors = $this->validator->validateWithVersionWarning([
            'version' => '0.99',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [],
        ]);

        $this->assertCount(1, $errors);
        $this->assertSame('/version', $errors[0]['path']);
        $this->assertSame('schema_version_mismatch', $errors[0]['keyword']);
        $this->assertStringContainsString('0.99', $errors[0]['message']);
    }

    public function test_no_version_warning_when_versions_match(): void
    {
        $errors = $this->validator->validateWithVersionWarning([
            'version' => '0.1',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [],
        ]);

        $this->assertSame([], $errors);
    }
}
