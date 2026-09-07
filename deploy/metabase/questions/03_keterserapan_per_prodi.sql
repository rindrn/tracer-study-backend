SELECT
  dim_prodi.nama_prodi,
  dim_status_alumni.label AS status_alumni,
  COUNT(*) AS jumlah_alumni,
  ROUND(100.0 * COUNT(*) / SUM(COUNT(*)) OVER (PARTITION BY dim_prodi.nama_prodi), 1) AS persentase
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_status_alumni ON fact_tracer_study.status_alumni_sk = dim_status_alumni.status_alumni_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi, dim_status_alumni.label
ORDER BY dim_prodi.nama_prodi, jumlah_alumni DESC
