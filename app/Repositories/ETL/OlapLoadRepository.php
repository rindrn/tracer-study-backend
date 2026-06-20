<?php

namespace App\Repositories\ETL;

use Illuminate\Support\Facades\DB;

class OlapLoadRepository
{
    private function olap(): \Illuminate\Database\Connection
    {
        return DB::connection('olap');
    }

    // ───────────────── dim_waktu — selalu INSERT baru ─────────────────
    public function insertNewSnapshot(\DateTimeInterface $tanggalRefresh): int
    {
        return $this->olap()->table('dim_waktu')->insertGetId([
            'minggu_snapshot' => $tanggalRefresh->format('W'),
            'bulan_snapshot'  => $tanggalRefresh->format('F'),
            'tahun_snapshot'  => $tanggalRefresh->format('Y'),
            'tanggal_refresh' => $tanggalRefresh->format('Y-m-d'),
        ], 'id_waktu');
    }

    // ───────────────── dim_prodi — SCD TYPE 2 ─────────────────
    public function getActiveProdiVersion(int $idProdi): ?object
    {
        return $this->olap()->table('dim_prodi')->where('id_prodi', $idProdi)->where('flag_prodi', true)->first();
    }

    public function closeProdiVersion(int $prodiSk, \DateTimeInterface $validTo): void
    {
        $this->olap()->table('dim_prodi')->where('prodi_sk', $prodiSk)
            ->update(['valid_to' => $validTo->format('Y-m-d'), 'flag_prodi' => false]);
    }

    public function insertNewProdiVersion(array $attributes, \DateTimeInterface $validFrom): int
    {
        return $this->olap()->table('dim_prodi')->insertGetId([
            'id_prodi'   => $attributes['id_prodi'],
            'kode_prodi' => $attributes['kode_prodi'],
            'nama_prodi' => $attributes['nama_prodi'],
            'jurusan'    => $attributes['jurusan'],
            'jenjang'    => $attributes['jenjang'],
            'valid_from' => $validFrom->format('Y-m-d'),
            'valid_to'   => null,
            'flag_prodi' => true,
        ], 'prodi_sk');
    }

    // ───────────────── dim_alumni — SCD TYPE 1 ─────────────────
    public function upsertAlumni(array $attributes): void
    {
        $this->olap()->table('dim_alumni')->updateOrInsert(
            ['nim' => $attributes['nim']],
            [
                'nama'                         => $attributes['nama'],
                'jenis_kelamin'                => $attributes['jenis_kelamin'],
                'angkatan'                     => $attributes['angkatan'],
                'tahun_lulus'                  => $attributes['tahun_lulus'],
                'label_sumber_biaya_dipolban'  => $attributes['label_sumber_biaya_dipolban'] ?? null,
            ]
        );
    }

    public function getAlumniSkByNim(string $nim): ?int
    {
        return $this->olap()->table('dim_alumni')->where('nim', $nim)->first()?->id_alumni;
    }

    // ───────────────── dim_status_alumni — Type 1 + append ─────────────────
    public function upsertStatusAlumni(string $idStatusAlumni, string $label): void
    {
        $this->olap()->table('dim_status_alumni')->updateOrInsert(
            ['id_status_alumni' => $idStatusAlumni],
            ['label' => $label]
        );
    }

    public function getStatusAlumniSk(string $idStatusAlumni): ?int
    {
        return $this->olap()->table('dim_status_alumni')->where('id_status_alumni', $idStatusAlumni)->first()?->status_alumni_sk;
    }

    // ───────────────── dim_perusahaan — SCD TYPE 2 ─────────────────
    public function getActivePerusahaanVersion(string $idPerusahaan): ?object
    {
        return $this->olap()->table('dim_perusahaan')->where('id_perusahaan', $idPerusahaan)->where('flag_perusahaan', true)->first();
    }

    public function closePerusahaanVersion(int $perusahaanSk, \DateTimeInterface $validTo): void
    {
        $this->olap()->table('dim_perusahaan')->where('perusahaan_sk', $perusahaanSk)
            ->update(['valid_to' => $validTo->format('Y-m-d'), 'flag_perusahaan' => false]);
    }

    public function insertNewPerusahaanVersion(array $attributes, \DateTimeInterface $validFrom): int
    {
        return $this->olap()->table('dim_perusahaan')->insertGetId([
            'id_perusahaan'          => $attributes['id_perusahaan'],
            'company_name'           => $attributes['company_name'],
            'label_jenis_perusahaan' => $attributes['label_jenis_perusahaan'],
            'label_tingkat_instansi' => $attributes['label_tingkat_instansi'],
            'nama_kota'              => $attributes['nama_kota'],
            'nama_provinsi'          => $attributes['nama_provinsi'],
            'valid_from'             => $validFrom->format('Y-m-d'),
            'valid_to'               => null,
            'flag_perusahaan'        => true,
        ], 'perusahaan_sk');
    }

    // ───────────────── dim_wirausaha — SCD TYPE 2 ─────────────────
    public function getActiveWirausahaVersion(string $idWirausaha): ?object
    {
        return $this->olap()->table('dim_wirausaha')->where('id_wirausaha', $idWirausaha)->where('flag_wirausaha', true)->first();
    }

    public function closeWirausahaVersion(int $wirausahaSk, \DateTimeInterface $validTo): void
    {
        $this->olap()->table('dim_wirausaha')->where('wirausaha_sk', $wirausahaSk)
            ->update(['valid_to' => $validTo->format('Y-m-d'), 'flag_wirausaha' => false]);
    }

    public function insertNewWirausahaVersion(array $attributes, \DateTimeInterface $validFrom): int
    {
        return $this->olap()->table('dim_wirausaha')->insertGetId([
            'id_wirausaha'           => $attributes['id_wirausaha'],
            'jabatan'                => $attributes['jabatan'],
            'label_tingkat_instansi' => $attributes['label_tingkat_instansi'],
            'nama_provinsi'          => $attributes['nama_provinsi'],
            'nama_kota'              => $attributes['nama_kota'],
            'valid_from'             => $validFrom->format('Y-m-d'),
            'valid_to'               => null,
            'flag_wirausaha'         => true,
        ], 'wirausaha_sk');
    }

    // ───────────────── dim_kesesuaian_level — Type 1 + append, dynamic ─────────────────
    // Business key sekarang string ("{questionnaire_id}:f14:{option_code}"),
    // pola identik dim_status_alumni. Method sentinel lama berbasis "0"
    // integer SUDAH DIHAPUS -- sentinel sekarang juga string, lihat
    // ensureKesesuaianLevelSentinel() di bawah.
    public function upsertKesesuaianLevel(string $idKesesuaianLevel, string $label): int
    {
        $this->olap()->table('dim_kesesuaian_level')->updateOrInsert(
            ['id_kesesuaian_level' => $idKesesuaianLevel],
            ['label' => $label]
        );
        return $this->olap()->table('dim_kesesuaian_level')->where('id_kesesuaian_level', $idKesesuaianLevel)->first()->kesesuaian_level_sk;
    }

    public function getKesesuaianLevelSk(string $idKesesuaianLevel): ?int
    {
        return $this->olap()->table('dim_kesesuaian_level')->where('id_kesesuaian_level', $idKesesuaianLevel)->first()?->kesesuaian_level_sk;
    }

    /** Business key sentinel untuk dim_kesesuaian_level -- "Tidak Ada Data". */
    public const KESESUAIAN_LEVEL_SENTINEL_KEY = '0:tidak_ada_data';

    /** Business key sentinel untuk dim_kesesuaian_bidang -- "Tidak Ada Data". */
    public const KESESUAIAN_BIDANG_SENTINEL_KEY = '0:tidak_ada_data';

    /**
     * Sentinel sekarang pakai business key string "0:tidak_ada_data"
     * (bukan integer 0), supaya konsisten dengan format business key
     * dynamic yang baru. Tetap idempotent: aman dipanggil berkali-kali.
     * Mengembalikan SK (integer) untuk dipakai langsung di fact.
     */
    public function ensureKesesuaianLevelSentinel(): int
    {
        $existing = $this->getKesesuaianLevelSk(self::KESESUAIAN_LEVEL_SENTINEL_KEY);
        if ($existing !== null) {
            return $existing;
        }
        return $this->olap()->table('dim_kesesuaian_level')->insertGetId([
            'id_kesesuaian_level' => self::KESESUAIAN_LEVEL_SENTINEL_KEY,
            'label'               => 'Tidak Ada Data',
        ], 'kesesuaian_level_sk');
    }

    // ───────────────── dim_kesesuaian_bidang — Type 1 + append, dynamic ─────────────────
    // KOREKSI PENTING: dim_kesesuaian_bidang TIDAK punya hubungan dengan
    // dim_kesesuaian_level. Keduanya independen -- f14 (kesesuaian
    // bidang studi) dan f15 (kesesuaian level pendidikan) adalah dua
    // pertanyaan terpisah dengan opsi berbeda. Kolom yang sebelumnya
    // bernama id_kesesuaian_level di tabel ini sudah di-RENAME jadi
    // id_kesesuaian_bidang (lihat migrations/004), menjadi business
    // key MILIK dim_kesesuaian_bidang sendiri, format sama dengan
    // dim_kesesuaian_level: "{questionnaire_id}:f14:{option_code}".
    public function getKesesuaianBidangSk(string $idKesesuaianBidang): ?int
    {
        return $this->olap()->table('dim_kesesuaian_bidang')->where('id_kesesuaian_bidang', $idKesesuaianBidang)->first()?->kesesuaian_bidang_sk;
    }

    public function upsertKesesuaianBidang(string $idKesesuaianBidang, string $label): int
    {
        $this->olap()->table('dim_kesesuaian_bidang')->updateOrInsert(
            ['id_kesesuaian_bidang' => $idKesesuaianBidang],
            ['label' => $label]
        );
        return $this->olap()->table('dim_kesesuaian_bidang')->where('id_kesesuaian_bidang', $idKesesuaianBidang)->first()->kesesuaian_bidang_sk;
    }

    public function ensureKesesuaianBidangSentinel(): int
    {
        $existing = $this->getKesesuaianBidangSk(self::KESESUAIAN_BIDANG_SENTINEL_KEY);
        if ($existing !== null) {
            return $existing;
        }
        return $this->olap()->table('dim_kesesuaian_bidang')->insertGetId([
            'id_kesesuaian_bidang' => self::KESESUAIAN_BIDANG_SENTINEL_KEY,
            'label'                => 'Tidak Ada Data',
        ], 'kesesuaian_bidang_sk');
    }

    // ───────────────── dim_studi_lanjut — Type 1, PK langsung (no _sk) ─────────────────
    public function getStudiLanjutId(string $perguruanTinggi, string $programStudi): ?int
    {
        return $this->olap()->table('dim_studi_lanjut')
            ->where('perguruan_tinggi', $perguruanTinggi)
            ->where('program_studi', $programStudi)
            ->first()?->id_studi_lanjut;
    }

    public function upsertStudiLanjut(string $perguruanTinggi, string $programStudi, ?string $sumberBiaya): int
    {
        $existing = $this->getStudiLanjutId($perguruanTinggi, $programStudi);
        if ($existing !== null) {
            $this->olap()->table('dim_studi_lanjut')->where('id_studi_lanjut', $existing)
                ->update(['sumber_biaya' => $sumberBiaya]);
            return $existing;
        }
        return $this->olap()->table('dim_studi_lanjut')->insertGetId([
            'perguruan_tinggi' => $perguruanTinggi,
            'program_studi'    => $programStudi,
            'sumber_biaya'     => $sumberBiaya,
        ], 'id_studi_lanjut');
    }

    /**
     * Pastikan baris sentinel (id_studi_lanjut=0, "Tidak Ada Data") ada.
     *
     * PERBAIKAN BUG: versi sebelumnya memakai ->insert() yang
     * mengembalikan bool (true/false), BUKAN id baris yang baru dibuat.
     * Karena PHP mengonversi `true` jadi `1` saat dipaksa jadi int,
     * method ini secara salah mengembalikan 1 -- padahal baris sentinel
     * yang sungguhan ber-id_studi_lanjut=0. Akibatnya fact_tracer_study
     * di-insert dengan id_studi_lanjut=1 (FK ke baris yang tidak ada),
     * menyebabkan "Key (id_studi_lanjut)=(1) is not present in table
     * dim_studi_lanjut". Diperbaiki dengan insertGetId() + nama PK
     * eksplisit, konsisten dengan method upsert lain di kelas ini.
     */
    public function ensureStudiLanjutSentinel(): int
    {
        $existing = $this->getStudiLanjutId('Tidak Ada Data', 'Tidak Ada Data');
        if ($existing !== null) {
            return $existing;
        }
        return $this->olap()->table('dim_studi_lanjut')->insertGetId([
            'id_studi_lanjut'  => 0,
            'perguruan_tinggi' => 'Tidak Ada Data',
            'program_studi'    => 'Tidak Ada Data',
            'sumber_biaya'     => null,
        ], 'id_studi_lanjut');
    }

    // ───────────────── dim_ump — Type 1, business key id_ump ─────────────────
    public function upsertUmp(int $idUmp, string $tahun, string $namaProvinsi, float $nilaiUmp): void
    {
        $this->olap()->table('dim_ump')->updateOrInsert(
            ['id_ump' => $idUmp],
            ['tahun' => $tahun, 'nama_provinsi' => $namaProvinsi, 'nilai_ump' => $nilaiUmp]
        );
    }

    public function getUmpSk(int $idUmp): ?int
    {
        return $this->olap()->table('dim_ump')->where('id_ump', $idUmp)->first()?->ump_sk;
    }

    public function findUmpByTahunProvinsi(string $tahun, string $namaProvinsi): ?object
    {
        return $this->olap()->table('dim_ump')->where('tahun', $tahun)->where('nama_provinsi', $namaProvinsi)->first();
    }

    public function upsertIndikatorEvaluasi(string $kodeField, string $labelPertanyaan, string $kategoriPertanyaan, string $jenisSkala, string $grupGap): void
    {
        $this->olap()->table('dim_indikator_evaluasi')->updateOrInsert(
            ['kode_field' => $kodeField],
            [
                'label_pertanyaan'    => $labelPertanyaan,
                'kategori_pertanyaan' => $kategoriPertanyaan,
                'jenis_skala'         => $jenisSkala,
                'grup_gap'            => $grupGap,
            ]
        );
    }

    public function getIndikatorEvaluasiId(string $kodeField): ?int
    {
        return $this->olap()->table('dim_indikator_evaluasi')->where('kode_field', $kodeField)->first()?->id_indikator_evaluasi;
    }

    // ───────────────── FACT: fact_tracer_study ─────────────────
    public function insertFactTracerStudy(array $row): void
    {
        $this->olap()->table('fact_tracer_study')->insert($row);
    }

    // ───────────────── FACT: fact_multi_select ─────────────────
    public function insertFactMultiSelect(array $row): void
    {
        $this->olap()->table('fact_multi_select')->insert($row);
    }

    // ───────────────── FACT: fact_range_evaluasi ─────────────────
    public function insertFactRangeEvaluasi(array $row): void
    {
        $this->olap()->table('fact_range_evaluasi')->insert($row);
    }
}