<?php

namespace App\Repositories\Analytical;

use App\Services\CubeJsClient;

abstract class BaseAnalyticalRepository
{
    public function __construct(
        protected readonly CubeJsClient $cube,
    ) {}

    protected function buildGlobalFilters(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        array   $extra          = [],
        ?array  $idProdiIn      = null,
    ): array {
        $filters = [];

        if ($jenjang !== null && $jenjang !== '') {
            $filters[] = [
                'member'   => 'DimProdi.jenjang',
                'operator' => 'equals',
                'values'   => [$jenjang],
            ];
        }

        // Scope Kajur/Dekan: daftar id_prodi eksplisit (dari
        // User::scopedProgramIds(), lihat EnforcesProdiScope::scopedParams).
        // Menggantikan filter scalar `jurusan` di bawah -- teks itu bisa
        // drift dari keanggotaan FK sesungguhnya begitu admin memindahkan
        // prodi antar jurusan di Master Data, sedangkan id_prodi_in selalu
        // mengikuti keanggotaan yang sebenarnya. Cube.js `equals` menerima
        // array = semantik IN, jadi tidak perlu operator terpisah.
        // KEDUANYA dipasang bila sama-sama ada, bukan salah satu. id_prodi_in
        // adalah BATAS cakupan (apa yang boleh dilihat), sedangkan `jurusan`
        // adalah PENYEMPIT yang dipilih pengguna di dalam batas itu. Sempat
        // ditulis sebagai elseif, dan akibatnya Dekan yang memilih
        // satu jurusan tetap melihat angka seluruh fakultas -- penyaringnya
        // tampak tidak berfungsi, tanpa galat apa pun.
        if ($idProdiIn !== null && $idProdiIn !== []) {
            $filters[] = [
                'member'   => 'DimProdi.id_prodi',
                'operator' => 'equals',
                'values'   => array_map('strval', $idProdiIn),
            ];
        }

        if ($jurusan !== null && $jurusan !== '') {
            $filters[] = [
                'member'   => 'DimProdi.jurusan',
                'operator' => 'equals',
                'values'   => [$jurusan],
            ];
        }

        if ($namaProdi !== null && $namaProdi !== '') {
            $filters[] = [
                'member'   => 'DimProdi.nama_prodi',
                'operator' => 'equals',
                'values'   => [$namaProdi],
            ];
        }

        if ($tahunLulus !== null && $tahunLulus !== '') {
            $filters[] = [
                'member'   => 'DimAlumni.tahun_lulus',
                'operator' => 'equals',
                'values'   => [$tahunLulus],
            ];
        }

        if ($mingguSnapshot !== null && $mingguSnapshot !== '') {
            // Filter by DimWaktu.id_waktu (UNIK per snapshot run), BUKAN
            // DimWaktu.minggu_snapshot (teks, mis. "29") -- nama parameter
            // ($mingguSnapshot / query string minggu_snapshot) sengaja TIDAK
            // diubah di seluruh codebase (FE, controller, DTO) supaya diff
            // kecil, tapi NILAI yang mengalir lewat sini sekarang adalah
            // id_waktu (lihat FilterMetaRepository::getSnapshot(), yang
            // mengisi field "value" dropdown dengan id_waktu, bukan lagi
            // minggu_snapshot mentah).
            //
            // Alasan: dua ETL run di minggu kalender yang SAMA (rutin terjadi
            // sekarang karena Langkah 1 mapping auto-trigger ETL kapan saja,
            // bukan cuma terjadwal mingguan) menghasilkan DUA baris dim_waktu
            // dengan minggu_snapshot yang IDENTIK -- filter berbasis teks
            // akan mencocokkan KEDUANYA sekaligus, meng-inflate/mengacaukan
            // semua angka agregat. id_waktu adalah primary key, selalu unik
            // per run, tidak punya ambiguitas ini.
            $filters[] = [
                'member'   => 'DimWaktu.id_waktu',
                'operator' => 'equals',
                'values'   => [$mingguSnapshot],
            ];
        }

        // Merge filter tambahan spesifik endpoint
        return array_merge($filters, $extra);
    }

    /**
     * Helper: bangun filter dari raw array params (dari $request->query()).
     * Cocok dipakai di controller yang langsung forward query params ke repo.
     *
     * Params yang dikenali: jenjang, jurusan, nama_prodi, tahun_lulus, minggu_snapshot.
     * Params lain di-ignore aman.
     */
    protected function buildGlobalFiltersFromArray(array $params, array $extra = []): array
    {
        return $this->buildGlobalFilters(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            extra:          $extra,
            idProdiIn:      $params['id_prodi_in']     ?? null,
        );
    }

    /**
     * Helper: ambil nilai tahun yang tersedia untuk dropdown FE.
     * Tidak pakai pre-agg — metadata dimension kecil.
     *
     * @return array<string>
     */
    protected function fetchAvailableYears(): array
    {
        return $this->cube->load([
            'dimensions' => ['DimAlumni.tahun_lulus'],
            'order'      => [['DimAlumni.tahun_lulus', 'desc']],
        ])
        ->pluck('DimAlumni.tahun_lulus')
        ->filter()
        ->unique()
        ->values()
        ->toArray();
    }

    /**
     * Helper: ambil minggu snapshot yang tersedia untuk dropdown FE.
     * Tidak pakai pre-agg — metadata dimension kecil.
     *
     * @return array<string>
     */
    protected function fetchAvailableSnapshots(): array
    {
        return $this->cube->load([
            'dimensions' => [
                'DimWaktu.minggu_snapshot',
                'DimWaktu.tahun_snapshot',
            ],
            'order' => [['DimWaktu.tahun_snapshot', 'desc'], ['DimWaktu.minggu_snapshot', 'desc']],
        ])
        ->map(fn($r) => [
            'minggu_snapshot' => $r['DimWaktu.minggu_snapshot'],
            'tahun_snapshot'  => $r['DimWaktu.tahun_snapshot'],
        ])
        ->unique('minggu_snapshot')
        ->values()
        ->toArray();
    }
}
