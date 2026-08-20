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
     * ID unit itu sendiri + seluruh keturunannya di bawah pohon (DFR-14),
     * dipakai RBAC generik untuk menentukan cakupan user level-menengah
     * (mis. Dekan) tanpa hardcode nama role. BFS iteratif, bukan recursive
     * CTE -- konsisten dengan OrgUnitService::assertNoCycle() yang memilih
     * pendekatan sama untuk skala pohon puluhan baris di proyek ini.
     *
     * @return int[]
     */
    public function descendantIds(int $rootId): array
    {
        $ids   = [$rootId];
        $queue = [$rootId];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            foreach ($this->children($currentId) as $child) {
                $childId = (int) $child->id;
                $ids[]   = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
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

    /**
     * Cari satu unit berdasarkan level + nama, TANPA memandang parent-nya
     * (berbeda dari findSibling() yang butuh $parentId eksak).
     *
     * Dipakai OrgUnitHierarchyResolverService (DFR-17) sebagai jalur
     * dual-mode: begitu programs.org_unit_id belum diisi (mis. POLBAN
     * sebelum backfill FK selesai), resolver jatuh kembali ke pencocokan
     * nama teks lama (`programs.jurusan`) -- pola yang sama dengan
     * EnforcesProdiScope dual-mode. Nama hanya unik per (type, parent),
     * bukan global, jadi ini best-effort: mengambil kecocokan pertama.
     * Cukup untuk template politeknik (1 level, root, nama sudah
     * terverifikasi identik ke org_units lewat OrgUnitBackfillSeederTest).
     */
    public function findFirstByTypeAndName(int $orgUnitTypeId, string $name): ?object
    {
        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('org_unit_type_id', $orgUnitTypeId)
            ->where('name', $name)
            ->first();
    }

    /** Seluruh org_units, dipakai membangun tree (DFR-08) di sisi service. */
    public function all(): Collection
    {
        return collect(
            DB::connection(self::CONN)->table(self::TABLE)->orderBy('name')->get()
        );
    }

    /** Total baris org_units -- dasar guard DFR-01 (template hanya dipilih sebelum ada data). */
    public function countAll(): int
    {
        return DB::connection(self::CONN)->table(self::TABLE)->count();
    }

    /** Seluruh unit pada satu level, dipakai wizard migrasi struktur (DFR-06). */
    public function unitsByTypeId(int $orgUnitTypeId): Collection
    {
        return collect(
            DB::connection(self::CONN)->table(self::TABLE)
                ->where('org_unit_type_id', $orgUnitTypeId)
                ->get()
        );
    }

    /**
     * Pindahkan seluruh anak $oldParentId supaya menjadi anak $newParentId
     * (bisa null = jadi root) -- dipakai wizard migrasi struktur (DFR-06)
     * saat menghapus level di tengah pohon: anak level yang dihapus
     * "naik" ke induk level yang dihapus, bukan ikut terhapus.
     */
    public function reparentChildrenTo(int $oldParentId, ?int $newParentId): void
    {
        DB::connection(self::CONN)->table(self::TABLE)
            ->where('parent_id', $oldParentId)
            ->update(['parent_id' => $newParentId, 'updated_at' => now()]);
    }

    /**
     * Pencarian unit organisasi (DFR-11) berdasarkan nama (ILIKE, cocok
     * sebagian) dan/atau level. Mengembalikan beserta label level supaya
     * frontend tidak perlu join terpisah.
     */
    public function search(?string $query, ?int $orgUnitTypeId): Collection
    {
        $builder = DB::connection(self::CONN)->table(self::TABLE . ' as ou')
            ->join('org_unit_types as t', 't.id', '=', 'ou.org_unit_type_id')
            ->select(['ou.*', 't.label as level_label', 't.level_index as level_index', 't.institution_type as institution_type']);

        if ($query !== null && trim($query) !== '') {
            $builder->where('ou.name', 'ILIKE', '%' . trim($query) . '%');
        }

        if ($orgUnitTypeId !== null) {
            $builder->where('ou.org_unit_type_id', $orgUnitTypeId);
        }

        return collect($builder->orderBy('t.level_index')->orderBy('ou.name')->get());
    }
}
