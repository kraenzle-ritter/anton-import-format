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
            'version' => '0.4',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [],
        ]);

        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
    }

    /**
     * v0.4 — inline authority specs may carry a portable `uuid` and
     * `match_by: "uuid"` for stable-identity round-trips. Additive/optional.
     */
    public function test_inline_authority_with_uuid_is_valid(): void
    {
        $result = $this->validator->validate([
            'version' => '0.4',
            'tenant' => 'x',
            'generator' => 'anton-native@1.0',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '44444444-4444-4444-4444-444444444444',
                    'title' => ['de' => 'Mit Akteur-uuid'],
                    'events' => [
                        [
                            'type' => 'creation',
                            'actor' => [
                                'uuid' => '55555555-5555-5555-5555-555555555555',
                                'label' => ['de' => 'Meret Oppenheim'],
                                'match_by' => 'uuid',
                                'on_not_found' => 'create',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertTrue($result->valid, (string) json_encode($result->toArray()));
    }

    /**
     * v0.3 round-trip fidelity fields — all additive and optional, so a
     * document without them stays valid and a document with them validates.
     */
    public function test_entry_with_roundtrip_fidelity_fields_is_valid(): void
    {
        $result = $this->validator->validate([
            'version' => '0.3',
            'tenant' => 'x',
            'generator' => 'anton-native@1.0',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'title' => ['de' => 'Mit Fidelity'],
                    'formset_id' => 12,
                    'term_values' => [
                        ['antonfield' => 'reproduction_conditions', 'term_id' => 7],
                        ['antonfield' => 'reproduction_conditions', 'taxonomy' => 'repro', 'name' => 'cc-by'],
                    ],
                    '_raw' => ['location' => 'POINT(46.9480 7.4474)'],
                    'files' => [
                        [
                            'name' => 'scan.jpg',
                            'mime_type' => 'image/jpeg',
                            'md5sum' => 'd41d8cd98f00b204e9800998ecf8427e',
                            'collection_name' => 'image',
                            'file_name' => 'scan.jpg',
                            'generated_conversions' => ['web' => ['width' => 800, 'height' => 600], 'thumb' => true],
                            'av_duration_seconds' => 0,
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertTrue($result->valid, (string) json_encode($result->toArray()));
    }

    public function test_inline_authority_notes_are_valid(): void
    {
        $result = $this->validator->validate([
            'version' => '0.3',
            'tenant' => 'x',
            'generator' => 'anton-native@1.0',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '22222222-2222-2222-2222-222222222222',
                    'title' => ['de' => 'Mit Akteur-Bio'],
                    'events' => [
                        [
                            'type' => 'creation',
                            'actor' => [
                                'label' => ['de' => 'Meret Oppenheim'],
                                'type' => 'person',
                                'notes' => [
                                    ['type' => 'bioghist', 'locale' => 'de', 'text' => 'Schweizer Künstlerin.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertTrue($result->valid, (string) json_encode($result->toArray()));
    }

    public function test_term_value_without_term_reference_fails(): void
    {
        $result = $this->validator->validate([
            'version' => '0.3',
            'tenant' => 'x',
            'generator' => 'anton-native@1.0',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '33333333-3333-3333-3333-333333333333',
                    'title' => ['de' => 'Kaputter Termwert'],
                    'term_values' => [
                        ['antonfield' => 'reproduction_conditions'],
                    ],
                ],
            ],
        ]);
        $this->assertFalse($result->valid, 'term_value needs term_id or name');
    }

    /**
     * Note.tracks — optional time-stamped track entries (movie_content /
     * audio_content notes). Pre-tracks Notes remain valid; tracks-bearing
     * Notes must validate too. Malformed track items fail.
     */
    public function test_note_without_tracks_is_valid(): void
    {
        $result = $this->validator->validate([
            'version' => '0.2',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'title' => ['de' => 'Trackless record'],
                    'notes' => [
                        ['type' => 'general_note', 'locale' => 'de', 'text' => 'plain text only'],
                    ],
                ],
            ],
        ]);
        $this->assertTrue($result->valid, (string) json_encode($result->toArray()));
    }

    public function test_note_with_tracks_is_valid(): void
    {
        $result = $this->validator->validate([
            'version' => '0.2',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'title' => ['de' => 'Filmclip'],
                    'notes' => [
                        [
                            'type' => 'movie_content',
                            'locale' => 'de',
                            'text' => 'Vollständige Beschreibung',
                            'tracks' => [
                                ['timestamp' => '00:00:05', 'content' => 'Anfangsszene'],
                                ['timestamp' => '00:01:23.500', 'content' => 'Schnitt mit Reflexion'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertTrue($result->valid, (string) json_encode($result->toArray()));
    }

    public function test_note_with_malformed_track_item_fails(): void
    {
        $result = $this->validator->validate([
            'version' => '0.2',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'title' => ['de' => 'Bad track'],
                    'notes' => [
                        [
                            'type' => 'movie_content',
                            'locale' => 'de',
                            'text' => 'OK',
                            'tracks' => [
                                ['timestamp' => '00:00:05'], // missing required `content`
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
    }

    public function test_note_with_empty_track_string_fails(): void
    {
        $result = $this->validator->validate([
            'version' => '0.2',
            'tenant' => 'x',
            'generator' => 'y',
            'entries' => [
                [
                    'type' => 'record',
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'title' => ['de' => 'Empty track string'],
                    'notes' => [
                        [
                            'type' => 'movie_content',
                            'locale' => 'de',
                            'text' => 'OK',
                            'tracks' => [
                                ['timestamp' => '', 'content' => 'irgendwas'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertFalse($result->valid);
    }
}
