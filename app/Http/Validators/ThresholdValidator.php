<?php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ThresholdValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, [
            'lam_version_id' => ['required', 'integer', 'exists:oltp.lam_versions,id'],
            'indicator_id'   => [
                'required', 'integer',
                'exists:oltp.threshold_indicators,id',
            ],
            'level'          => ['required', 'string', 'in:baik,unggul'],
            'value'          => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    public function validateUpdate(array $data): array
    {
        return $this->validate($data, [
            'value' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function validate(array $data, array $rules): array
    {
        $v = Validator::make($data, $rules, [
            'lam_version_id.required' => 'Versi LAM wajib dipilih.',
            'lam_version_id.exists'   => 'Versi LAM tidak ditemukan.',
            'indicator_id.required'   => 'Indikator wajib dipilih.',
            'indicator_id.exists'     => 'Indikator tidak valid.',
            'level.required'          => 'Level wajib dipilih (baik/unggul).',
            'level.in'                => 'Level harus "baik" atau "unggul".',
            'value.required'          => 'Nilai threshold wajib diisi.',
            'value.numeric'           => 'Nilai harus berupa angka.',
            'value.max'               => 'Nilai maksimal 100 (persentase).',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }

    public function validateBulkStore(array $data): array
    {
        $v = Validator::make($data, [
            'thresholds'              => ['required', 'array', 'min:1'],
            'thresholds.*.indicator_id' => [
                'required', 'integer',
                'exists:oltp.threshold_indicators,id',
                'distinct',   // tidak boleh indicator_id duplikat dalam 1 request
            ],
            'thresholds.*.baik'       => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.*.unggul'     => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'thresholds.required'                    => 'Data threshold wajib diisi.',
            'thresholds.*.indicator_id.required'     => 'Indikator wajib dipilih.',
            'thresholds.*.indicator_id.exists'       => 'Indikator tidak valid.',
            'thresholds.*.indicator_id.distinct'     => 'Indikator tidak boleh duplikat.',
            'thresholds.*.baik.required'             => 'Nilai baik wajib diisi.',
            'thresholds.*.unggul.required'           => 'Nilai unggul wajib diisi.',
            'thresholds.*.baik.max'                  => 'Nilai baik maksimal 100.',
            'thresholds.*.unggul.max'                => 'Nilai unggul maksimal 100.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }

    public function validateBulkUpdate(array $data): array
    {
        // Untuk update, indicator_id tidak dikirim (sudah fix),
        // yang dikirim adalah threshold_id (baik) + threshold_id (unggul) + value masing-masing
        $v = Validator::make($data, [
            'thresholds'                   => ['required', 'array', 'min:1'],
            'thresholds.*.indicator_id'    => ['required', 'integer', 'exists:oltp.threshold_indicators,id'],
            'thresholds.*.baik_id'         => ['required', 'integer', 'exists:oltp.thresholds,id'],
            'thresholds.*.baik_value'      => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.*.unggul_id'       => ['required', 'integer', 'exists:oltp.thresholds,id'],
            'thresholds.*.unggul_value'    => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'thresholds.required'                 => 'Data threshold wajib diisi.',
            'thresholds.*.baik_id.exists'         => 'Threshold baik tidak ditemukan.',
            'thresholds.*.unggul_id.exists'       => 'Threshold unggul tidak ditemukan.',
            'thresholds.*.baik_value.max'         => 'Nilai baik maksimal 100.',
            'thresholds.*.unggul_value.max'       => 'Nilai unggul maksimal 100.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}