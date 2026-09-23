<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

/**
 * ExplorerRepository
 *
 * Jalur Cube.js untuk halaman Insight (OLAP Explorer). Berbeda dari repository
 * lain yang query-nya tetap, di sini measure/dimensi disusun pengguna — tapi
 * yang sampai ke sini SUDAH divalidasi ExplorerService terhadap
 * config/olap_catalog.php, jadi repository tidak menebak apa pun.
 *
 * SNAPSHOT SELALU DIPATOK. fact_tracer_study adalah periodic snapshot: setiap
 * ETL run menyisipkan ulang satu baris per alumni (AlumniFactBuilderService).
 * Tanpa filter DimWaktu.id_waktu, "jumlah alumni" ikut terhitung sekali per
 * run dan angkanya berlipat. Pemanggil tidak perlu ingat ini — snapshot
 * dipasang di sini lewat buildGlobalFiltersFromArray().
 */
class ExplorerRepository extends BaseAnalyticalRepository
{
    /** Metadata model Cube (untuk ukuran & rentang yang dibangkitkan otomatis). */
    public function cubeMeta(): array
    {
        return $this->cube->meta();
    }

    /**
     * id_waktu snapshot terbaru yang benar-benar punya baris fakta. Diambil
     * lewat measure (bukan daftar DimWaktu saja) supaya run ETL yang gagal
     * atau kosong tidak terpilih dan membuat seluruh halaman tampak kosong.
     */
    public function latestSnapshotId(string $baseMeasure): ?string
    {
        $row = $this->cube->load([
            'measures'   => [$baseMeasure],
            'dimensions' => ['DimWaktu.id_waktu'],
            'order'      => [['DimWaktu.id_waktu', 'desc']],
            'limit'      => 1,
        ])->first();

        $id = $row['DimWaktu.id_waktu'] ?? null;

        return $id === null ? null : (string) $id;
    }

    /**
     * @param  string[]                                        $measures
     * @param  string[]                                        $dimensions
     * @param  array<int, array{member: string, values: string[]}> $filters
     * @param  array<string, mixed>                            $scopeParams  scope role + minggu_snapshot
     */
    public function query(
        array $measures,
        array $dimensions,
        array $filters,
        array $scopeParams,
        int   $limit,
    ): Collection {
        $query = [
            'measures'   => array_values($measures),
            'dimensions' => array_values($dimensions),
            'filters'    => $this->buildGlobalFiltersFromArray($scopeParams, $this->userFilters($filters)),
            'limit'      => $limit,
        ];

        // Urutan dimensi pertama supaya tabel dan sumbu X terbaca urut
        // (tahun lulus menaik, nama prodi alfabetis) tanpa diurutkan ulang di FE.
        if ($dimensions !== []) {
            $query['order'] = [[$dimensions[0], 'asc']];
        }

        return $this->cube->load($query);
    }

    /**
     * Nilai unik satu dimensi untuk isi dropdown filter — dalam cakupan role
     * dan snapshot yang sama dengan query, supaya kaprodi tidak melihat nama
     * prodi lain di pilihan filternya.
     *
     * @return string[]
     */
    public function distinctValues(string $baseMeasure, string $dimension, array $scopeParams): array
    {
        return $this->cube->load([
            'measures'   => [$baseMeasure],
            'dimensions' => [$dimension],
            'filters'    => $this->buildGlobalFiltersFromArray($scopeParams),
            'order'      => [[$dimension, 'asc']],
        ])
            // Bukan pluck(): nama member Cube.js mengandung titik, dan pluck
            // membacanya sebagai path bersarang — hasilnya selalu kosong.
            ->map(fn (array $r) => $r[$dimension] ?? null)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Daftar alumni di balik satu angka (drill-down).
     *
     * Satu baris per alumni: nama/NIM/prodi dijadikan dimensi, dan measure
     * yang diklik ikut dihitung per alumni. Menyaring measure itu sendiri
     * (`gt 0` untuk cacah, `set` untuk rata-rata/nilai) membuat daftar hanya
     * berisi alumni yang benar-benar ikut terhitung — klik "Jumlah alumni
     * terserap" tidak ikut menampilkan alumni yang belum terserap.
     *
     * Tidak di-cache dan tidak lewat pre-aggregation: data perorangan.
     *
     * Kolom daftar mengikuti `drill_dimensions` di katalog tiap sumber data:
     * status alumni hanya ada di fakta tracer study, tidak di fakta kompetensi.
     *
     * @param  array<int, array{member: string, operator: string, values: string[]}> $filters  saringan + nilai titik yang diklik
     * @param  string[] $drillDimensions
     * @return array<int, array<string, mixed>>
     */
    public function drillDown(
        string  $measure,
        bool    $isCount,
        array   $filters,
        array   $scopeParams,
        ?string $search,
        int     $page,
        int     $perPage,
        array   $drillDimensions,
    ): array {
        $extra = $this->userFilters($filters);

        $extra[] = $isCount
            ? ['member' => $measure, 'operator' => 'gt', 'values' => ['0']]
            : ['member' => $measure, 'operator' => 'set'];

        if ($search !== null && $search !== '') {
            $extra[] = ['or' => [
                ['member' => 'DimAlumni.nama', 'operator' => 'contains', 'values' => [$search]],
                ['member' => 'DimAlumni.nim',  'operator' => 'contains', 'values' => [$search]],
            ]];
        }

        return $this->cube->load([
            'measures'   => [$measure],
            'dimensions' => $drillDimensions,
            'filters' => $this->buildGlobalFiltersFromArray($scopeParams, $extra),
            'order'   => [['DimAlumni.nama', 'asc'], ['DimAlumni.id_alumni', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ])->map(fn (array $r) => [
            'nama'        => $r['DimAlumni.nama']        ?? '',
            'nim'         => $r['DimAlumni.nim']         ?? '',
            'nama_prodi'  => $r['DimProdi.nama_prodi']   ?? '',
            'jenjang'     => $r['DimProdi.jenjang']      ?? '',
            'tahun_lulus' => $r['DimAlumni.tahun_lulus'] ?? '',
            'status'      => $r['DimStatusAlumni.label'] ?? '',
            'nilai'       => $r[$measure]                ?? null,
        ])->values()->all();
    }

    /**
     * Saringan pengguna ke bentuk Cube: "sama dengan"/"bukan" membawa nilai,
     * "ada nilainya"/"kosong" tidak (Cube menolak values pada set/notSet).
     *
     * @return array<int, array<string, mixed>>
     */
    private function userFilters(array $filters): array
    {
        return array_map(function (array $f) {
            $operator = $f['operator'] ?? 'equals';

            return in_array($operator, ['set', 'notSet'], true)
                ? ['member' => $f['member'], 'operator' => $operator]
                : ['member' => $f['member'], 'operator' => $operator, 'values' => array_map('strval', $f['values'])];
        }, $filters);
    }
}
