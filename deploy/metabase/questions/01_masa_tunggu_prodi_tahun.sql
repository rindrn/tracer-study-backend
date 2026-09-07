SELECT
  dim_prodi.nama_prodi,
  dim_alumni.tahun_lulus,
  AVG(fact_tracer_study.masa_tunggu_bekerja) AS rata_masa_tunggu_bekerja,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE fact_tracer_study.masa_tunggu_bekerja IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi, dim_alumni.tahun_lulus
ORDER BY dim_alumni.tahun_lulus, dim_prodi.nama_prodi
