<?php

declare(strict_types=1);

namespace KraenzleRitter\AntonImportFormat\Tests;

use KraenzleRitter\AntonImportFormat\ValidationError;
use KraenzleRitter\AntonImportFormat\ValidationResult;
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
        $result = $this->validator->validate($json);
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
    }

    public function test_accepts_array_input(): void
    {
        $arr = ['version' => '0.1', 'tenant' => 'x', 'generator' => 'y', 'entries' => []];
        $this->assertTrue($this->validator->validate($arr)->valid);
    }

    public function test_accepts_stdclass_input(): void
    {
        $obj = json_decode('{"version":"0.1","tenant":"x","generator":"y","entries":[]}', false);
        $this->assertNotNull($obj);
        $this->assertTrue($this->validator->validate($obj)->valid);
    }

    public function test_returns_invalid_result_with_structured_errors_on_failure(): void
    {
        $result = $this->validator->validate(['tenant' => 'x']);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        foreach ($result->errors as $error) {
            $this->assertInstanceOf(ValidationError::class, $error);
            $this->assertNotSame('', $error->path);
            $this->assertNotSame('', $error->keyword);
            $this->assertNotSame('', $error->message);
        }
    }

    public function test_validation_error_serialises_to_array(): void
    {
        $result = $this->validator->validate(['tenant' => 'x']);
        $first = $result->errors[0];

        $arr = $first->toArray();
        $this->assertArrayHasKey('path', $arr);
        $this->assertArrayHasKey('keyword', $arr);
        $this->assertArrayHasKey('message', $arr);
    }

    public function test_validation_result_serialises_to_array(): void
    {
        $result = $this->validator->validate(['tenant' => 'x']);
        $arr = $result->toArray();

        $this->assertArrayHasKey('valid', $arr);
        $this->assertArrayHasKey('errors', $arr);
        $this->assertFalse($arr['valid']);
        $this->assertNotEmpty($arr['errors']);
        $this->assertArrayHasKey('path', $arr['errors'][0]);
    }

    public function test_does_not_throw_on_validation_failure(): void
    {
        $result = $this->validator->validate(['version' => 'invalid-version-pattern']);
        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
    }

    public function test_throws_jsonexception_on_malformed_string(): void
    {
        $this->expectException(\JsonException::class);
        $this->validator->validate('{not valid json');
    }

    public function test_version_warning_when_declared_does_not_match_loaded(): void
    {
        $result = $this->validator->validateWithVersionWarning([
            'version' => '0.99',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [],
        ]);

        $this->assertTrue($result->valid, 'Structural validation should pass; only the version warning is appended.');
        $this->assertCount(1, $result->errors);
        $this->assertSame('/version', $result->errors[0]->path);
        $this->assertSame('schema_version_mismatch', $result->errors[0]->keyword);
        $this->assertStringContainsString('0.99', $result->errors[0]->message);
    }

    public function test_no_version_warning_when_versions_match(): void
    {
        $result = $this->validator->validateWithVersionWarning([
            'version' => '0.1',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [],
        ]);

        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
    }
}
