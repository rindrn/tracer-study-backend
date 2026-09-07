SELECT
  CASE fact_tracer_study.flag_above_ump
    WHEN 1 THEN 'Di Atas/Sama Dengan UMP'
    WHEN 0 THEN 'Di Bawah UMP'
    ELSE 'Tidak Diketahui'
  END AS kategori_ump,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY kategori_ump
ORDER BY jumlah_alumni DESC
