<?php
// app/Services/Transactional/ThresholdService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ThresholdResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\ThresholdRepository;
use App\Repositories\Transactional\ThresholdIndicatorRepository;
use App\Repositories\Transactional\LamVersionRepository;
use App\Traits\WithCache;
use Illuminate\Support\Facades\DB;

class ThresholdService
{
    use WithCache;

    private const TTL      = 300;  // 5 menit — untuk data per-version/per-chart, sering berubah
    private const TTL_META = 1800; // 30 menit — metadata indikator, jarang berubah tapi tetap di-tag biar bisa di-invalidate

    public function __construct(
        private readonly ThresholdRepository          $repo,
        private readonly LamVersionRepository         $versionRepo,
        private readonly ThresholdIndicatorRepository  $indicatorRepo, // baru
    ) {}

    private const SLOVIN_MARGIN_ERROR = 0.023;

    public function bulkCreate(int $lamVersionId, array $validated): array
    {
        $version = $this->versionRepo->findById($lamVersionId);
        if (! $version) {
            throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);
        }

        $rows = $this->prepareBulkRows($version, $validated['thresholds']);
        $this->repo->bulkCreate($lamVersionId, $rows);
        $this->syncDynamicParams($lamVersionId, $validated['thresholds']);

        $result = $this->formatGroupedResponse($version, $this->repo->byVersion($lamVersionId));

        $this->forget("thresholds:by_version:{$lamVersionId}");
        $this->forgetTag('thresholds', 'lams');

        return $result;
    }

    public function bulkUpdate(int $lamVersionId, array $validated): array
    {
        $version = $this->versionRepo->findById($lamVersionId);
        if (! $version) {
            throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);
        }

        $rows = $this->prepareBulkUpdateRows($version, $validated['thresholds']);
        $this->repo->bulkUpdate($rows);
        $this->syncDynamicParams($lamVersionId, $validated['thresholds']);

        $result = $this->formatGroupedResponse($version, $this->repo->byVersion($lamVersionId));

        $this->forget("thresholds:by_version:{$lamVersionId}");
        $this->forgetTag('thresholds', 'lams');

        return $result;
    }

    // ── Helpers baru ──────────────────────────────────────────

    private function prepareBulkRows(object $version, array $thresholds): array
    {
        return collect($thresholds)->map(function ($row) {
            $indicator = $this->indicatorRepo->findById($row['indicator_id']);
            if (! $indicator) {
                throw new BusinessException("Indicator ID {$row['indicator_id']} tidak ditemukan.", 404);
            }

            if ($indicator->is_system_calculated) {
                throw new BusinessException("Indikator {$indicator->key} dihitung otomatis oleh sistem (formula Slovin) dan tidak bisa diisi manual.", 422);
            }

            return $row;
        })->toArray();
    }

    private function prepareBulkUpdateRows(object $version, array $thresholds): array
    {
        return collect($thresholds)->map(function ($row) {
            $indicator = $this->indicatorRepo->findById($row['indicator_id']);
            if (! $indicator) {
                throw new BusinessException("Indicator ID {$row['indicator_id']} tidak ditemukan.", 404);
            }

            if ($indicator->is_system_calculated) {
                throw new BusinessException("Indikator {$indicator->key} dihitung otomatis oleh sistem (formula Slovin) dan tidak bisa diisi manual.", 422);
            }

            return $row;
        })->toArray();
    }

    private function syncDynamicParams(int $lamVersionId, array $thresholds): void
    {
        foreach ($thresholds as $row) {
            if (array_key_exists('param_value', $row) && $row['param_value'] !== null) {
                $this->repo->upsertConfig($lamVersionId, (int) $row['indicator_id'], (float) $row['param_value']);
            }
        }
    }

   

    private function interpolateName(string $template, $paramValue): string
    {
        if ($paramValue === null || ! str_contains($template, '{value}')) {
            return $template;
        }
        $formatted = rtrim(rtrim(number_format((float) $paramValue, 2, '.', ''), '0'), '.');
        return str_replace('{value}', $formatted, $template);
    }

    // ── formatGroupedResponse & doForChart, versi update ──────

    private function formatGroupedResponse(object $version, \Illuminate\Support\Collection $rows): array
    {
        $grouped = $rows->groupBy('indicator_id')
            ->map(function ($items) {
                $first      = $items->first();
                $paramValue = $first->param_value ?? null;

                $result = [
                    'indicator_id'   => $first->indicator_id,
                    'indicator_key'  => $first->indicator_key,
                    'indicator_name' => $this->interpolateName($first->indicator_name, $paramValue),
                    'unit'           => $first->indicator_unit,
                    'operator'       => $first->indicator_operator,
                    'dynamic_param'  => $first->dynamic_param_unit
                        ? ['value' => $paramValue !== null ? (float) $paramValue : null, 'unit' => $first->dynamic_param_unit]
                        : null,
                    'is_system_calculated' => (bool) $first->is_system_calculated,
                ];

                foreach ($items as $item) {
                    $result[$item->threshold_level] = [
                        'threshold_id' => $item->threshold_id,
                        'value'        => (float) $item->threshold_value,
                    ];
                }
                return $result;
            })
            ->values()
            ->toArray();

        return [
            'lam'        => ['id' => $version->lam_id, 'name' => $version->lam_name],
            'version'    => ['id' => $version->id, 'year' => $version->year, 'year_end' => $version->year_end ?? null],
            'thresholds' => $grouped,
        ];
    }

    private function doForChart(?int $prodiId, string $indicatorKey): array
    {
        if ($indicatorKey === 'tracer_response') {
            return $this->doForChartTracerResponse($prodiId);
        }

        if (! $prodiId) {
            return [
                'context'   => 'all_prodi',
                'lam'       => null,
                'indicator' => $this->resolveIndicatorMeta($indicatorKey),
                'versions'  => [],
            ];
        }

        $result = $this->repo->byProdiAndIndicator($prodiId, $indicatorKey);

        if (! $result) {
            return [
                'context'   => 'prodi',
                'lam'       => null,
                'indicator' => $this->resolveIndicatorMeta($indicatorKey),
                'versions'  => [],
            ];
        }

        $versions = collect($result->rows)
            ->groupBy('version_id')
            ->map(function ($items) use ($result) {
                $first      = $items->first();
                $paramValue = $first->param_value ?? null;

                $thresholds = [];
                foreach ($items as $item) {
                    $thresholds[$item->threshold_level] = [
                        'threshold_id' => $item->threshold_id,
                        'value'        => (float) $item->threshold_value,
                    ];
                }

                $yearLabel = $first->year_end
                    ? "{$first->year}\u{2013}{$first->year_end}"
                    : "{$first->year}\u{2013}sekarang";

                return [
                    'id'             => $first->version_id,
                    'year'           => $first->year,
                    'year_end'       => $first->year_end ?? null,
                    'version_name'   => $first->version_name,
                    'label'          => $result->lam->lam_name . ' ' . $yearLabel,
                    'is_active'      => (bool) $first->is_active,
                    'indicator_name' => $this->interpolateName($first->indicator_name, $paramValue),
                    'thresholds'     => $thresholds,
                    'dynamic_param'  => $first->dynamic_param_unit
                        ? ['value' => $paramValue !== null ? (float) $paramValue : null, 'unit' => $first->dynamic_param_unit]
                        : null,
                ];
            })
            ->values()
            ->toArray();

        $firstRow = $result->rows->first();

        return [
            'context'   => 'prodi',
            'lam'       => [
                'id'   => $result->lam->lam_id,
                'name' => $result->lam->lam_name,
                'code' => $result->lam->lam_code,
            ],
            'indicator' => [
                'key'                  => $firstRow->indicator_key,
                'name'                 => $firstRow->indicator_name, // raw template
                'unit'                 => $firstRow->indicator_unit,
                'operator'             => $firstRow->indicator_operator,
                'dynamic_param_unit'   => $firstRow->dynamic_param_unit,
                'is_system_calculated' => (bool) $firstRow->is_system_calculated,
            ],
            'versions' => $versions,
        ];
    }

    private function resolveIndicatorMeta(string $key): array
    {
        return $this->remember("threshold_indicators:meta:{$key}", function () use ($key) {
            $row = DB::connection('oltp')->table('threshold_indicators')->where('key', $key)->first();

            if (! $row) {
                return ['key' => $key, 'name' => null, 'unit' => null, 'operator' => null, 'dynamic_param_unit' => null, 'is_system_calculated' => false];
            }

            return [
                'key'                  => $row->key,
                'name'                 => $row->name,
                'unit'                 => $row->unit,
                'operator'             => $row->operator,
                'dynamic_param_unit'   => $row->dynamic_param_unit,
                'is_system_calculated' => (bool) $row->is_system_calculated,
            ];
        }, self::TTL_META, ['thresholds']);
    }

    // GET /api/lams/{lamId}/thresholds/tracer-response — breakdown per prodi di bawah LAM ini
    public function tracerResponseByLam(int $lamId): array
    {
        $rows = $this->repo->tracerResponseHistoryByLam($lamId);

        return $rows->groupBy('program_id')->map(function ($items) {
            $first = $items->first();
            return [
                'program_id'   => $first->program_id,
                'program_name' => $first->program_name,
                'program_code' => $first->program_code,
                'history' => $items->map(fn($r) => [
                    'graduated_year'  => $r->graduated_year,
                    'threshold_value' => (float) $r->threshold_value,
                    'total_lulusan'   => $r->total_lulusan,
                    'min_responden'   => $r->min_responden,
                    'margin_error'    => (float) $r->margin_error,
                    'calculated_at'   => $r->calculated_at,
                ])->values(),
            ];
        })->values()->toArray();
    }

    private function doForChartTracerResponse(?int $prodiId): array
    {
        $indicatorMeta = $this->resolveIndicatorMeta('tracer_response');

        if (! $prodiId) {
            return ['context' => 'all_prodi', 'lam' => null, 'indicator' => $indicatorMeta, 'versions' => []];
        }

        $lamRow = DB::connection('oltp')
            ->table('lam_programs as lp')
            ->join('lams as l', 'l.id', '=', 'lp.lam_id')
            ->where('lp.program_id', $prodiId)
            ->select('l.id as lam_id', 'l.name as lam_name', 'l.code as lam_code')
            ->first();

        $latest = $this->repo->latestTracerResponseThreshold($prodiId);

        if (! $latest) {
            return [
                'context'   => 'prodi',
                'lam'       => $lamRow ? ['id' => $lamRow->lam_id, 'name' => $lamRow->lam_name, 'code' => $lamRow->lam_code] : null,
                'indicator' => $indicatorMeta,
                'versions'  => [],
            ];
        }

        return [
            'context'   => 'prodi',
            'lam'       => $lamRow ? ['id' => $lamRow->lam_id, 'name' => $lamRow->lam_name, 'code' => $lamRow->lam_code] : null,
            'indicator' => $indicatorMeta,
            'versions'  => [[
                'id'             => null, // tidak terikat lam_version
                'year'           => $latest->graduated_year,
                'version_name'   => "Angkatan {$latest->graduated_year}",
                'label'          => ($lamRow->lam_name ?? 'Prodi') . " — Angkatan {$latest->graduated_year}",
                'is_active'      => true,
                'indicator_name' => $indicatorMeta['name'],
                'thresholds'     => [
                    'baik'   => ['threshold_id' => null, 'value' => (float) $latest->threshold_value],
                    'unggul' => ['threshold_id' => null, 'value' => (float) $latest->threshold_value],
                ],
                'dynamic_param'  => null,
                'calculation_meta' => [
                    'graduated_year' => $latest->graduated_year,
                    'total_lulusan'  => $latest->total_lulusan,
                    'margin_error'   => (float) $latest->margin_error,
                    'min_responden'  => $latest->min_responden,
                    'formula'        => 'Slovin',
                ],
            ]],
        ];
    }
}