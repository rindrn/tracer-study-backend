<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Support\PersonalData;

/**
 * Enkripsi NIK dan NPWP alumni yang tersimpan (UU 27/2022 tentang PDP).
 *
 * Dua langkah, urutannya wajib:
 *
 *   1. Lebarkan kolom. Ciphertext Laravel panjangnya ratusan karakter,
 *      sementara kolomnya varchar(16) dan varchar(20). Tanpa langkah ini
 *      seluruh penulisan gagal dengan "value too long".
 *   2. Enkripsi baris yang sudah ada, sepotong demi sepotong.
 *
 * Kenapa `text` dan bukan varchar berukuran besar: panjang ciphertext ikut
 * berubah kalau kelak sandinya berganti, dan di PostgreSQL text tidak lebih
 * mahal daripada varchar.
 *
 * KEBALIKANNYA TIDAK MENGEMBALIKAN LEBAR KOLOM. down() mendekripsi isinya
 * supaya data tetap terbaca, tapi kolomnya dibiarkan bertipe text. Menyempitkan
 * kembali ke varchar(16) akan memotong nilai apa pun yang gagal didekripsi dan
 * itu kehilangan permanen — kerugiannya jauh lebih besar daripada manfaat
 * memulihkan lebar kolom.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    /** Sekali proses berapa baris. Cukup kecil supaya memori aman pada data puluhan ribu alumni. */
    private const CHUNK = 500;

    public function up(): void
    {
        // Statement mentah, bukan Blueprint: doctrine/dbal tidak terpasang di
        // proyek ini, sehingga $table->text('nik')->change() akan gagal.
        DB::connection('oltp')->statement('ALTER TABLE alumni_profiles ALTER COLUMN nik TYPE text');
        DB::connection('oltp')->statement('ALTER TABLE alumni_profiles ALTER COLUMN npwp TYPE text');

        $this->rewrite(fn (?string $value) => PersonalData::protect($value));
    }

    public function down(): void
    {
        $this->rewrite(fn (?string $value) => PersonalData::reveal($value));
    }

    /**
     * Baca ulang seluruh baris yang punya NIK atau NPWP, lewatkan ke $transform,
     * lalu tulis kembali.
     *
     * Dipakai dua arah (enkripsi di up, dekripsi di down) karena langkahnya
     * identik selain fungsi transformasinya. PersonalData::protect() aman
     * dijalankan ulang pada nilai yang sudah terenkripsi, jadi migrasi yang
     * terhenti di tengah bisa diulang tanpa merusak baris yang sudah selesai.
     */
    private function rewrite(callable $transform): void
    {
        DB::connection('oltp')->table('alumni_profiles')
            ->select(['id', 'nik', 'npwp'])
            // Kedua syarat WAJIB dikurung. chunkById() menambahkan `and id > ?`
            // di ujung klausa WHERE, dan AND mengikat lebih kuat daripada OR:
            // tanpa kurung, syaratnya terbaca `nik IS NOT NULL OR (npwp IS NOT
            // NULL AND id > ?)`. Baris ber-NIK karenanya cocok berapa pun
            // kursornya, potongan pertama terambil terus-menerus, dan
            // migrasinya berputar tanpa akhir — tanpa galat, hanya menggantung.
            ->where(function ($q) {
                $q->whereNotNull('nik')->orWhereNotNull('npwp');
            })
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use ($transform) {
                foreach ($rows as $row) {
                    DB::connection('oltp')->table('alumni_profiles')
                        ->where('id', $row->id)
                        ->update([
                            'nik'  => $transform($row->nik),
                            'npwp' => $transform($row->npwp),
                        ]);
                }
            });
    }
};
