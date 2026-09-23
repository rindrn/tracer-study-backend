<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

/**
 * ExplorerRepository
 *
 * Jalur data OLAP Explorer ke Cube.js. Berbeda dari repository analitik lain
 * di folder ini, measure dan dimension TIDAK ditentukan di sini — keduanya
 * datang dari pengguna, sudah divalidasi OlapCatalog dan sudah disusun jadi
 * objek query oleh ExplorerQueryBuilder. Repository ini hanya menyediakan tiga
 * hal yang butuh menyentuh Cube.js: filter scope, snapshot terbaru, dan
 * eksekusi query.
 *
 * Catatan performa: query yang disusun bebas hampir pasti meleset dari
 * pre_aggregations yang didefinisikan di FactTracerStudy.js (rollup-nya
 * dirancang untuk kombinasi dimensi tertentu), jadi sebagian besar permintaan
 * di sini jatuh ke PostgreSQL langsung.
 */
class ExplorerRepository extends BaseAnalyticalRepository
{
    /**
     * Filter batas cakupan role pemanggil.
     *
     * Memakai helper yang sama dengan seluruh dashboard existing supaya aturan
     * scope kaprodi/kajur/dekan tidak punya dua implementasi yang bisa
     * berbeda diam-diam.
     *
     * `tahun_lulus` dan `minggu_snapshot` sengaja dibuang dari params sebelum
     * diteruskan: di explorer, tahun lulus adalah filter biasa yang disusun
     * pengguna lewat body (dan bisa multi-nilai, sedangkan helper ini hanya
     * menerima satu nilai), sementara duplikasi antar snapshot sudah ditangani
     * cube *Terkini di lapisan Cube.js -- lihat FactTracerStudyTerkini.js.
     *
     * @param  array<string, mixed>  $scopedParams  hasil EnforcesProdiScope::scopedParams()
     * @return array<array{member: string, operator: string, values: array}>
     */
    public function scopeFilters(array $scopedParams): array
    {
        return $this->buildGlobalFiltersFromArray([
            'jenjang'     => $scopedParams['jenjang']     ?? null,
            'jurusan'     => $scopedParams['jurusan']     ?? null,
            'nama_prodi'  => $scopedParams['nama_prodi']  ?? null,
            'id_prodi_in' => $scopedParams['id_prodi_in'] ?? null,
        ]);
    }

    /**
     * Jalankan query explorer apa adanya.
     *
     * @param  array<string, mixed>  $cubeQuery
     * @return Collection<int, array<string, mixed>>
     */
    public function run(array $cubeQuery): Collection
    {
        return $this->cube->load($cubeQuery);
    }

    /**
     * Nilai unik satu dimensi, untuk mengisi dropdown filter.
     *
     * Filter scope ikut dipasang supaya daftar pilihannya sendiri tidak
     * membocorkan apa pun: kaprodi tidak boleh melihat nama prodi lain di
     * dropdown, meskipun ia tidak akan bisa mengambil datanya.
     *
     * @param  array<array{member: string, operator: string, values: array}>  $scopeFilters
     * @return array<string>
     */
    public function dimensionValues(string $dimension, array $scopeFilters, int $limit = 500): array
    {
        $query = [
            'dimensions' => [$dimension],
            'order'      => [[$dimension, 'asc']],
            'limit'      => $limit,
        ];

        if ($scopeFilters !== []) {
            $query['filters'] = $scopeFilters;
        }

        return $this->cube->load($query)
            ->pluck($dimension)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->toArray();
    }
}
