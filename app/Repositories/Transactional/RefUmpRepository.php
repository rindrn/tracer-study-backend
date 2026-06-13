<?php
// app/Repositories/Transactional/RefUmpRepository.php

namespace App\Repositories\Transactional;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RefUmpRepository
{
    // ── READ ──────────────────────────────────────────────────

    public function availableYears(): array
    {
        return DB::connection('oltp')
            ->table('ref_ump')
            ->select('tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn($y) => (int) $y)
            ->toArray();
    }

    public function byTahun(int $tahun): Collection
    {
        return DB::connection('oltp')
            ->table('ref_ump as u')
            ->join('provinces as p', 'p.id', '=', 'u.province_id')
            ->select(
                'u.id',
                'u.tahun',
                'u.province_id',
                'u.nama_provinsi',
                'u.nilai_ump',
                'u.sumber',
                'u.created_at',
                'u.updated_at',
            )
            ->where('u.tahun', $tahun)
            ->orderBy('p.name')
            ->get();
    }

    public function findByTahunProvinsi(int $tahun, int $idProvinsi): ?object
    {
        return DB::connection('oltp')
            ->table('ref_ump')
            ->where('tahun', $tahun)
            ->where('province_id', $idProvinsi)
            ->first();
    }

    public function allProvinces(): Collection
    {
        return DB::connection('oltp')
            ->table('provinces')
            ->select('id', 'name')
            ->where('id', '!=', 6)
            ->orderBy('name')
            ->get();
    }

    // ── WRITE ─────────────────────────────────────────────────

    public function upsert(
        int    $tahun,
        int    $idProvinsi,
        string $namaProvinsi,
        int    $nilaiUmp,
        string $sumber,
    ): object {
        DB::connection('oltp')->table('ref_ump')->upsert(
            [
                'tahun'         => $tahun,
                'province_id'   => $idProvinsi,
                'nama_provinsi' => $namaProvinsi,
                'nilai_ump'     => $nilaiUmp,
                'sumber'        => $sumber,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            uniqueBy: ['tahun', 'province_id'],
            update:   ['nama_provinsi', 'nilai_ump', 'sumber', 'updated_at'],
        );

        return $this->findByTahunProvinsi($tahun, $idProvinsi);
    }

    public function bulkUpsert(array $rows): int
    {
        $now    = now();
        $filled = array_filter($rows, fn($r) => isset($r['nilai_ump']) && $r['nilai_ump'] !== null);

        if (empty($filled)) {
            return 0;
        }

        $payload = array_map(fn($r) => [
            'tahun'         => $r['tahun'],
            'province_id'   => $r['id_provinsi'],
            'nama_provinsi' => $r['nama_provinsi'],
            'nilai_ump'     => $r['nilai_ump'],
            'sumber'        => $r['sumber'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ], array_values($filled));

        DB::connection('oltp')->table('ref_ump')->upsert(
            $payload,
            uniqueBy: ['tahun', 'province_id'],
            update:   ['nama_provinsi', 'nilai_ump', 'sumber', 'updated_at'],
        );

        return count($payload);
    }

    public function updateSingle(int $id, int $nilaiUmp, string $sumber = 'MANUAL'): ?object
    {
        $affected = DB::connection('oltp')
            ->table('ref_ump')
            ->where('id', $id)
            ->update([
                'nilai_ump'  => $nilaiUmp,
                'sumber'     => $sumber,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            return null;
        }

        return DB::connection('oltp')->table('ref_ump')->where('id', $id)->first();
    }
}