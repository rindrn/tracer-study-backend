-- 005_reconcile_init.sql — menyelaraskan init.sql dengan riwayat migrasi.
--
-- init.sql adalah pg_dump dari basis data produksi yang pernah diubah manual,
-- sehingga skemanya lebih maju daripada isi tabel `migrations` di dalamnya.
-- Tanpa penyelarasan ini `php artisan migrate` sesudah restore akan gagal
-- (kolom/view sudah ada) atau melewati tabel yang sebenarnya belum dibuat.
--
-- HARUS dijalankan SESUDAH restore init.sql dan SEBELUM `php artisan migrate`.
--
-- Additive dan idempotent — aman dijalankan berulang kali.

SET client_encoding = 'UTF8';
SET client_min_messages = warning;
SET search_path = tracer_oltp;

-- 1. Berkas migrasi view LAM pernah di-rename dari ..._000001_... menjadi
--    ..._000006_.... Baris lamanya tidak lagi cocok dengan nama berkas, jadi
--    Laravel menganggapnya pending dan mencoba CREATE VIEW ulang di atas view
--    yang sudah ada (SQLSTATE 42P07 pada vw_thresholds_complete).
UPDATE migrations
   SET migration = '2026_06_01_000006_create_vw_thresholds_and_lam_views'
 WHERE migration = '2026_06_01_000001_create_vw_thresholds_and_lam_views';

-- 2. threshold_indicators.dynamic_param_unit dan .is_system_calculated sudah
--    ada di dump, tapi migrasi yang menambahkannya tidak pernah tercatat dan
--    tidak punya guard hasColumn(). Catat sebagai sudah jalan.
INSERT INTO migrations (migration, batch)
SELECT '2026_06_20_000001_add_dynamic_fields_to_threshold_indicators',
       COALESCE((SELECT MAX(batch) FROM migrations), 0)
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
     WHERE migration = '2026_06_20_000001_add_dynamic_fields_to_threshold_indicators'
);

-- 3. threshold_programs tercatat sebagai sudah dibuat, tapi tabelnya tidak ada
--    di dump (dihapus manual di basis data sumber). `migrate` melewatinya
--    sehingga relasi Threshold::programs() dan Program::thresholds() gagal saat
--    runtime tanpa error apa pun saat instalasi. Definisi disalin dari
--    2026_03_22_000005_create_threshold_programs_table.php.
CREATE TABLE IF NOT EXISTS threshold_programs (
    id           bigserial PRIMARY KEY,
    threshold_id bigint    NOT NULL,
    program_id   bigint    NOT NULL,
    created_at   timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT threshold_programs_threshold_id_program_id_unique
        UNIQUE (threshold_id, program_id),
    -- Nama constraint sengaja memakai akhiran _foreign, bukan _fkey bawaan
    -- Postgres: itu pola penamaan Blueprint Laravel, dan tabel ini juga dibuat
    -- lewat migrasi di instalasi dari basis data kosong. Kalau namanya beda,
    -- kedua jalur instalasi menghasilkan skema yang tidak identik.
    CONSTRAINT threshold_programs_threshold_id_foreign
        FOREIGN KEY (threshold_id) REFERENCES thresholds (id) ON DELETE CASCADE,
    CONSTRAINT threshold_programs_program_id_foreign
        FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE CASCADE
);

-- 4. Sembilan tabel yang dulu hanya ada di oltp_supplement.sql sekarang punya
--    migrasi sungguhan (2026_08_11_000001..000007). Tabelnya sudah terkandung
--    di init.sql, jadi untuk jalur restore-dari-dump migrasinya dicatat sebagai
--    sudah jalan. Di basis data kosong migrasi-migrasi itu jalan normal dan
--    membuat tabelnya sendiri.
--
--    Kalau salah satu berkas migrasi di-rename, daftar di bawah HARUS ikut
--    diubah -- kalau tidak, migrate akan mencoba CREATE TABLE di atas tabel
--    yang sudah ada dan gagal.
INSERT INTO migrations (migration, batch)
SELECT m.name, COALESCE((SELECT MAX(batch) FROM migrations), 0)
  FROM (VALUES
        ('2026_08_11_000001_create_semantic_role_registry_table'),
        ('2026_08_11_000002_create_question_semantic_mapping_table'),
        ('2026_08_11_000003_create_ref_ump_table'),
        ('2026_08_11_000004_create_threshold_configs_table'),
        ('2026_08_11_000005_create_tracer_response_thresholds_table'),
        ('2026_08_11_000006_create_etl_runs_table'),
        ('2026_08_11_000007_create_queue_tables')
       ) AS m(name)
 WHERE NOT EXISTS (
        SELECT 1 FROM migrations x WHERE x.migration = m.name
       );
