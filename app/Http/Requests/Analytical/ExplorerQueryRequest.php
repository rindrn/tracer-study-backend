<?php

namespace App\Http\Requests\Analytical;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ExplorerQueryRequest
 *
 * Hanya memeriksa BENTUK permintaan (tipe, kehadiran, batas jumlah kasar).
 * Apakah measure dan dimensinya benar-benar ada dan boleh dipasangkan pada
 * cube terpilih diperiksa OlapCatalog, karena aturan itu hidup di katalog,
 * bukan di daftar rule validasi.
 */
class ExplorerQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditangani middleware auth:sanctum pada grup route, dan
        // batas cakupan data ditangani EnforcesProdiScope di controller.
        return true;
    }

    public function rules(): array
    {
        $limits = config('olap_catalog.limits');

        return [
            'cube'       => ['required', 'string', 'max:100'],

            'measures'   => ['required', 'array', 'min:1', 'max:' . $limits['max_measures']],
            'measures.*' => ['required', 'string', 'max:150'],

            'dimensions'   => ['nullable', 'array', 'max:' . $limits['max_dimensions']],
            'dimensions.*' => ['required', 'string', 'max:150'],

            'filters'            => ['nullable', 'array', 'max:20'],
            'filters.*.member'   => ['required', 'string', 'max:150'],
            'filters.*.values'   => ['required', 'array', 'max:200'],
            'filters.*.values.*' => ['required', 'string', 'max:255'],

            'limit' => ['nullable', 'integer', 'min:1', 'max:' . $limits['max_rows']],
        ];
    }

    public function messages(): array
    {
        $limits = config('olap_catalog.limits');

        return [
            'measures.required' => 'Pilih minimal satu measure.',
            'measures.min'      => 'Pilih minimal satu measure.',
            'measures.max'      => "Maksimal {$limits['max_measures']} measure per query.",
            'dimensions.max'    => "Maksimal {$limits['max_dimensions']} dimensi per query.",
        ];
    }

    /**
     * Permintaan yang sudah bersih untuk ExplorerService.
     *
     * @return array{cube: string, measures: array<string>, dimensions: array<string>, filters: array, limit: int|null}
     */
    public function explorerPayload(): array
    {
        return [
            'cube'       => $this->input('cube'),
            'measures'   => $this->input('measures', []),
            'dimensions' => $this->input('dimensions', []),
            'filters'    => $this->input('filters', []),
            'limit'      => $this->input('limit'),
        ];
    }
}
