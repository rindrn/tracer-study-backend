SELECT
  dim_prodi.nama_prodi,
  ROUND(AVG(fact_tracer_study.bulan_sebelum_lulus) FILTER (WHERE fact_tracer_study.bulan_sebelum_lulus > 0), 1) AS rata_bulan_sebelum_lulus,
  ROUND(AVG(fact_tracer_study.bulan_sesudah_lulus) FILTER (WHERE fact_tracer_study.bulan_sesudah_lulus > 0), 1) AS rata_bulan_sesudah_lulus
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi
ORDER BY rata_bulan_sesudah_lulus DESC
