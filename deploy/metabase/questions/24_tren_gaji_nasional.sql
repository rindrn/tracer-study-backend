SELECT
  dim_alumni.tahun_lulus,
  AVG(fact_tracer_study.take_home_pay) AS rata_take_home_pay,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
WHERE fact_tracer_study.take_home_pay IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_alumni.tahun_lulus
ORDER BY dim_alumni.tahun_lulus
