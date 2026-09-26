SELECT
  dim_kesesuaian_bidang.label AS kesesuaian_bidang,
  AVG(fact_tracer_study.take_home_pay) AS rata_take_home_pay,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_kesesuaian_bidang ON fact_tracer_study.kesesuaian_bidang_sk = dim_kesesuaian_bidang.kesesuaian_bidang_sk
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE fact_tracer_study.take_home_pay IS NOT NULL
  AND dim_kesesuaian_bidang.label IS DISTINCT FROM 'Tidak Ada Data'
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_kesesuaian_bidang.label
ORDER BY rata_take_home_pay DESC
