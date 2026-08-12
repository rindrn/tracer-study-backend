<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referensi UMP per provinsi per tahun — sebelumnya hanya ada di
 * database/dump/oltp_supplement.sql.
 *
 * Dipakai untuk menurunkan pita pendapatan alumni (f505 dibandingkan UMP
 * provinsi tempat bekerja) yang bermuara di public.dim_ump. Lihat catatan di
 * 2026_08_11_000001_create_semantic_role_registry_table.php soal kenapa
 * ditulis sebagai SQL mentah.
 *
 * `id` memakai sequence biasa (bukan identity) mengikuti init.sql.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE ref_ump (
                id            integer               NOT NULL,
                tahun         integer               NOT NULL,
                province_id   integer               NOT NULL,
                nilai_ump     bigint                NOT NULL,
                sumber        character varying(20) DEFAULT 'MANUAL',
                created_at    timestamp without time zone DEFAULT now(),
                nama_provinsi character varying(255),
                updated_at    timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT ref_ump_pkey PRIMARY KEY (id),
                CONSTRAINT ref_ump_tahun_province_id_key UNIQUE (tahun, province_id),
                CONSTRAINT ref_ump_province_id_fkey
                    FOREIGN KEY (province_id) REFERENCES provinces (id)
            )
        ");

        DB::connection('oltp')->statement("
            CREATE SEQUENCE ref_ump_id_seq AS integer
                START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1
        ");

        DB::connection('oltp')->statement("
            ALTER SEQUENCE ref_ump_id_seq OWNED BY ref_ump.id
        ");

        DB::connection('oltp')->statement("
            ALTER TABLE ref_ump ALTER COLUMN id SET DEFAULT nextval('ref_ump_id_seq')
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('ref_ump');
    }
};
