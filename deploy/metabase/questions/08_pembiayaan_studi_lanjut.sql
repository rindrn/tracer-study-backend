SELECT
  dim_studi_lanjut.perguruan_tinggi,
  dim_studi_lanjut.sumber_biaya,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_studi_lanjut ON fact_tracer_study.id_studi_lanjut = dim_studi_lanjut.id_studi_lanjut
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE dim_studi_lanjut.perguruan_tinggi IS DISTINCT FROM 'Tidak Ada Data'
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_studi_lanjut.perguruan_tinggi, dim_studi_lanjut.sumber_biaya
ORDER BY jumlah_alumni DESC
