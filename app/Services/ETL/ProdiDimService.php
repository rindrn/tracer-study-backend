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

        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($programs as $program) {
            $processed++;

            $active = $this->olapRepo->getActiveProdiVersion($program->id);

            $newAttributes = [
                'id_prodi'   => $program->id,
                'kode_prodi' => $program->code,
                'nama_prodi' => $program->name,
                'jurusan'    => $program->jurusan,
                'jenjang'    => $program->degree,
            ];

            if ($active === null) {
                $this->olapRepo->insertNewProdiVersion($newAttributes, $snapshotDate);
                $inserted++;
                continue;
            }

            // Perbandingan longgar — lihat WirausahaDimService untuk alasan
            // lengkap: !== antara kolom DB (selalu string) dan nilai OLTP
            // (bisa berbeda tipe) salah mendeteksi "berubah" padahal sama,
            // dan memicu versi SCD duplikat terus-menerus.
            $hasChanged = self::normalize($active->kode_prodi) !== self::normalize($newAttributes['kode_prodi'])
                || self::normalize($active->nama_prodi) !== self::normalize($newAttributes['nama_prodi'])
                || self::normalize($active->jurusan) !== self::normalize($newAttributes['jurusan'])
                || self::normalize($active->jenjang) !== self::normalize($newAttributes['jenjang']);

            if ($hasChanged) {
                $this->olapRepo->closeProdiVersion($active->prodi_sk, $snapshotDate);
                $this->olapRepo->insertNewProdiVersion($newAttributes, $snapshotDate);
                $inserted++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }

    /** Null dan string kosong dianggap setara; selain itu dibandingkan sebagai string. */
    private static function normalize(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}