<?php

namespace Tests\Unit\Validators;

use App\Http\Validators\KpiCategoryMappingValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KpiCategoryMappingValidatorTest extends TestCase
{
    private function validator(): KpiCategoryMappingValidator
    {
        return new KpiCategoryMappingValidator();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'semantic_role'  => 'status_pekerjaan',
            'option_code'    => 'bekerja',
            'kpi_category'   => 'keterserapan',
            'digunakan_oleh' => 'dashboard_employment',
        ], $overrides);
    }

    public function test_field_wajib_yang_hilang_ditolak(): void
    {
        $payload = $this->validPayload();
        unset($payload['kpi_category']);

        $this->expectException(ValidationException::class);

        $this->validator()->validateStore($payload);
    }

    public function test_payload_lengkap_lolos(): void
    {
        $result = $this->validator()->validateStore($this->validPayload());

        $this->assertSame('keterserapan', $result['kpi_category']);
    }
}
