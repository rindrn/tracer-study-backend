<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peringkat akreditasi program studi.
 *
 * Gambar 5.3 proposal menggambarkan `dim_prodi.akreditasi_prodi`, tapi
 * nilainya tidak bisa muncul di data warehouse kalau OLTP tidak pernah
 * menyimpannya. Kolom ini sumbernya.
 *
 * Bertipe string bebas, bukan enum: peringkatnya berbeda-beda antar lembaga
 * dan antar zaman -- BAN-PT lama memakai A/B/C, IAPS 4.0 memakai Unggul/Baik
 * Sekali/Baik, dan tiap LAM boleh memakai istilahnya sendiri. Enum akan
 * memaksa migration baru setiap kali ada lembaga yang memakai istilah lain,
 * dan SmartTracer justru dirancang untuk banyak LAM sekaligus (lihat tabel
 * `lams` beserta versinya).
 *
 * `accredited_until` ikut karena peringkat akreditasi selalu punya masa
 * berlaku, dan dashboard penjaminan mutu perlu tahu prodi mana yang
 * akreditasinya segera kedaluwarsa. Nullable karena prodi baru belum
 * terakreditasi.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->string('accreditation', 30)->nullable()->after('degree');
            $table->date('accredited_until')->nullable()->after('accreditation');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->dropColumn(['accreditation', 'accredited_until']);
        });
    }
};
