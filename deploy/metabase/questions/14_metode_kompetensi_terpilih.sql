SELECT
  dim_prodi.nama_prodi,
  dim_indikator_evaluasi.kategori_pertanyaan,
  dim_indikator_evaluasi.label_pertanyaan,
  COUNT(*) AS jumlah_dipilih
FROM fact_multi_select
JOIN dim_prodi ON fact_multi_select.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_multi_select.id_alumni = dim_alumni.id_alumni
JOIN dim_indikator_evaluasi ON fact_multi_select.id_indikator_evaluasi = dim_indikator_evaluasi.id_indikator_evaluasi
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY dim_prodi.nama_prodi, dim_indikator_evaluasi.kategori_pertanyaan, dim_indikator_evaluasi.label_pertanyaan
ORDER BY jumlah_dipilih DESC
