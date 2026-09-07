SELECT
  dim_perusahaan.label_tingkat_instansi,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_perusahaan ON fact_tracer_study.perusahaan_sk = dim_perusahaan.perusahaan_sk
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE dim_perusahaan.label_tingkat_instansi IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_perusahaan.label_tingkat_instansi
ORDER BY jumlah_alumni DESC
