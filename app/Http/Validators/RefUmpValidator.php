<?php
// app/Http/Validators/RefUmpValidator.php

namespace App\Http\Validators;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Validator;

class RefUmpValidator
{
    /** Validasi request bulk save */
    public function validateBulkSave(array $data): array
    {
        $validator = Validator::make($data, [
            'tahun'                   => ['required', 'integer', 'min:2000', 'max:2100'],
            'rows'                    => ['required', 'array', 'min:1'],
            'rows.*.id_provinsi'      => ['required', 'integer', 'min:1'],
            'rows.*.nilai_ump'        => ['nullable', 'integer', 'min:0'],
            'rows.*.sumber'           => ['nullable', 'string', 'in:BPS_API,IMPORT,MANUAL,GAGAL,KOSONG'],
        ]);

        if ($validator->fails()) {
            throw new BusinessException($validator->errors()->first(), 422);
        }

        return $validator->validated();
    }

    /** Validasi request edit satu baris */
    public function validateUpdateSingle(array $data): array
    {
        $validator = Validator::make($data, [
            'nilai_ump' => ['required', 'integer', 'min:500000', 'max:50000000'],
        ], [
            'nilai_ump.min' => 'Nilai UMP minimal Rp 500.000.',
            'nilai_ump.max' => 'Nilai UMP maksimal Rp 50.000.000.',
        ]);

        if ($validator->fails()) {
            throw new BusinessException($validator->errors()->first(), 422);
        }

        return $validator->validated();
    }

    /** Validasi file import */
    public function validateImportFile(array $data): void
    {
        $validator = Validator::make($data, [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'], // max 5MB
        ], [
            'file.mimes' => 'File harus berformat xlsx, xls, atau csv.',
            'file.max'   => 'Ukuran file maksimal 5MB.',
        ]);

        if ($validator->fails()) {
            throw new BusinessException($validator->errors()->first(), 422);
        }
    }

    /** Validasi parameter tahun di query/route */
    public function validateTahun(mixed $tahun): int
    {
        $validator = Validator::make(
            ['tahun' => $tahun],
            ['tahun' => ['required', 'integer', 'min:2000', 'max:2100']],
        );

        if ($validator->fails()) {
            throw new BusinessException('Parameter tahun tidak valid. Gunakan tahun 4 digit (2000–2100).', 422);
        }

        return (int) $tahun;
    }
}