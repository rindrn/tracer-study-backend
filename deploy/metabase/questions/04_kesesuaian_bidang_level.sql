SELECT
  dim_prodi.nama_prodi,
  dim_kesesuaian_bidang.label AS kesesuaian_bidang,
  dim_kesesuaian_level.label AS kesesuaian_level,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
LEFT JOIN dim_kesesuaian_bidang ON fact_tracer_study.kesesuaian_bidang_sk = dim_kesesuaian_bidang.kesesuaian_bidang_sk
LEFT JOIN dim_kesesuaian_level ON fact_tracer_study.kesesuaian_level_sk = dim_kesesuaian_level.kesesuaian_level_sk
WHERE (dim_kesesuaian_bidang.label IS DISTINCT FROM 'Tidak Ada Data'
       OR dim_kesesuaian_level.label IS DISTINCT FROM 'Tidak Ada Data')
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi, dim_kesesuaian_bidang.label, dim_kesesuaian_level.label
ORDER BY dim_prodi.nama_prodi, jumlah_alumni DESC
