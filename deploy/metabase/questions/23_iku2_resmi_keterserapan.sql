SELECT
  dim_prodi.nama_prodi,
  COUNT(*) AS total_alumni,
  ROUND(100.0 * COUNT(*) FILTER (WHERE kpi_category_mapping.kpi_category = 'terserap') / COUNT(*), 1) AS persentase_terserap
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
JOIN dim_status_alumni ON fact_tracer_study.status_alumni_sk = dim_status_alumni.status_alumni_sk
LEFT JOIN kpi_category_mapping
  ON kpi_category_mapping.semantic_role = 'status_pekerjaan'
  AND kpi_category_mapping.option_code = split_part(dim_status_alumni.id_status_alumni, ':', 3)
  AND kpi_category_mapping.digunakan_oleh = 'iku2_keterserapan'
  AND kpi_category_mapping.is_active = true
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi
ORDER BY persentase_terserap ASC
