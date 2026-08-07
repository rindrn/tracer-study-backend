<?php

namespace App\DTOs\Analytical\MasaTunggu;

/**
 * FR-027: prediksi tren median masa tunggu untuk periode berikutnya.
 *
 * {
 *   "filters": {},
 *   "historis": [{ "tahun_lulus": "2020", "median": 7.0, "count_alumni": 56, "is_prediksi": false }, ...],
 *   "prediksi": { "tahun_lulus": "2026", "median": 4.2, "is_prediksi": true },
 *   "metodologi": { "metode": "regresi_linier", "jumlah_titik_historis": 6, "slope": -0.6, "intercept": ... }
 * }
 */
class MasaTungguPrediksiDTO
{
    public function __construct(
        private readonly array $historis,
        private readonly ?array $prediksi,
        private readonly array $metodologi,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'    => $this->filters,
            'historis'   => $this->historis,
            'prediksi'   => $this->prediksi,
            'metodologi' => $this->metodologi,
        ];
    }
}
