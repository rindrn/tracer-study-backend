SELECT
  dim_alumni.tahun_lulus,
  dim_status_alumni.label AS status_alumni,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
JOIN dim_status_alumni ON fact_tracer_study.status_alumni_sk = dim_status_alumni.status_alumni_sk
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_alumni.tahun_lulus, dim_status_alumni.label
ORDER BY dim_alumni.tahun_lulus
