SELECT
  CASE
    WHEN fact_tracer_study.masa_tunggu_bekerja <= 3 THEN '0-3 Bulan'
    WHEN fact_tracer_study.masa_tunggu_bekerja <= 6 THEN '4-6 Bulan'
    WHEN fact_tracer_study.masa_tunggu_bekerja <= 9 THEN '7-9 Bulan'
    ELSE '10-12 Bulan'
  END AS kelompok_masa_tunggu,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE fact_tracer_study.masa_tunggu_bekerja IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY kelompok_masa_tunggu
ORDER BY MIN(fact_tracer_study.masa_tunggu_bekerja)
