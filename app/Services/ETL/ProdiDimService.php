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
            ];

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
                || $active->akreditasi_prodi !== $newAttributes['akreditasi_prodi'];

            if ($hasChanged) {
                $this->olapRepo->closeProdiVersion($active->prodi_sk, $snapshotDate);
                $this->olapRepo->insertNewProdiVersion($newAttributes, $snapshotDate);
                $inserted++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }
}