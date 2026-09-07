SELECT
  dim_perusahaan.nama_provinsi,
  AVG(fact_tracer_study.take_home_pay) AS rata_take_home_pay,
  AVG(dim_ump.nilai_ump) AS rata_ump,
  AVG(fact_tracer_study.take_home_pay::numeric / NULLIF(dim_ump.nilai_ump, 0)) AS rasio_gaji_ump,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_perusahaan ON fact_tracer_study.perusahaan_sk = dim_perusahaan.perusahaan_sk
LEFT JOIN dim_ump ON fact_tracer_study.ump_sk = dim_ump.ump_sk
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE fact_tracer_study.take_home_pay IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_perusahaan.nama_provinsi
ORDER BY rata_take_home_pay DESC
