SELECT
  dim_prodi.nama_prodi,
  COUNT(*) FILTER (WHERE fact_tracer_study.bulan_sebelum_lulus IS NOT NULL AND fact_tracer_study.bulan_sebelum_lulus > 0) AS dapat_kerja_sebelum_lulus,
  COUNT(*) FILTER (WHERE fact_tracer_study.bulan_sesudah_lulus IS NOT NULL AND fact_tracer_study.bulan_sesudah_lulus > 0) AS dapat_kerja_sesudah_lulus
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi
ORDER BY dapat_kerja_sebelum_lulus DESC
