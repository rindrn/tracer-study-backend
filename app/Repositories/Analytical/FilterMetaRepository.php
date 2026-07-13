<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

/**
 * FilterMetaRepository
 *
 * Menyediakan semua opsi dropdown untuk global filter dashboard.
 * Dipanggil SEKALI saat dashboard pertama load — hasilnya di-cache
 * di FE (React state / Zustand / Pinia) sehingga tidak perlu
 * dipanggil ulang setiap chart berubah.
 *
 * TIDAK pakai pre-agg untuk semua method di sini:
 *   - Data metadata dimension sangat kecil (puluhan baris)
 *   - Dipanggil sekali, bukan per interaksi user
 *   - Overhead pre-agg Redis tidak sebanding untuk data sekecil ini
 *
 * Taruh di: app/Repositories/Analytical/FilterMetaRepository.php
 */
class FilterMetaRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  TAHUN LULUS — dari dim_alumni
    // ──────────────────────────────────────────────────────────────

    /**
     * Semua tahun lulus yang ada di DW.
     * Dipakai untuk: filter global "Tahun Lulus" di semua chart.
     *
     * @return array<string>  contoh: ["2024","2023","2022","2021","2020"]
     */
    public function getTahunLulus(): array
    {
        return $this->cube->load([
            'dimensions' => ['DimAlumni.tahun_lulus'],
            'order'      => [['DimAlumni.tahun_lulus', 'desc']],
        ])
        ->map(fn ($r) => $r['DimAlumni.tahun_lulus'] ?? null)
        ->filter()
        ->unique()
        ->values()
        ->toArray();
    }

    // ──────────────────────────────────────────────────────────────
    //  MINGGU SNAPSHOT — dari dim_waktu
    // ──────────────────────────────────────────────────────────────

    /**
     * Semua snapshot yang ada di DW, diurutkan terbaru dulu.
     * Dipakai untuk: filter global "Snapshot" yang memilih periode DW.
     *
     * NILAI FILTER masih `minggu_snapshot` mentah (TIDAK berubah -- lihat
     * catatan di bawah), tapi respons sekarang menyertakan tahun_snapshot,
     * bulan_snapshot, dan tanggal_refresh supaya FE bisa menampilkan label
     * yang jelas ("2026 · Minggu 26 · Juni · 24 Jun 2026"), bukan cuma
     * angka minggu mentah seperti sebelumnya.
     *
     * CATATAN PENTING -- BELUM sepenuhnya menyelesaikan potensi ambiguitas:
     * filter yang benar-benar dipakai (lihat BaseAnalyticalRepository::
     * buildGlobalFilters()) masih mencocokkan HANYA berdasarkan teks
     * minggu_snapshot, bukan kombinasi minggu+tahun atau id_waktu unik.
     * Kalau suatu saat ada 2 snapshot berbeda dengan angka minggu yang SAMA
     * (lintas tahun, ATAU >1 kali ETL jalan di minggu kalender yang sama --
     * mis. karena ada perubahan mapping, bukan jadwal mingguan), memilih
     * salah satu di dropdown akan tetap mencocokkan KEDUANYA di query,
     * bukan cuma yang dipilih. Memperbaiki ini butuh mengganti filter
     * member dari DimWaktu.minggu_snapshot ke FactTracerStudy.id_waktu
     * (satu-satunya identitas snapshot yang benar-benar unik) -- perubahan
     * yang sengaja TIDAK dilakukan di sini karena mengubah makna parameter
     * yang sudah dipakai di seluruh dashboard, perlu keputusan terpisah.
     *
     * @return array<array{
     *   value: string,        -- dikirim sebagai minggu_snapshot ke filter (TIDAK berubah)
     *   tahun_snapshot: string,
     *   bulan_snapshot: string,
     *   tanggal_refresh: string,
     *   label: string         -- siap tampil di dropdown FE
     * }>
     */
    public function getSnapshot(): array
    {
        return $this->cube->load([
            'dimensions' => [
                'DimWaktu.minggu_snapshot',
                'DimWaktu.tahun_snapshot',
                'DimWaktu.bulan_snapshot',
                'DimWaktu.tanggal_refresh',
            ],
            'order' => [
                ['DimWaktu.tanggal_refresh', 'desc'],
            ],
        ])
        ->filter(fn ($r) => !empty($r['DimWaktu.minggu_snapshot']))
        ->unique(fn ($r) => $r['DimWaktu.minggu_snapshot'] . '|' . $r['DimWaktu.tahun_snapshot'] . '|' . $r['DimWaktu.tanggal_refresh'])
        ->map(function ($r) {
            $minggu   = $r['DimWaktu.minggu_snapshot'];
            $tahun    = $r['DimWaktu.tahun_snapshot'] ?? '';
            $bulan    = $r['DimWaktu.bulan_snapshot'] ?? '';
            $tanggal  = $r['DimWaktu.tanggal_refresh'] ?? null;
            $tanggalFormatted = $tanggal ? \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y') : '';

            return [
                'value'           => $minggu,
                'tahun_snapshot'  => $tahun,
                'bulan_snapshot'  => $bulan,
                'tanggal_refresh' => $tanggal,
                'label'           => trim("{$tahun} · Minggu {$minggu} · {$bulan}" . ($tanggalFormatted ? " ({$tanggalFormatted})" : '')),
            ];
        })
        ->values()
        ->toArray();
    }

    // ──────────────────────────────────────────────────────────────
    //  JENJANG — dari dim_prodi
    // ──────────────────────────────────────────────────────────────

    /**
     * Semua jenjang unik yang ada di DW.
     * Biasanya hanya: ["D3", "D4"] — tapi diambil dinamis dari DW
     * agar tidak hardcode jika POLBAN tambah jenjang baru.
     *
     * @return array<string>
     */
    public function getJenjang(): array
    {
        return $this->cube->load([
            'dimensions' => ['DimProdi.jenjang'],
            'order'      => [['DimProdi.jenjang', 'asc']],
        ])
        ->map(fn ($r) => $r['DimProdi.jenjang'] ?? null)
        ->filter()
        ->unique()
        ->values()
        ->toArray();
    }

    // ──────────────────────────────────────────────────────────────
    //  JURUSAN — dari dim_prodi, bisa difilter by jenjang
    // ──────────────────────────────────────────────────────────────

    /**
     * Semua jurusan unik yang ada di DW.
     * Dipakai untuk: filter global "Jurusan" (level 2 hierarki prodi).
     *
     * @return array<array{jurusan:string, jenjang:string}>
     *
     * Menyertakan jenjang agar FE bisa filter chip jurusan
     * ketika user sudah pilih jenjang tertentu.
     */
    public function getJurusan(): array
    {
        return $this->cube->load([
            'dimensions' => [
                'DimProdi.jurusan',
                'DimProdi.jenjang',
            ],
            'order' => [
                ['DimProdi.jenjang',  'asc'],
                ['DimProdi.jurusan',  'asc'],
            ],
        ])
        ->map(fn($r) => [
            'jurusan' => $r['DimProdi.jurusan'] ?? '',
            'jenjang' => $r['DimProdi.jenjang'] ?? '',
        ])
        ->unique('jurusan')
        ->values()
        ->toArray();
    }

    // ──────────────────────────────────────────────────────────────
    //  PRODI — dari dim_prodi (hierarki lengkap)
    // ──────────────────────────────────────────────────────────────

    /**
     * Semua program studi dengan hierarki jenjang → jurusan → nama_prodi.
     * Dipakai untuk: filter global "Program Studi" (level 3 hierarki).
     *
     * @return array<array{nama_prodi:string, jurusan:string, jenjang:string, kode_prodi:string}>
     */
    public function getProdi(): array
    {
        return $this->cube->load([
            'dimensions' => [
                'DimProdi.id_prodi',
                'DimProdi.nama_prodi',
                'DimProdi.jurusan',
                'DimProdi.jenjang',
                'DimProdi.kode_prodi',
            ],
            'order' => [
                ['DimProdi.jenjang',    'asc'],
                ['DimProdi.jurusan',    'asc'],
                ['DimProdi.nama_prodi', 'asc'],
            ],
        ])
        ->map(fn($r) => [
            'id'         => (int) ($r['DimProdi.id_prodi'] ?? 0),
            'nama_prodi' => $r['DimProdi.nama_prodi'] ?? '',
            'jurusan'    => $r['DimProdi.jurusan']    ?? '',
            'jenjang'    => $r['DimProdi.jenjang']    ?? '',
            'kode_prodi' => $r['DimProdi.kode_prodi'] ?? '',
        ])
        ->unique('nama_prodi')
        ->values()
        ->toArray();
    }
}