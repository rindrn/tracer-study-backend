-- ============================================================================
-- 003_semantic_mapping_seed.sql
--
-- First ACTIVE version of the mapping tables, encoding TODAY's real
-- hardcoded truth (OltpExtractRepository::RELEVANT_QUESTION_CODES,
-- AlumniFactBuilderService's $resolved[code] reads, and FactTracerStudy.js's
-- SPLIT_PART(...) IN (...) lists). Must be run and verified BEFORE any PHP
-- or Cube.js code is switched over to read from these tables, so nothing
-- regresses mid-migration.
--
-- Scope check performed against the live dev DB: only questionnaire_id
-- 1, 2, 3 ("Kuesioner Tracer Study Nasional 2026 — Lulusan 2022/2023/2024")
-- actually contain the ~40 whitelisted question codes. The other 108
-- "Kuesioner Tambahan" (per-program supplementary) questionnaires use
-- entirely different, program-specific codes -- out of scope for this seed.
-- ============================================================================

BEGIN;

-- ─────────────────────────────────────────────────────────────────────────
-- semantic_role_registry
-- ─────────────────────────────────────────────────────────────────────────
INSERT INTO tracer_oltp.semantic_role_registry
  (role_key, label, category, description, expected_kind, value_min, value_max, sample_valid_answer, target_table, target_column, grain)
VALUES
  ('status_pekerjaan',         'Status Pekerjaan',                 'keterserapan',           'Status utama pekerjaan lulusan saat ini (bekerja/wirausaha/melanjutkan studi/dst)', 'categorical', NULL, NULL, 'Bekerja (full time / part time)', 'fact_tracer_study', NULL, 'narrow'),
  ('masa_tunggu_bekerja',      'Masa Tunggu Kerja',                'waktu_tunggu',           'Jumlah bulan sejak lulus hingga mendapat pekerjaan pertama', 'integer', 0, 120, '3', 'fact_tracer_study', 'masa_tunggu_bekerja', 'narrow'),
  ('bulan_sebelum_lulus',      'Bulan Sebelum Lulus Mulai Cari Kerja', 'waktu_tunggu',        'Jumlah bulan sebelum lulus alumni mulai mencari kerja', 'integer', 0, 60, '2', 'fact_tracer_study', 'bulan_sebelum_lulus', 'narrow'),
  ('pendapatan',               'Pendapatan / Take Home Pay',       'pendapatan',             'Take home pay bulanan dalam Rupiah', 'integer', 100000, 999999999, '4500000', 'fact_tracer_study', 'take_home_pay', 'narrow'),
  ('relevansi_bidang',         'Kesesuaian Bidang Studi',          'kesesuaian_bidang',      'Kesesuaian BIDANG studi dengan pekerjaan (Sangat Erat..Tidak Sama Sekali)', 'categorical', NULL, NULL, 'Erat', 'dim_kesesuaian_bidang', NULL, 'narrow'),
  ('kesesuaian_level',         'Kesesuaian Level Pendidikan',      'kesesuaian_level',       'Kesesuaian LEVEL/tingkat pendidikan dengan pekerjaan -- independen dari relevansi_bidang', 'categorical', NULL, NULL, 'Tingkat yang Sama', 'dim_kesesuaian_level', NULL, 'narrow'),
  ('sumber_biaya_lanjut',      'Sumber Biaya Studi Lanjut',        'studi_lanjut',           'Sumber pembiayaan studi lanjut (jika melanjutkan pendidikan)', 'categorical', NULL, NULL, 'Beasiswa', 'dim_studi_lanjut', 'sumber_biaya', 'narrow'),
  ('pt_lanjut',                'Perguruan Tinggi Studi Lanjut',    'studi_lanjut',           'Nama perguruan tinggi tempat studi lanjut', 'text', NULL, NULL, 'Institut Teknologi Bandung', 'dim_studi_lanjut', 'perguruan_tinggi', 'narrow'),
  ('prodi_lanjut',             'Program Studi Lanjut',             'studi_lanjut',           'Nama program studi tempat studi lanjut', 'text', NULL, NULL, 'Magister Teknik Sipil', 'dim_studi_lanjut', 'program_studi', 'narrow'),
  ('provinsi_kerja',           'Provinsi Tempat Kerja',            'lokasi_kerja',           'Provinsi tempat alumni bekerja (disimpan sebagai FK id provinsi)', 'categorical', NULL, NULL, '32', 'dim_perusahaan/dim_wirausaha', NULL, 'narrow'),
  ('kota_kerja',               'Kota Tempat Kerja',                'lokasi_kerja',           'Kota tempat alumni bekerja (disimpan sebagai FK id kota)', 'categorical', NULL, NULL, '3273', 'dim_perusahaan/dim_wirausaha', NULL, 'narrow'),
  ('nama_perusahaan',          'Nama Perusahaan',                  'perusahaan',             'Nama perusahaan/instansi tempat bekerja', 'text', NULL, NULL, 'PT Telekomunikasi Indonesia', 'dim_perusahaan', 'nama_perusahaan', 'narrow'),
  ('jabatan_wirausaha',        'Jabatan Wirausaha',                'perusahaan',             'Jabatan/posisi pada usaha sendiri (jika wirausaha)', 'text', NULL, NULL, 'Pemilik', 'dim_wirausaha', 'jabatan', 'narrow'),
  ('tingkat_instansi',         'Tingkat Instansi',                 'perusahaan',             'Tingkat instansi tempat bekerja/berwirausaha (lokal/nasional/multinasional)', 'categorical', NULL, NULL, 'Nasional', 'dim_perusahaan/dim_wirausaha', 'tingkat_instansi', 'narrow'),
  ('jenis_perusahaan',         'Jenis Perusahaan',                 'perusahaan',             'Jenis/kategori perusahaan tempat bekerja', 'categorical', NULL, NULL, 'BUMN', 'dim_perusahaan', 'jenis_perusahaan', 'narrow'),
  ('sumber_biaya_studi',       'Sumber Biaya Studi (S1/Diploma)',  'biaya_studi',            'Sumber pembiayaan kuliah S1/Diploma alumni', 'categorical', NULL, NULL, 'Biaya Sendiri', 'dim_alumni', 'label_sumber_biaya_dipolban', 'narrow'),
  ('kompetensi_evaluasi',      'Evaluasi Kompetensi',              'kompetensi',             'Battery evaluasi kompetensi lulusan (f1761-f1774), satu item per question_code via dim_indikator_evaluasi', 'integer', 1, 5, '4', 'fact_range_evaluasi', NULL, 'wide'),
  ('metode_pembelajaran',      'Metode Pembelajaran',              'metode_pembelajaran',    'Evaluasi metode pembelajaran yang berkontribusi pada kompetensi (f21-f27)', 'integer', 1, 5, '4', 'fact_range_evaluasi', NULL, 'wide'),
  ('alasan_kerja_tidak_sesuai','Alasan Kerja Tidak Sesuai Bidang', 'ketidaksesuaian_kerja',  'Alasan multi-pilihan kenapa pekerjaan tidak sesuai bidang studi (f1601-f1613)', 'boolean', NULL, NULL, 'true', 'fact_multi_select', NULL, 'wide');

-- ─────────────────────────────────────────────────────────────────────────
-- question_semantic_mapping — driven from live questionnaire_questions,
-- restricted to the 3 main tracer-study questionnaires (id 1,2,3) and the
-- codes documented in OltpExtractRepository::RELEVANT_QUESTION_CODES.
-- ─────────────────────────────────────────────────────────────────────────
WITH code_role_map (question_code, semantic_role) AS (
  VALUES
    ('f8',    'status_pekerjaan'),
    ('f14',   'relevansi_bidang'),
    ('f15',   'kesesuaian_level'),
    ('f18a',  'sumber_biaya_lanjut'),
    ('f18b',  'pt_lanjut'),
    ('f18c',  'prodi_lanjut'),
    ('f302',  'bulan_sebelum_lulus'),
    ('f502',  'masa_tunggu_bekerja'),
    ('f505',  'pendapatan'),
    ('f5a1',  'provinsi_kerja'),
    ('f5a2',  'kota_kerja'),
    ('f5b',   'nama_perusahaan'),
    ('f5c',   'jabatan_wirausaha'),
    ('f5d',   'tingkat_instansi'),
    ('f1101', 'jenis_perusahaan'),
    ('f1201', 'sumber_biaya_studi'),
    ('f1761', 'kompetensi_evaluasi'), ('f1762', 'kompetensi_evaluasi'), ('f1763', 'kompetensi_evaluasi'),
    ('f1764', 'kompetensi_evaluasi'), ('f1765', 'kompetensi_evaluasi'), ('f1766', 'kompetensi_evaluasi'),
    ('f1767', 'kompetensi_evaluasi'), ('f1768', 'kompetensi_evaluasi'), ('f1769', 'kompetensi_evaluasi'),
    ('f1770', 'kompetensi_evaluasi'), ('f1771', 'kompetensi_evaluasi'), ('f1772', 'kompetensi_evaluasi'),
    ('f1773', 'kompetensi_evaluasi'), ('f1774', 'kompetensi_evaluasi'),
    ('f21', 'metode_pembelajaran'), ('f22', 'metode_pembelajaran'), ('f23', 'metode_pembelajaran'),
    ('f24', 'metode_pembelajaran'), ('f25', 'metode_pembelajaran'), ('f26', 'metode_pembelajaran'),
    ('f27', 'metode_pembelajaran'),
    ('f1601', 'alasan_kerja_tidak_sesuai'), ('f1602', 'alasan_kerja_tidak_sesuai'), ('f1603', 'alasan_kerja_tidak_sesuai'),
    ('f1604', 'alasan_kerja_tidak_sesuai'), ('f1605', 'alasan_kerja_tidak_sesuai'), ('f1606', 'alasan_kerja_tidak_sesuai'),
    ('f1607', 'alasan_kerja_tidak_sesuai'), ('f1608', 'alasan_kerja_tidak_sesuai'), ('f1609', 'alasan_kerja_tidak_sesuai'),
    ('f1610', 'alasan_kerja_tidak_sesuai'), ('f1611', 'alasan_kerja_tidak_sesuai'), ('f1612', 'alasan_kerja_tidak_sesuai'),
    ('f1613', 'alasan_kerja_tidak_sesuai')
)
INSERT INTO tracer_oltp.question_semantic_mapping
  (questionnaire_id, question_code, question_text_snapshot, semantic_role, grain, effective_date, is_active, mapped_by)
SELECT
  qq.questionnaire_id,
  qq.code,
  qq.question_text,
  crm.semantic_role,
  srr.grain,
  CURRENT_DATE,
  true,
  NULL -- system-seeded baseline, not mapped by a human admin
FROM tracer_oltp.questionnaire_questions qq
JOIN code_role_map crm ON crm.question_code = qq.code
JOIN tracer_oltp.semantic_role_registry srr ON srr.role_key = crm.semantic_role
WHERE qq.questionnaire_id IN (1, 2, 3);

-- ─────────────────────────────────────────────────────────────────────────
-- kpi_category_mapping — reproduces FactTracerStudy.js's hardcoded
-- SPLIT_PART(id_status_alumni, ':', 3) IN (...) lists and
-- KeterserapanService.php::STATUS_TERSERAP, as data.
--
-- option_code values below are f8's business option codes (stable across
-- questionnaires per StatusAlumniDimService's business-key derivation),
-- confirmed identical across questionnaire_id 1/2/3 in the live DB:
--   1 Bekerja (full time/part time)   5 Tidak kerja tetapi sedang mencari kerja
--   2 Belum memungkinkan bekerja      6 Melanjutkan pendidikan sambil bekerja
--   3 Wiraswasta                      7 Melanjutkan pendidikan sambil wiraswasta
--   4 Melanjutkan Pendidikan
-- ─────────────────────────────────────────────────────────────────────────

-- effective_date DIBACKDATE ke sebelum data historis manapun mungkin ada
-- (bukan CURRENT_DATE) -- lihat model/cubes/FactTracerStudy.js: measure
-- terserap/masa_tunggu/kesesuaian sekarang point-in-time, mencocokkan
-- kpi_category_mapping yang EFEKTIF pada tanggal snapshot masing-masing
-- baris fact (dim_waktu.tanggal_refresh), bukan sekadar is_active=true.
-- Kalau baseline seed ini pakai CURRENT_DATE, maka SEMUA fact snapshot
-- yang sudah ada SEBELUM tanggal seed dijalankan akan terlihat "belum
-- ada kategori" (effective_date baseline > tanggal snapshot lama) --
-- persis kelas bug yang justru ingin dicegah oleh point-in-time lookup.
-- '2020-01-01' merepresentasikan "definisi ini sudah berlaku sejak awal
-- sebelum sistem OLAP ini ada", bukan "baru berlaku sejak seed dijalankan".
--
-- IKU 2 Kemendikbud: terserap = bekerja (1,6) + wirausaha (3,7) + studi lanjut (4)
INSERT INTO public.kpi_category_mapping
  (semantic_role, option_code, option_label_snapshot, kpi_category, kpi_category_label, digunakan_oleh, effective_date)
VALUES
  ('status_pekerjaan', '1', 'Bekerja (full time / part time)',              'terserap', 'Terserap',        'iku2_keterserapan', '2020-01-01'),
  ('status_pekerjaan', '3', 'Wiraswasta',                                   'terserap', 'Terserap',        'iku2_keterserapan', '2020-01-01'),
  ('status_pekerjaan', '4', 'Melanjutkan Pendidikan',                       'terserap', 'Terserap',        'iku2_keterserapan', '2020-01-01'),
  ('status_pekerjaan', '6', 'Melanjutkan pendidikan sambil bekerja',        'terserap', 'Terserap',        'iku2_keterserapan', '2020-01-01'),
  ('status_pekerjaan', '7', 'Melanjutkan pendidikan sambil wiraswasta',     'terserap', 'Terserap',        'iku2_keterserapan', '2020-01-01'),
  ('status_pekerjaan', '2', 'Belum memungkinkan bekerja',                   'tidak',    'Tidak Terserap',  'iku2_keterserapan', '2020-01-01'),
  ('status_pekerjaan', '5', 'Tidak kerja tetapi sedang mencari kerja',      'tidak',    'Tidak Terserap',  'iku2_keterserapan', '2020-01-01'),

  -- Eligible for masa-tunggu KPI: bekerja/wirausaha only (excludes studi lanjut '4' -- has no masa_tunggu_bekerja value)
  ('status_pekerjaan', '1', 'Bekerja (full time / part time)',              'valid', 'Dihitung Masa Tunggu', 'masa_tunggu_valid_status', '2020-01-01'),
  ('status_pekerjaan', '3', 'Wiraswasta',                                   'valid', 'Dihitung Masa Tunggu', 'masa_tunggu_valid_status', '2020-01-01'),
  ('status_pekerjaan', '6', 'Melanjutkan pendidikan sambil bekerja',        'valid', 'Dihitung Masa Tunggu', 'masa_tunggu_valid_status', '2020-01-01'),
  ('status_pekerjaan', '7', 'Melanjutkan pendidikan sambil wiraswasta',     'valid', 'Dihitung Masa Tunggu', 'masa_tunggu_valid_status', '2020-01-01'),

  -- Eligible for kesesuaian-bidang KPI: bekerja statuses only (1,6)
  ('status_pekerjaan', '1', 'Bekerja (full time / part time)',              'valid', 'Dihitung Kesesuaian Bidang', 'kesesuaian_bidang_employed_status', '2020-01-01'),
  ('status_pekerjaan', '6', 'Melanjutkan pendidikan sambil bekerja',        'valid', 'Dihitung Kesesuaian Bidang', 'kesesuaian_bidang_employed_status', '2020-01-01'),

  -- Kesesuaian bidang relevance bucket (option_code '3' "Cukup Erat" intentionally
  -- left uncategorized here -- matches the original SQL, which only checked IN('1','2') / IN('4','5'))
  ('relevansi_bidang', '1', 'Sangat Erat',      'sesuai',        'Sesuai Bidang',     'kesesuaian_bidang_relevance', '2020-01-01'),
  ('relevansi_bidang', '2', 'Erat',             'sesuai',        'Sesuai Bidang',     'kesesuaian_bidang_relevance', '2020-01-01'),
  ('relevansi_bidang', '4', 'Kurang Erat',      'tidak_sesuai',  'Tidak Sesuai Bidang','kesesuaian_bidang_relevance', '2020-01-01'),
  ('relevansi_bidang', '5', 'Tidak Sama Sekali','tidak_sesuai',  'Tidak Sesuai Bidang','kesesuaian_bidang_relevance', '2020-01-01');

COMMIT;
