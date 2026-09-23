<?php

namespace App\Services\Analytical;

/**
 * ExplorerQueryBuilder
 *
 * Mengubah permintaan explorer yang SUDAH divalidasi OlapCatalog menjadi objek
 * query Cube.js. Murni: tidak ada HTTP, database, config, maupun session di
 * sini, sehingga seluruh aturan penggabungan filter bisa diuji langsung.
 *
 * Kenapa penggabungan filter aman: Cube.js meng-AND seluruh entri `filters`.
 * Artinya filter scope yang ditambahkan di sini hanya bisa MEMPERSEMPIT hasil,
 * tidak pernah memperluasnya — kaprodi yang mengirim filter prodi lain lewat
 * body tetap mendapat irisan kosong, bukan data prodi itu. Jadi urutan
 * penggabungan tidak menentukan keamanannya; yang menentukan adalah filter
 * scope selalu ikut terpasang. Itu yang diuji di ExplorerQueryBuilderTest.
 */
class ExplorerQueryBuilder
{
    /**
     * @param  array{cube: string, measures: array<string>, dimensions: array<string>, filters: array<array{member: string, values: array}>, limit?: int}  $request
     * @param  array<array{member: string, operator: string, values: array}>  $scopeFilters
     *         Hasil BaseAnalyticalRepository::buildGlobalFilters() — batas yang
     *         dipaksakan role pemanggil (kaprodi: nama_prodi + jenjang, kajur
     *         dan dekan: id_prodi_in).
     * @return array<string, mixed>  Objek query Cube.js siap kirim.
     */
    public function build(
        array $request,
        array $scopeFilters,
        int   $maxRows,
    ): array {
        $dimensions = $request['dimensions'] ?? [];

        $filters = $scopeFilters;

        foreach (($request['filters'] ?? []) as $filter) {
            // Nilai kosong berarti "tanpa penyaring" — dikirim apa adanya ke
            // Cube.js, `equals` dengan array kosong menghasilkan kondisi yang
            // tidak pernah benar dan seluruh hasil hilang tanpa penjelasan.
            if (empty($filter['values'])) {
                continue;
            }

            $filters[] = [
                'member'   => $filter['member'],
                'operator' => 'equals',
                'values'   => array_map('strval', $filter['values']),
            ];
        }

        // Tidak ada penyaring snapshot di sini, dan itu disengaja. Duplikasi
        // antar snapshot ETL sudah ditangani di lapisan Cube.js oleh cube
        // *Terkini yang dirujuk katalog (DISTINCT ON per alumni). Memasang
        // penyaring id_waktu di atasnya justru merusak: karena ETL berjalan
        // inkremental, membatasi ke satu snapshot membuang alumni yang tidak
        // ikut run itu. Lihat FactTracerStudyTerkini.js.
        $query = [
            'measures'   => array_values($request['measures']),
            'dimensions' => array_values($dimensions),
            'limit'      => $this->resolveLimit($request['limit'] ?? null, $maxRows),
        ];

        if ($filters !== []) {
            $query['filters'] = $filters;
        }

        // Urutan naik pada setiap dimensi supaya pivot di frontend stabil:
        // baris dan kolom muncul dengan urutan yang sama di setiap pemuatan,
        // dan perbandingan antar dua hasil tidak terganggu urutan acak.
        if ($dimensions !== []) {
            $query['order'] = array_map(fn ($d) => [$d, 'asc'], array_values($dimensions));
        }

        return $query;
    }

    private function resolveLimit(?int $requested, int $maxRows): int
    {
        if ($requested === null || $requested < 1) {
            return $maxRows;
        }

        return min($requested, $maxRows);
    }
}
