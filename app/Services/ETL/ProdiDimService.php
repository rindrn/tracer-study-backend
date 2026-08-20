<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;

/**
 * ETL untuk dim_prodi. SCD Type 2 (terkonfirmasi: ada valid_from,
 * valid_to, flag_prodi di schema asli).
 */
class ProdiDimService
{
    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
        // DFR-19: hierarchy resolver dipanggil DI DALAM langkah SCD2 yang
        // sama (bukan proses terpisah), supaya level_1_name..level_5_name
        // tidak pernah out-of-sync dengan versi dim_prodi yang baru dibuat.
        private readonly OrgUnitHierarchyResolverService $hierarchyResolver,
    ) {}

    /**
     * @return array{processed:int, inserted:int, updated:int}
     */
    public function sync(\DateTimeInterface $snapshotDate): array
    {
        $programs = $this->oltpRepo->getAllPrograms();

        // Dibaca sekali di luar perulangan: nilainya konfigurasi, bukan data,
        // jadi tidak akan berubah di tengah satu jalannya ETL.
        $namaPt = config('institution.name');

        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($programs as $program) {
            $processed++;

            $active = $this->olapRepo->getActiveProdiVersion($program->id);

            // DFR-17: resolve level_1_name..level_5_name dari pohon
            // org_units SAAT INI (snapshot-aware -- nilainya mencerminkan
            // struktur organisasi pada saat ETL run ini jalan, bukan versi
            // lama yang mungkin sudah di-reparent/rename).
            $orgUnitId = $program->org_unit_id !== null ? (int) $program->org_unit_id : null;
            $hierarchyLevels = $this->hierarchyResolver->resolve($orgUnitId, $program->jurusan);

            $newAttributes = [
                'id_prodi'         => $program->id,
                'kode_prodi'       => $program->code,
                'nama_prodi'       => $program->name,
                'jurusan'          => $program->jurusan,
                'jenjang'          => $program->degree,
                // Sama untuk seluruh baris pada satu pemasangan (single-tenant),
                // tapi tetap disimpan per baris supaya dimensi ini sudah siap
                // kalau kelak satu pemasangan melayani lebih dari satu PT.
                'nama_pt'          => $namaPt,
                'akreditasi_prodi' => $program->accreditation,
            ] + $hierarchyLevels;

            if ($active === null) {
                $this->olapRepo->insertNewProdiVersion($newAttributes, $snapshotDate);
                $inserted++;
                continue;
            }

            // Perbandingan menentukan kapan versi lama ditutup dan versi baru
            // dibuka. Atribut yang tidak ikut dibandingkan berarti perubahannya
            // hilang diam-diam -- karena itu nama_pt dan akreditasi_prodi WAJIB
            // ada di sini, bukan hanya di $newAttributes. Kenaikan peringkat
            // akreditasi memang seharusnya melahirkan versi baru: itulah yang
            // membuat dashboard bisa membandingkan capaian sebelum dan sesudah.
            $hasChanged = $active->kode_prodi !== $newAttributes['kode_prodi']
                || $active->nama_prodi !== $newAttributes['nama_prodi']
                || $active->jurusan !== $newAttributes['jurusan']
                || $active->jenjang !== $newAttributes['jenjang']
                || $active->nama_pt !== $newAttributes['nama_pt']
                || $active->akreditasi_prodi !== $newAttributes['akreditasi_prodi']
                // DFR-19: rename/reparent unit organisasi (yang mengubah
                // hasil resolve level_1_name..level_5_name) juga wajib
                // melahirkan versi baru -- kalau tidak, riwayat SCD2 diam-diam
                // berhenti mencerminkan struktur organisasi yang sedang berlaku.
                || $active->level_1_name !== $newAttributes['level_1_name']
                || $active->level_2_name !== $newAttributes['level_2_name']
                || $active->level_3_name !== $newAttributes['level_3_name']
                || $active->level_4_name !== $newAttributes['level_4_name']
                || $active->level_5_name !== $newAttributes['level_5_name'];

            if ($hasChanged) {
                $this->olapRepo->closeProdiVersion($active->prodi_sk, $snapshotDate);
                $this->olapRepo->insertNewProdiVersion($newAttributes, $snapshotDate);
                $inserted++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }
}