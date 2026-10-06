<?php

namespace Tests\Unit\Validators;

use App\Http\Validators\ThresholdValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Hanya validateUpdate() yang diuji di sini -- validateCreate()/
 * validateBulkStore()/validateBulkUpdate() punya rule `exists:oltp....`
 * yang butuh koneksi Postgres nyata (tidak tersedia di lingkungan unit
 * test ini). validateUpdate() murni numeric/min/max, aman diuji tanpa DB.
 */
class ThresholdValidatorTest extends TestCase
{
    private function validator(): ThresholdValidator
    {
        return new ThresholdValidator();
    }

    public function test_value_di_bawah_nol_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateUpdate(['value' => -5]);
    }

    public function test_value_di_atas_seratus_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateUpdate(['value' => 150]);
    }

    public function test_value_dalam_rentang_0_100_diterima(): void
    {
        $result = $this->validator()->validateUpdate(['value' => 85.5]);

        $this->assertSame(85.5, $result['value']);
    }

    public function test_value_non_numeric_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator()->validateUpdate(['value' => 'delapan puluh']);
    }
}
