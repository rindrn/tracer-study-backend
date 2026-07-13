<?php

namespace App\Repositories\Config;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber public.kpi_category_mapping (koneksi olap -- sama fisik Postgres
 * yang dibaca Cube.js, lihat file header 002_semantic_mapping_schema.sql).
 * Gaya query builder murni, mengikuti konvensi OlapLoadRepository (tidak
 * ada Eloquent di sisi olap).
 */
class KpiCategoryMappingRepository
{
    private function olap(): \Illuminate\Database\Connection
    {
        return DB::connection('olap');
    }

    /**
     * $isActive null = TIDAK difilter (aktif + nonaktif sekaligus) -- WAJIB
     * dipakai tab audit "Data Tersimpan" di FE, supaya baris yang dinonaktifkan
     * tetap terlihat (forward-only artinya baris tidak pernah hilang dari
     * database, tapi kalau API selalu memfilter is_active=true, efeknya SAMA
     * SAJA dengan hilang dari sudut pandang admin -- bug yang sempat lolos
     * sebelum ini diperbaiki).
     */
    public function list(?string $semanticRole, ?string $digunakanOleh, ?bool $isActive = true): Collection
    {
        $q = $this->olap()->table('kpi_category_mapping');

        if ($isActive !== null) {
            $q->where('is_active', $isActive);
        }
        if ($semanticRole !== null) {
            $q->where('semantic_role', $semanticRole);
        }
        if ($digunakanOleh !== null) {
            $q->where('digunakan_oleh', $digunakanOleh);
        }

        return $q->orderBy('kpi_category')->orderBy('id')->get();
    }

    /**
     * Semua (digunakan_oleh, kpi_category, kpi_category_label) yang PERNAH
     * ada untuk role ini -- aktif ATAU nonaktif. Beda dari list(): ini bukan
     * daftar baris, tapi daftar "grouping apa saja yang pernah dikonfigurasi"
     * (taksonomi), dipakai Langkah 2 UI supaya sebuah digunakan_oleh (mis.
     * iku2_keterserapan) TETAP terlihat sebagai section yang bisa dikelola
     * walau SEMUA baris aktifnya kebetulan sedang nonaktif (mis. baru saja
     * dinonaktifkan semua untuk demo ulang) -- tanpa ini, Langkah 2 tidak
     * pernah tahu grouping itu pernah ada sama sekali, dead-end "hubungi tim
     * teknis" padahal cukup dipetakan ulang lewat UI yang sama.
     */
    public function taxonomyForRole(string $semanticRole): Collection
    {
        return $this->olap()->table('kpi_category_mapping')
            ->where('semantic_role', $semanticRole)
            ->select(['digunakan_oleh', 'kpi_category', 'kpi_category_label'])
            ->distinct()
            ->orderBy('digunakan_oleh')
            ->orderBy('kpi_category')
            ->get();
    }

    public function find(int $id): ?object
    {
        return $this->olap()->table('kpi_category_mapping')->where('id', $id)->first();
    }

    /** Constraint unik (semantic_role, option_code, digunakan_oleh) WHERE is_active -- pre-check sebelum insert. */
    public function findActiveConflict(string $semanticRole, string $optionCode, string $digunakanOleh): ?object
    {
        return $this->olap()->table('kpi_category_mapping')
            ->where('semantic_role', $semanticRole)
            ->where('option_code', $optionCode)
            ->where('digunakan_oleh', $digunakanOleh)
            ->where('is_active', true)
            ->first();
    }

    public function insert(array $data): int
    {
        return $this->olap()->table('kpi_category_mapping')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function deactivate(int $id, ?int $userId): bool
    {
        return $this->olap()->table('kpi_category_mapping')->where('id', $id)->update([
            'is_active'      => false,
            'deactivated_at' => now(),
            'deactivated_by' => $userId,
            'updated_at'     => now(),
        ]) > 0;
    }

    /**
     * Baris FLAT (belum di-group) untuk endpoint formula tooltip -- caller
     * (KpiCategoryMappingService) yang meng-group by kpi_category + susun
     * array options. Sengaja TIDAK pakai array_agg() Postgres di sini:
     * hasil array_agg via query builder mentah kembali sebagai string
     * literal Postgres ("{a,b,c}") yang butuh parsing manual dan rawan
     * salah kalau option_label_snapshot suatu saat mengandung koma/kurung
     * kurawal -- grouping di PHP jauh lebih aman dan sama sederhananya.
     */
    public function formulaRows(string $semanticRole, string $digunakanOleh): Collection
    {
        return $this->olap()->table('kpi_category_mapping')
            ->where('semantic_role', $semanticRole)
            ->where('digunakan_oleh', $digunakanOleh)
            ->where('is_active', true)
            ->orderBy('kpi_category')
            ->orderBy('id')
            ->get();
    }

    /**
     * Label opsi (option_label_snapshot) untuk (role, digunakan_oleh, kategori)
     * tertentu -- dipakai KeterserapanService menggantikan STATUS_TERSERAP
     * hardcode. Urut by id supaya stabil antar-pemanggilan.
     */
    public function optionLabelsFor(string $semanticRole, string $digunakanOleh, string $kpiCategory): array
    {
        return $this->olap()->table('kpi_category_mapping')
            ->where('semantic_role', $semanticRole)
            ->where('digunakan_oleh', $digunakanOleh)
            ->where('kpi_category', $kpiCategory)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('option_label_snapshot')
            ->filter()
            ->values()
            ->all();
    }
}
