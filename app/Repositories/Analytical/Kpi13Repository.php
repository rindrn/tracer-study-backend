<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

/**
 * Kpi13Repository
 *
 * KPI 13 tidak lagi menghitung metriknya sendiri. Angka per prodi diambil
 * dari repository yang sama dengan grafik di tab Employment Outcome
 * (MasaTungguRepository, KesesuaianRepository, WirausahaRepository,
 * PendapatanRepository -- lihat Kpi13Service), supaya halaman Bandingkan
 * per Prodi selalu sama dengan grafik tab asalnya. Versi lama memakai
 * proxy sendiri (perusahaan_sk terisi = bekerja, label Sangat Erat + Erat
 * = sesuai) yang tidak sejalan dengan tab, dan tidak memasang filter
 * snapshot sehingga setiap ETL run ikut terjumlah.
 *
 * Yang tersisa di sini: identitas prodi (termasuk id_prodi OLTP untuk
 * mencari threshold LAM), snapshot terbaru, dan daftar tahun lulus.
 */
class Kpi13Repository extends BaseAnalyticalRepository
{
    /**
     * Daftar prodi yang punya alumni pada filter aktif, beserta id_prodi
     * OLTP-nya.
     *
     * id_prodi diambil dari DimProdi.id_prodi, BUKAN FactTracerStudy.prodi_sk.
     * prodi_sk adalah surrogate key data warehouse; nilainya bisa berbeda dari
     * programs.id (mis. D-4 Teknik Informatika: sk 24, id 23), sedangkan
     * lam_programs.program_id menyimpan programs.id. Memakai prodi_sk membuat
     * threshold yang dipakai milik prodi lain.
     *
     * @return Collection<array{id_prodi:int, nama_prodi:string, jenjang:string, jurusan:string, total:int}>
     */
    public function getProdiIdentity(array $params): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimProdi.id_prodi',
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimProdi.jurusan',
            ],
            'filters' => $this->buildGlobalFiltersFromArray($params),
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ])->map(fn ($r) => [
            'id_prodi'   => (int) ($r['DimProdi.id_prodi'] ?? 0),
            'nama_prodi' => $r['DimProdi.nama_prodi'] ?? '',
            'jenjang'    => $r['DimProdi.jenjang']    ?? '',
            'jurusan'    => $r['DimProdi.jurusan']    ?? '',
            'total'      => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ])->filter(fn ($r) => $r['id_prodi'] > 0 && $r['nama_prodi'] !== '')->values();
    }

    /**
     * id_waktu snapshot terbaru yang punya baris fakta. Dipakai bila
     * pemanggil tidak mengirim minggu_snapshot -- tanpa filter ini setiap
     * snapshot ETL ikut terjumlah. Sama dengan ExplorerRepository::latestSnapshotId().
     */
    public function latestSnapshotId(): ?string
    {
        $row = $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimWaktu.id_waktu'],
            'order'      => [['DimWaktu.id_waktu', 'desc']],
            'limit'      => 1,
        ])->first();

        $id = $row['DimWaktu.id_waktu'] ?? null;

        return $id === null ? null : (string) $id;
    }

    /** Tahun lulus yang tersedia di DW, untuk dropdown FE. */
    public function getAvailableYears(): Collection
    {
        return $this->cube->load([
            'dimensions' => ['DimAlumni.tahun_lulus'],
            'order'      => [['DimAlumni.tahun_lulus', 'desc']],
        ])
        ->pluck('DimAlumni.tahun_lulus')
        ->filter()
        ->unique()
        ->values();
    }
}
