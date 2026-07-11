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

    private const TTL      = 1800;  // 30 menit — untuk data per-version/per-chart
    private const TTL_META = 3600; // 1 jam  — untuk metadata indikator (jarang berubah)

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
        return collect($thresholds)->map(function ($row) use ($version) {
            $indicator = $this->indicatorRepo->findById($row['indicator_id']);
            if (! $indicator) {
                throw new BusinessException("Indicator ID {$row['indicator_id']} tidak ditemukan.", 404);
            }

            if ($indicator->is_system_calculated) {
                $calc = $this->calculateTracerResponseValue($version);
                $row['baik']   = $calc['value'];
                $row['unggul'] = $calc['value'];
            } elseif (! isset($row['baik']) || ! isset($row['unggul'])) {
                throw new BusinessException("Nilai 'baik' dan 'unggul' wajib diisi untuk indikator {$indicator->key}.", 422);
            }

            return $row;
        })->toArray();
    }

    private function prepareBulkUpdateRows(object $version, array $thresholds): array
    {
        return collect($thresholds)->map(function ($row) use ($version) {
            $indicator = $this->indicatorRepo->findById($row['indicator_id']);
            if (! $indicator) {
                throw new BusinessException("Indicator ID {$row['indicator_id']} tidak ditemukan.", 404);
            }

            if ($indicator->is_system_calculated) {
                $calc = $this->calculateTracerResponseValue($version);
                $row['baik_value']   = $calc['value'];
                $row['unggul_value'] = $calc['value'];
            } elseif (! isset($row['baik_value']) || ! isset($row['unggul_value'])) {
                throw new BusinessException("Nilai 'baik_value' dan 'unggul_value' wajib diisi untuk indikator {$indicator->key}.", 422);
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

    private function calculateTracerResponseValue(object $version): array
    {
        $totalLulusan = DB::connection('oltp')
            ->table('alumni_profiles as ap')
            ->join('lam_programs as lp', 'lp.program_id', '=', 'ap.program_id')
            ->where('lp.lam_id', $version->lam_id)
            ->where('ap.graduation_year', $version->year)
            ->count();

        if ($totalLulusan === 0) {
            throw new BusinessException(
                "Tidak ada data lulusan tahun {$version->year} untuk menghitung threshold tracer_response secara otomatis.",
                422
            );
        }

        $d            = self::SLOVIN_MARGIN_ERROR;
        $minResponden = $totalLulusan / ($totalLulusan * ($d ** 2) + 1);
        $percentage   = round(($minResponden / $totalLulusan) * 100, 2);

        return [
            'value' => $percentage,
            'meta'  => [
                'total_lulusan' => $totalLulusan,
                'margin_error'  => $d,
                'min_responden' => (int) round($minResponden),
                'formula'       => 'Slovin',
            ],
        ];
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
        $tracerCalc = null;

        $grouped = $rows->groupBy('indicator_id')
            ->map(function ($items) use ($version, &$tracerCalc) {
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

                if ($first->is_system_calculated) {
                    $tracerCalc ??= $this->calculateTracerResponseValue($version);
                    $result['calculation_meta'] = $tracerCalc['meta'];
                }

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
            'version'    => ['id' => $version->id,     'year' => $version->year],
            'thresholds' => $grouped,
        ];
    }

    private function doForChart(?int $prodiId, string $indicatorKey): array
    {
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

                $version = [
                    'id'             => $first->version_id,
                    'year'           => $first->year,
                    'version_name'   => $first->version_name,
                    'label'          => $result->lam->lam_name . ' ' . $first->year,
                    'is_active'      => (bool) $first->is_active,
                    'indicator_name' => $this->interpolateName($first->indicator_name, $paramValue),
                    'thresholds'     => $thresholds,
                    'dynamic_param'  => $first->dynamic_param_unit
                        ? ['value' => $paramValue !== null ? (float) $paramValue : null, 'unit' => $first->dynamic_param_unit]
                        : null,
                ];

                if ($first->is_system_calculated) {
                    $version['calculation_meta'] = $this->calculateTracerResponseValue(
                        (object) ['lam_id' => $result->lam->lam_id, 'year' => $first->year]
                    )['meta'];
                }

                return $version;
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
        }, self::TTL_META);
    }
}