<?php

namespace Tests\Unit\Validators;

use App\Exceptions\BusinessException;
use App\Http\Validators\RefUmpValidator;
use Tests\TestCase;

class RefUmpValidatorTest extends TestCase
{
    private RefUmpValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new RefUmpValidator();
    }

    /** nilai_ump di bawah 500.000 harus ditolak, bukan lolos sebagai UMP valid. */
    public function test_nilai_ump_di_bawah_batas_minimum_ditolak(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Nilai UMP minimal Rp 500.000.');

        $this->validator->validateUpdateSingle(['nilai_ump' => 400000]);
    }

    /** nilai_ump di atas 50 juta harus ditolak (guard salah ketik/salah input operator). */
    public function test_nilai_ump_di_atas_batas_maksimum_ditolak(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Nilai UMP maksimal Rp 50.000.000.');

        $this->validator->validateUpdateSingle(['nilai_ump' => 60000000]);
    }

    /** nilai_ump dalam rentang wajar harus lolos dan dikembalikan apa adanya. */
    public function test_nilai_ump_dalam_rentang_valid_diterima(): void
    {
        $result = $this->validator->validateUpdateSingle(['nilai_ump' => 3500000]);

        $this->assertSame(3500000, $result['nilai_ump']);
    }

    /** sumber di luar daftar enum yang diizinkan harus ditolak saat bulk save. */
    public function test_bulk_save_menolak_sumber_di_luar_enum(): void
    {
        $this->expectException(BusinessException::class);

        $this->validator->validateBulkSave([
            'tahun' => 2026,
            'rows'  => [
                ['id_provinsi' => 1, 'nilai_ump' => 3000000, 'sumber' => 'NGARANG_SENDIRI'],
            ],
        ]);
    }

    /** bulk save dengan rows kosong harus ditolak (min:1), bukan diam-diam no-op. */
    public function test_bulk_save_menolak_rows_kosong(): void
    {
        $this->expectException(BusinessException::class);

        $this->validator->validateBulkSave(['tahun' => 2026, 'rows' => []]);
    }
}
