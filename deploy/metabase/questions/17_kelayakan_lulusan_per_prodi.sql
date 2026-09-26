SELECT
  dim_prodi.nama_prodi,
  COUNT(*) AS total_alumni,
  ROUND(100.0 * COUNT(*) FILTER (
    WHERE (dim_status_alumni.label = 'Bekerja (full time / part time)' AND fact_tracer_study.flag_above_ump = 1)
       OR dim_status_alumni.label IN ('Wiraswasta', 'Melanjutkan Pendidikan')
  ) / COUNT(*), 1) AS persentase_layak
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
JOIN dim_status_alumni ON fact_tracer_study.status_alumni_sk = dim_status_alumni.status_alumni_sk
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi
ORDER BY persentase_layak ASC
