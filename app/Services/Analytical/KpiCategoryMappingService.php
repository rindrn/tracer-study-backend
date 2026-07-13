<?php

namespace App\Services\Analytical;

use App\Exceptions\BusinessException;
use App\Repositories\Config\KpiCategoryMappingRepository;
use App\Repositories\ETL\SemanticMappingRepository;
use Illuminate\Support\Collection;

/**
 * Orkestrasi admin API untuk public.kpi_category_mapping -- pengelompokan
 * option_code menjadi kategori KPI (terserap/tidak, sesuai/tidak_sesuai,
 * dst), dikonsumsi Cube.js (FactTracerStudy.js) dan KeterserapanService.
 */
class KpiCategoryMappingService
{
    public function __construct(
        private readonly KpiCategoryMappingRepository $repo,
        private readonly SemanticMappingRepository $semanticRepo,
    ) {}

    /** $isActive null = aktif + nonaktif sekaligus (dipakai tab audit "Data Tersimpan"). */
    public function list(?string $semanticRole, ?string $digunakanOleh, ?bool $isActive = true): Collection
    {
        return $this->repo->list($semanticRole, $digunakanOleh, $isActive);
    }

    /**
     * Semua grouping (digunakan_oleh) yang PERNAH ada untuk role ini, aktif
     * atau tidak -- lihat catatan lengkap di KpiCategoryMappingRepository::
     * taxonomyForRole(). Dipakai Langkah 2 UI supaya grouping yang SEMUA
     * baris aktifnya kebetulan nonaktif tetap muncul sebagai section yang
     * bisa dikelola, bukan hilang total dari tampilan.
     */
    public function taxonomy(string $semanticRole): array
    {
        return $this->repo->taxonomyForRole($semanticRole)
            ->groupBy('digunakan_oleh')
            ->map(fn (Collection $rows, string $usage) => [
                'digunakan_oleh' => $usage,
                'categories'     => $rows->map(fn ($r) => [
                    'id'    => $r->kpi_category,
                    'label' => $r->kpi_category_label,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @throws BusinessException 409 jika (semantic_role, option_code, digunakan_oleh)
     *         sudah aktif -- unique index akan gagal kalau tidak dicek dulu.
     * @throws BusinessException 422 kalau option_code BUKAN option_code asli dari
     *         questionnaire_options milik role ini (lihat SemanticMappingRepository::
     *         getOptionCandidatesForRole) -- kelas bug yang sudah pernah terjadi:
     *         option_code karangan (mis. slug dari label) tersimpan sukses di sini
     *         tapi tidak pernah match SPLIT_PART(id_status_alumni,':',3) yang dibaca
     *         Cube.js, sehingga kategorisasi gagal senyap. Endpoint option-candidates
     *         sudah menutup jalur ini di FE resmi, tapi validasi tetap harus ada di
     *         sini juga -- FE hanya satu dari kemungkinan banyak client API ini.
     */
    public function store(array $data, ?int $userId): array
    {
        $validCodes = $this->semanticRepo->getOptionCandidatesForRole($data['semantic_role'])
            ->pluck('option_code');

        if (!$validCodes->contains($data['option_code'])) {
            throw BusinessException::withPayload(
                "option_code '{$data['option_code']}' bukan kode opsi asli untuk role '{$data['semantic_role']}' -- tidak ditemukan di questionnaire_options milik kode aktif role ini.",
                422,
                ['error' => 'invalid_option_code', 'valid_option_codes' => $validCodes->values()->all()]
            );
        }

        $conflict = $this->repo->findActiveConflict($data['semantic_role'], $data['option_code'], $data['digunakan_oleh']);

        if ($conflict !== null) {
            throw BusinessException::withPayload(
                "Kombinasi (semantic_role={$data['semantic_role']}, option_code={$data['option_code']}, digunakan_oleh={$data['digunakan_oleh']}) sudah aktif.",
                409,
                ['error' => 'kpi_mapping_already_active', 'conflicting_mapping' => (array) $conflict]
            );
        }

        $newId = $this->repo->insert([
            'semantic_role'         => $data['semantic_role'],
            'option_code'           => $data['option_code'],
            'option_label_snapshot' => $data['option_label_snapshot'] ?? null,
            'kpi_category'          => $data['kpi_category'],
            'kpi_category_label'    => $data['kpi_category_label'] ?? null,
            'digunakan_oleh'        => $data['digunakan_oleh'],
            'effective_date'        => now()->toDateString(),
            'is_active'             => true,
            'mapped_by'             => $userId,
        ]);

        return (array) $this->repo->find($newId);
    }

    public function deactivate(int $id, ?int $userId): void
    {
        $row = $this->repo->find($id);
        if ($row === null) {
            throw new BusinessException('KPI category mapping tidak ditemukan.', 404);
        }

        $this->repo->deactivate($id, $userId);
    }

    /**
     * THE DYNAMIC TOOLTIP ENDPOINT -- lihat kontrak untuk contoh response
     * lengkap. group by kpi_category, options = daftar option_label_snapshot
     * terurut, hanya baris is_active.
     */
    public function formula(string $semanticRole, string $digunakanOleh): array
    {
        $rows = $this->repo->formulaRows($semanticRole, $digunakanOleh);

        $groups = $rows->groupBy('kpi_category')->map(function (Collection $items, string $kpiCategory) {
            return [
                'kpi_category'       => $kpiCategory,
                'kpi_category_label' => $items->first()->kpi_category_label,
                'options'            => $items->pluck('option_label_snapshot')->filter()->values()->all(),
            ];
        })->values()->all();

        return [
            'semantic_role'  => $semanticRole,
            'digunakan_oleh' => $digunakanOleh,
            'groups'         => $groups,
        ];
    }
}
