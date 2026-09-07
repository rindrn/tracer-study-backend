SELECT
  dim_prodi.nama_prodi,
  dim_indikator_evaluasi.grup_gap,
  AVG(fact_range_evaluasi.skor) AS rata_skor,
  COUNT(*) AS jumlah_jawaban
FROM fact_range_evaluasi
JOIN dim_prodi ON fact_range_evaluasi.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_range_evaluasi.id_alumni = dim_alumni.id_alumni
JOIN dim_indikator_evaluasi ON fact_range_evaluasi.id_indikator_evaluasi = dim_indikator_evaluasi.id_indikator_evaluasi
WHERE dim_indikator_evaluasi.grup_gap IS NOT NULL
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi, dim_indikator_evaluasi.grup_gap
ORDER BY rata_skor ASC
LIMIT 10
