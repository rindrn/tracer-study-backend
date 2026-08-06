<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;

/**
 * Sinkronisasi dim_wirausaha (SCD Type 2).
 *
 * Business key: id_alumni (surrogate key dari dim_alumni).
 *
 * Alasan perubahan dari business key lama ("jabatan|kota"):
 *   Wirausaha adalah usaha MILIK SATU ALUMNI — tidak ada dua alumni yang
 *   berbagi satu entitas wirausaha yang sama. Business key "jabatan|kota"
 *   menyebabkan collision: dua alumni dengan jabatan dan kota yang sama
 *   akan dianggap satu entitas, dan ETL alumni kedua akan menutup versi
 *   aktif alumni pertama (flag_wirausaha → false) karena SCD Type 2
 *   mendeteksi "perubahan atribut" padahal itu entitas berbeda.
 *
 *   Dengan business key = id_alumni (SK dari dim_alumni), setiap alumni
 *   memiliki entitas wirausaha-nya sendiri. SCD Type 2 tetap bermakna:
 *   jika pada snapshot berikutnya alumni melaporkan jabatan atau kota
 *   yang berbeda, versi lama ditutup dan versi baru dibuat — riwayat
 *   perkembangan usaha alumni ter-track dengan benar.
 *
 *   id_alumni dipilih daripada NIM karena:
 *   - Konsisten dengan pola dimensi lain yang menggunakan surrogate key
 *   - dim_alumni sudah pasti tersinkronisasi sebelum loop fact dijalankan
 *   - $alumniSk sudah tersedia di AlumniFactBuilderService tanpa query tambahan
 */
class WirausahaDimService
{
    public function __construct(
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @param array $resolvedAnswers  hasil pivot EAV alumni ini
     * @param int   $alumniSk         surrogate key dari dim_alumni (id_alumni)
     * @param \DateTimeInterface $snapshotDate
     * @return int|null wirausaha_sk, atau null jika alumni tidak wirausaha (f5c kosong)
     */
    public function syncAndResolveSk(
        array $resolvedAnswers,
        int $alumniSk,
        \DateTimeInterface $snapshotDate
    ): ?int {
        $jabatan = $resolvedAnswers['jabatan'] ?? null;

        // Jika tidak ada jawaban f5c (jabatan wirausaha), alumni ini bukan wirausaha
        if ($jabatan === null) {
            return null;
        }

        // Business key = id_alumni (surrogate key dim_alumni)
        // Setiap alumni punya entitas wirausaha sendiri -- tidak ada sharing antar alumni
        $idWirausaha = $alumniSk;

        $newAttributes = [
            'id_wirausaha'           => $idWirausaha,
            'jabatan'                => $jabatan,
            'label_tingkat_instansi' => $resolvedAnswers['tingkat_instansi'] ?? null,
            'nama_provinsi'          => $resolvedAnswers['provinsi'] ?? null,
            'nama_kota'              => $resolvedAnswers['kota'] ?? null,
        ];

        $active = $this->olapRepo->getActiveWirausahaVersion($idWirausaha);

        if ($active === null) {
            return $this->olapRepo->insertNewWirausahaVersion($newAttributes, $snapshotDate);
        }

        // Deteksi perubahan atribut wirausaha antar snapshot.
        //
        // Pakai perbandingan longgar (bukan !==) yang menyamakan null dengan
        // string kosong dan angka dengan representasi string-nya. Kolom DB
        // selalu kembali sebagai string lewat PDO, sedangkan nilai baru bisa
        // saja berupa int/null dari pivot EAV — dengan !== keduanya dianggap
        // "berubah" walau nilainya identik, sehingga versi SCD baru dibuat
        // terus-menerus tanpa ada perubahan atribut sungguhan (lihat versi
        // duplikat wirausaha_sk yang menutup dirinya sendiri di produksi).
        $hasChanged = self::normalize($active->jabatan)                !== self::normalize($newAttributes['jabatan'])
            || self::normalize($active->label_tingkat_instansi) !== self::normalize($newAttributes['label_tingkat_instansi'])
            || self::normalize($active->nama_provinsi)          !== self::normalize($newAttributes['nama_provinsi'])
            || self::normalize($active->nama_kota)               !== self::normalize($newAttributes['nama_kota']);

        if ($hasChanged) {
            $this->olapRepo->closeWirausahaVersion($active->wirausaha_sk, $snapshotDate);
            return $this->olapRepo->insertNewWirausahaVersion($newAttributes, $snapshotDate);
        }

        return $active->wirausaha_sk;
    }

    /** Null dan string kosong dianggap setara; selain itu dibandingkan sebagai string. */
    private static function normalize(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}