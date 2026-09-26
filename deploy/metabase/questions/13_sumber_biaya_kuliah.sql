-- Sumber pembiayaan kuliah selain 6 label resmi (termasuk teks bebas hasil
-- isian "Lainnya, tuliskan", lihat AnswerResolverService::applyCompanionSubstitutions())
-- dikelompokkan jadi satu bucket "Lainnya" -- konsisten dengan
-- OFFICIAL_SOURCES/normalizeLabel() di Kpi11FundingSourceChart.tsx & ComparePage.tsx,
-- supaya chart ini tidak pecah jadi puluhan slice begitu label_sumber_biaya_dipolban
-- mulai berisi teks bebas per-alumni.
SELECT
  CASE
    WHEN dim_alumni.label_sumber_biaya_dipolban IN (
      'Biaya Sendiri/Keluarga', 'Beasiswa BIDIKMISI', 'Beasiswa PPA',
      'Beasiswa Perusahaan/Swasta', 'Beasiswa AFIRMASI', 'Beasiswa ADIK'
    ) THEN dim_alumni.label_sumber_biaya_dipolban
    ELSE 'Lainnya'
  END AS sumber_biaya_kuliah,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
WHERE dim_alumni.label_sumber_biaya_dipolban IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY
  CASE
    WHEN dim_alumni.label_sumber_biaya_dipolban IN (
      'Biaya Sendiri/Keluarga', 'Beasiswa BIDIKMISI', 'Beasiswa PPA',
      'Beasiswa Perusahaan/Swasta', 'Beasiswa AFIRMASI', 'Beasiswa ADIK'
    ) THEN dim_alumni.label_sumber_biaya_dipolban
    ELSE 'Lainnya'
  END
ORDER BY jumlah_alumni DESC
