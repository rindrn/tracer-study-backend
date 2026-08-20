<?php
// app/Repositories/Transactional/OrgUnitTypeRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * OrgUnitTypeRepository — katalog level hierarki (`org_unit_types`).
 *
 * Lihat migration create_org_unit_types_table untuk alasan satu tabel ini
 * memuat preset ketiga jenis institusi sekaligus.
 */
class OrgUnitTypeRepository
{
    private const CONN  = 'oltp';
    private const TABLE = 'org_unit_types';

    public function find(int $id): ?object
    {
        return DB::connection(self::CONN)->table(self::TABLE)->where('id', $id)->first();
    }

    public function findByInstitutionTypeAndLevel(string $institutionType, int $levelIndex): ?object
    {
        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('institution_type', $institutionType)
            ->where('level_index', $levelIndex)
            ->first();
    }

    /** Seluruh level milik satu template, terurut sesuai kedalaman (DFR-03). */
    public function byInstitutionType(string $institutionType): Collection
    {
        return collect(
            DB::connection(self::CONN)->table(self::TABLE)
                ->where('institution_type', $institutionType)
                ->orderBy('level_index')
                ->get()
        );
    }

    public function insert(array $attributes): int
    {
        $now = now();

        return DB::connection(self::CONN)->table(self::TABLE)->insertGetId(
            $attributes + ['created_at' => $now, 'updated_at' => $now]
        );
    }

    public function update(int $id, array $attributes): void
    {
        DB::connection(self::CONN)->table(self::TABLE)
            ->where('id', $id)
            ->update($attributes + ['updated_at' => now()]);
    }

    public function delete(int $id): void
    {
        DB::connection(self::CONN)->table(self::TABLE)->where('id', $id)->delete();
    }

    /** Berapa org_units yang memakai level ini -- dasar guard DFR-05. */
    public function orgUnitCount(int $orgUnitTypeId): int
    {
        return DB::connection(self::CONN)->table('org_units')
            ->where('org_unit_type_id', $orgUnitTypeId)
            ->count();
    }
}
