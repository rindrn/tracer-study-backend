-- jenis_perusahaan selain 6 label resmi (termasuk teks bebas hasil isian
-- "Lainnya, tuliskan", lihat AnswerResolverService::applyCompanionSubstitutions())
-- dikelompokkan jadi satu bucket "Lainnya". Field ini TIDAK punya pengaman
-- bucketing di sisi frontend (beda dari label_sumber_biaya_dipolban) --
-- pertanyaan ini SATU-SATUNYA yang membatasi kardinalitasnya, jangan dihapus.
SELECT
  dim_perusahaan.nama_provinsi,
  CASE
    WHEN dim_perusahaan.label_jenis_perusahaan IN (
      'Instansi pemerintah', 'Organisasi non-profit/Lembaga Swadaya Masyarakat',
      'Perusahaan swasta', 'Wiraswasta/perusahaan sendiri', 'BUMN/BUMD',
      'Institusi/Organisasi Multilateral'
    ) THEN dim_perusahaan.label_jenis_perusahaan
    ELSE 'Lainnya'
  END AS label_jenis_perusahaan,
  COUNT(*) AS jumlah_alumni
FROM fact_tracer_study
JOIN dim_perusahaan ON fact_tracer_study.perusahaan_sk = dim_perusahaan.perusahaan_sk
JOIN dim_prodi ON fact_tracer_study.prodi_sk = dim_prodi.prodi_sk
JOIN dim_alumni ON fact_tracer_study.id_alumni = dim_alumni.id_alumni
WHERE 1 = 1
  [[AND dim_alumni.tahun_lulus = {{tahun_lulus}}]]
  [[AND dim_prodi.nama_prodi = {{prodi}}]]
GROUP BY
  dim_perusahaan.nama_provinsi,
  CASE
    WHEN dim_perusahaan.label_jenis_perusahaan IN (
      'Instansi pemerintah', 'Organisasi non-profit/Lembaga Swadaya Masyarakat',
      'Perusahaan swasta', 'Wiraswasta/perusahaan sendiri', 'BUMN/BUMD',
      'Institusi/Organisasi Multilateral'
    ) THEN dim_perusahaan.label_jenis_perusahaan
    ELSE 'Lainnya'
  END
ORDER BY jumlah_alumni DESC
