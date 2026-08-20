<?php

namespace App\Services\ETL;

use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;

/**
 * OrgUnitHierarchyResolverService — DFR-17/DFR-18.
 *
 * Untuk satu program studi, menelusuri pohon `org_units` (adjacency list)
 * dari daun ke akar dan mengembalikan hasilnya sebagai kolom bertingkat
 * tetap `level_1_name` .. `level_5_name` (bukan JSONB -- lihat catatan
 * DFR-18 di cetak-biru-struktur-dinamis.md: Cube.js pre-aggregation butuh
 * dimension bertipe tetap).
 *
 * `level_index` di `org_unit_types` dipetakan LANGSUNG ke `level_N_name`
 * (level_index 1 -> level_1_name, dst) -- tidak ada terjemahan lain,
 * supaya makna kolom identik dengan urutan yang sudah dipakai DFR-03.
 * Level yang tidak dipakai institusi (mis. level 2-5 pada template
 * Politeknik yang cuma 1 level) dibiarkan NULL.
 *
 * Iteratif (walk-up sederhana), BUKAN recursive CTE -- konsisten dengan
 * OrgUnitService::assertNoCycle()/OrgUnitRepository::descendantIds() yang
 * memilih pendekatan sama untuk skala pohon puluhan baris di proyek ini.
 *
 * ── Dual-mode (mengikuti pola EnforcesProdiScope, DFR-25) ──────────────
 *
 * Prodi idealnya menaut ke org_units lewat `programs.org_unit_id` (kolom
 * FK yang sudah ada sejak Fase 2, migration
 * 2026_08_20_000009_add_org_unit_id_to_users_and_programs). Tapi kolom itu
 * murni aditif dan TIDAK diisi retroaktif oleh migration manapun (strategi
 * expand-contract) -- untuk data POLBAN existing nilainya masih NULL di
 * seluruh baris `programs`. Karena itu resolver jatuh kembali ke
 * pencocokan nama teks lama (`programs.jurusan`) terhadap level dasar
 * ("unit akademik" -- level_index tertinggi/paling dalam) template
 * institusi aktif begitu `org_unit_id` kosong. Ini BUKAN jalur baru:
 * polanya sama persis dengan `EnforcesProdiScope::scopedParamsGenericUnit()`.
 */
class OrgUnitHierarchyResolverService
{
    private const MAX_DEPTH_GUARD = 20;
    private const MAX_LEVEL = 5;

    public function __construct(
        private readonly OrgUnitRepository $orgUnitRepo,
        private readonly OrgUnitTypeRepository $orgUnitTypeRepo,
    ) {}

    /**
     * @return array{level_1_name: ?string, level_2_name: ?string, level_3_name: ?string, level_4_name: ?string, level_5_name: ?string}
     */
    public function resolve(?int $orgUnitId, ?string $fallbackUnitName): array
    {
        $result = [
            'level_1_name' => null,
            'level_2_name' => null,
            'level_3_name' => null,
            'level_4_name' => null,
            'level_5_name' => null,
        ];

        $leaf = $this->resolveLeafUnit($orgUnitId, $fallbackUnitName);

        if ($leaf === null) {
            return $result;
        }

        $node = $leaf;
        $guard = 0;

        while ($node !== null) {
            if (++$guard > self::MAX_DEPTH_GUARD) {
                // Jaring pengaman kalau data pohon di luar sini korup
                // (bersiklus) -- pola sama dengan OrgUnitService.
                break;
            }

            $type = $this->orgUnitTypeRepo->find((int) $node->org_unit_type_id);

            if ($type !== null) {
                $levelIndex = (int) $type->level_index;

                if ($levelIndex >= 1 && $levelIndex <= self::MAX_LEVEL) {
                    $result["level_{$levelIndex}_name"] = $node->name;
                }
            }

            $node = $node->parent_id !== null ? $this->orgUnitRepo->find((int) $node->parent_id) : null;
        }

        return $result;
    }

    private function resolveLeafUnit(?int $orgUnitId, ?string $fallbackUnitName): ?object
    {
        if ($orgUnitId !== null) {
            $unit = $this->orgUnitRepo->find($orgUnitId);
            if ($unit !== null) {
                return $unit;
            }
            // FK menunjuk ke unit yang sudah tidak ada -- jatuh ke fallback
            // nama alih-alih membiarkan seluruh hierarki NULL diam-diam.
        }

        if ($fallbackUnitName === null || trim($fallbackUnitName) === '') {
            return null;
        }

        $institutionType = config('institution.structure_template', 'politeknik');
        $baseType = $this->orgUnitTypeRepo->byInstitutionType($institutionType)->sortByDesc('level_index')->first();

        if ($baseType === null) {
            return null;
        }

        return $this->orgUnitRepo->findFirstByTypeAndName((int) $baseType->id, $fallbackUnitName);
    }
}
