SELECT
  dim_prodi.nama_prodi,
  ROUND(AVG(fact_tracer_study.masa_tunggu_bekerja), 1) AS rata_masa_tunggu_bekerja,
  ROUND(100.0 * COUNT(*) FILTER (
    WHERE dim_status_alumni.label IN ('Tidak kerja tetapi sedang mencari kerja', 'Belum memungkinkan bekerja')
  ) / COUNT(*), 1) AS persentase_tidak_terserap,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
JOIN dim_status_alumni ON fact_tracer_study.status_alumni_sk = dim_status_alumni.status_alumni_sk
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi
ORDER BY persentase_tidak_terserap DESC, rata_masa_tunggu_bekerja DESC
