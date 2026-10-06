<?php

namespace Tests\Unit\Validators;

use App\Http\Validators\QuestionnaireValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuestionnaireValidatorTest extends TestCase
{
    private function validator(): QuestionnaireValidator
    {
        return new QuestionnaireValidator();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'  => 'Kuesioner Tracer 2026',
            'status' => 'draft',
            'sections' => [
                [
                    'title' => 'Section 1',
                    'questions' => [
                        ['question' => 'Apa pekerjaan Anda?', 'type' => 'short'],
                    ],
                ],
            ],
        ], $overrides);
    }

    /**
     * `program_id: null` adalah perintah eksplisit "berlaku untuk semua
     * prodi" (lihat komentar di QuestionnaireValidator::validate()) --
     * validated() bawaan Laravel MEMBUANG key bernilai null, jadi tanpa
     * override eksplisit perintah ini akan lenyap. Ini bug historis yang
     * sudah diperbaiki di kode; test ini menjaga supaya tidak regresi.
     */
    public function test_program_id_null_eksplisit_dipertahankan_bukan_dibuang(): void
    {
        $payload = $this->validPayload(['program_id' => null]);

        $result = $this->validator()->validateCreate($payload);

        $this->assertArrayHasKey('program_id', $result);
        $this->assertNull($result['program_id']);
    }

    public function test_program_id_yang_tidak_dikirim_sama_sekali_tidak_ikut_di_result(): void
    {
        $payload = $this->validPayload();
        unset($payload['program_id']);

        $result = $this->validator()->validateCreate($payload);

        $this->assertArrayNotHasKey('program_id', $result);
    }

    public function test_status_di_luar_enum_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateCreate($this->validPayload(['status' => 'dipublikasikan']));
    }

    public function test_tipe_pertanyaan_di_luar_daftar_yang_diizinkan_ditolak(): void
    {
        $payload = $this->validPayload();
        $payload['sections'][0]['questions'][0]['type'] = 'essay_bebas';

        $this->expectException(ValidationException::class);

        $this->validator()->validateCreate($payload);
    }

    public function test_sections_kosong_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateCreate($this->validPayload(['sections' => []]));
    }

    public function test_section_tanpa_pertanyaan_ditolak(): void
    {
        $payload = $this->validPayload();
        $payload['sections'][0]['questions'] = [];

        $this->expectException(ValidationException::class);

        $this->validator()->validateCreate($payload);
    }

    public function test_payload_valid_lolos_tanpa_exception(): void
    {
        $result = $this->validator()->validateCreate($this->validPayload());

        $this->assertSame('Kuesioner Tracer 2026', $result['title']);
    }
}
