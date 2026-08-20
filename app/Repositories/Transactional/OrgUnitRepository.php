<?php
// app/Repositories/Transactional/OrgUnitRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * OrgUnitRepository — pohon unit organisasi sungguhan (`org_units`).
 *
 * Baca-tulis mentah saja, query builder (bukan Eloquent) mengikuti pola
 * JurusanRepository. Validasi bisnis (anti-siklus, guard template) ada di
 * OrgUnitService -- repository ini sengaja tidak tahu aturan itu.
 */
class OrgUnitRepository
{
    private const CONN  = 'oltp';
    private const TABLE = 'org_units';

    public function find(int $id): ?object
    {
        return DB::connection(self::CONN)->table(self::TABLE)->where('id', $id)->first();
    }

    public function findSibling(int $orgUnitTypeId, ?int $parentId, string $name): ?object
    {
        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('org_unit_type_id', $orgUnitTypeId)
            ->where('name', $name)
            ->when(
                $parentId === null,
                fn ($q) => $q->whereNull('parent_id'),
                fn ($q) => $q->where('parent_id', $parentId),
            )
            ->first();
    }

    public function children(int $parentId): Collection
    {
        return collect(
            DB::connection(self::CONN)->table(self::TABLE)
                ->where('parent_id', $parentId)
                ->orderBy('name')
                ->get()
        );
    }

    public function insert(array $attributes): int
    {
        $now = now();

        return DB::connection(self::CONN)->table(self::TABLE)->insertGetId(
            $attributes + ['is_active' => $attributes['is_active'] ?? true, 'created_at' => $now, 'updated_at' => $now]
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

    public function childCount(int $id): int
    {
        return DB::connection(self::CONN)->table(self::TABLE)->where('parent_id', $id)->count();
    }

    /**
     * Nama seluruh unit milik satu org_unit_type, dipakai backfill DFR-24
     * untuk mengecek unit yang sudah pernah dibuat (idempoten).
     *
     * @return string[]
     */
    public function namesByType(int $orgUnitTypeId): array
    {
        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('org_unit_type_id', $orgUnitTypeId)
            ->pluck('name')
            ->all();
    }
}
