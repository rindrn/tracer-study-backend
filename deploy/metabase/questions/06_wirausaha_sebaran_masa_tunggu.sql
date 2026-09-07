SELECT
  dim_wirausaha.nama_provinsi,
  dim_prodi.nama_prodi,
  AVG(fact_tracer_study.masa_tunggu_wirausaha) AS rata_masa_tunggu_wirausaha,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_wirausaha ON fact_tracer_study.wirausaha_sk = dim_wirausaha.wirausaha_sk
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE fact_tracer_study.wirausaha_sk IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_wirausaha.nama_provinsi, dim_prodi.nama_prodi
ORDER BY jumlah_alumni DESC
