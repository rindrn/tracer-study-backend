# Rencana Metabase: Analisis Multi-Dimensi Tracer Study

## Context

Ada dokumen plan (gaya laporan implementasi) yang mengusulkan setup Metabase sebagai
alat eksplorasi data bebas di atas data warehouse OLAP tracer study, lengkap dengan 8
pertanyaan starter dan skrip provisioning otomatis. Sebelum menindaklanjuti, dilakukan
verifikasi menyeluruh terhadap semua klaim teknisnya di repo `tracer-study-backend` dan
mesin produksi ini. Hasilnya: **sebagian besar premis arsitektur di plan itu sudah tidak
berlaku / tidak pernah ada**, tapi tujuan dan sebagian besar detail lain (skema, 8 query,
prinsip provisioning) valid dan layak dilanjutkan — dengan penyesuaian besar pada bagian
infrastruktur.

### Yang terverifikasi BENAR dari plan awal

- Skema OLAP di `database/dump/olap_schema.sql`: ke-12 tabel yang disebut
  (`fact_tracer_study`, 10 `dim_*`, `fact_range_evaluasi`, `fact_multi_select`) semua ada
  persis dengan nama itu, relasi fact↔dimensi memang FK Postgres asli (bukan konvensi
  penamaan).
- Semua nama kolom yang dipakai 8 query starter (`tahun_lulus`, `nama_prodi`,
  `masa_tunggu_bekerja`, `masa_tunggu_wirausaha`, `take_home_pay`, `nilai_ump`,
  `nama_provinsi`, `label_jenis_perusahaan`, `grup_gap`, `skor`, `sumber_biaya`,
  `perguruan_tinggi`, dst) valid, tidak ada typo/nama salah.
- Alasan TIDAK membuat starter card "Response Rate" benar: `dim_alumni` cuma diisi ETL
  dari alumni yang sudah *submit* respons (`EtlOrchestratorService::getSubmittedResponsesSince`),
  jadi tidak ada penyebut "total terdaftar" di sisi OLAP — response rate memang harus
  tetap dihitung dari OLTP (`ResponseRateController`).
- Prinsip desain provisioning (idempoten, native SQL tersimpan di git, Field Filter
  template tag untuk filter dashboard global) tetap ide yang bagus dan reusable.

### Yang ternyata sudah usang / tidak cocok dengan kenyataan repo ini

1. **Direktori `metabase/` yang dideskripsikan plan awal** (docker-compose.yml, sql/,
   questions/, setup/provision.mjs, dst) **tidak ada sama sekali** — bukan di disk,
   bukan di git history. Seluruh narasi "Status Pengujian" di plan awal (role sudah
   dibuat & diverifikasi, compose sudah divalidasi) tidak match apapun yang nyata ada.
2. **Metabase justru sudah berjalan di production**, bukan cuma "percobaan manual java
   -jar yang sudah dibersihkan" seperti dinarasikan plan awal. Ada service `metabase`
   utuh di `deploy/docker-compose.yml` (image `metabase/metabase:latest`, network
   `internal`, `mem_limit: 512m`, container `smarttracer-metabase-1`), dan
   `deploy/nginx/default.conf` sudah punya vhost TLS lengkap di `analyst.smart-tracer.id`
   yang proxy ke `metabase:3000`. Perubahan ini sekarang sudah ter-commit (checkpoint
   commit `14534c9`), sebelumnya sempat jadi perubahan working-tree yang belum tersimpan.
3. **Premis jaringan plan awal salah untuk environment ini**: Postgres di sini tidak
   listen di host `127.0.0.1:5432` sama sekali — hanya reachable via Docker network
   internal (`postgres:5432`). Jadi seluruh justifikasi `network_mode: host` di plan
   awal tidak relevan; service `metabase` yang sudah ada malah benar caranya: satu
   network Docker (`internal`) dengan `postgres`, connect via hostname service, bukan
   IP host.
4. **Nama sibling repo salah**: bukan `tracer-study-analytics`, tapi
   `/opt/tracer-study/cube/tracer-analytics/model/cubes/`.

### Temuan baru di luar cakupan plan awal — celah keamanan nyata yang sudah live

Plan awal sangat menekankan prinsip "role terpisah, read-only, bukan kredensial
admin/aplikasi" — tapi setup yang **sudah jalan sekarang justru melanggar prinsip itu
sendiri**: dicek langsung ke cluster Postgres (`\du`), **hanya ada satu role**,
`smarttracer`, dan role itu **superuser penuh** (`Superuser, Create role, Create DB,
Replication, Bypass RLS`). Service `metabase` yang sudah berjalan dikonfigurasi persis
dengan role itu:

```yaml
MB_DB_USER: smarttracer
MB_DB_PASS: ${POSTGRES_PASSWORD}
```

Karena cuma ada satu role di cluster ini, data source analitik apa pun yang
sudah/akan dikonfigurasi analis di dalam UI Metabase (Admin > Databases, untuk query
tabel `fact_tracer_study` dkk) pasti pakai role yang sama juga — tidak ada pilihan
lain. Artinya siapa pun yang login ke `https://analyst.smart-tracer.id` (sudah
ter-expose publik lewat nginx+TLS) berpotensi punya akses baca **dan tulis** ke
seluruh database, termasuk skema OLTP yang berisi PII alumni — bukan cuma baca OLAP
seperti niat plan awal. **Ini harus diperbaiki lebih dulu**, sebelum menambah starter
questions apa pun.

## Rencana yang direkomendasikan

Bangun di atas infrastruktur yang sudah live, tidak perlu bikin ulang stack Docker
terpisah. Urutan prioritas:

### 1. Perbaiki pemisahan kredensial (prioritas utama, isu keamanan live)

- Buat role Postgres baru read-only, mis. `metabase_reader`, lewat SQL manual (mirip
  ide `metabase_reader_role.sql` di plan awal — itu bagian yang tetap valid):
  `GRANT SELECT` ke seluruh tabel schema `public` (OLAP) + `ALTER DEFAULT PRIVILEGES`
  supaya tabel baru otomatis ter-cover. **Jangan** beri akses ke schema OLTP
  (`tracer_oltp`) sama sekali — role ini murni untuk analisis OLAP.
- Simpan file SQL ini di `deploy/metabase/sql/metabase_reader_role.sql` (lihat struktur
  folder di bagian 3), dijalankan manual sekali oleh admin lewat `docker compose exec
  postgres psql -U smarttracer -d tracer_study -f ...` — skrip provisioning tidak boleh
  pegang kredensial superuser.
- **Tindakan manual di UI Metabase** (tidak bisa lewat file/migration): masuk ke
  `https://analyst.smart-tracer.id` sebagai admin → Admin settings → Databases → cek
  data source yang dipakai untuk query `tracer_study` (kemungkinan besar juga pakai
  `smarttracer`) → ganti ke `metabase_reader`.
- `MB_DB_*` di `docker-compose.yml` (backing-store metadata Metabase sendiri: dashboard
  tersimpan, user, dll — bukan data source analitik) boleh tetap terpisah di database
  `metabase`, tapi idealnya juga tidak pakai role superuser aplikasi — pertimbangkan
  role ketiga khusus metadata Metabase, atau minimal dicatat sebagai risiko yang
  diterima kalau tidak diubah sekarang.

### 2. Commit perubahan infra yang sudah live — selesai

`deploy/docker-compose.yml` (service `metabase`) dan `deploy/nginx/default.conf`
(vhost `analyst.smart-tracer.id`) sudah ter-commit (commit `14534c9`). Konsekuensinya:
perbaikan kredensial di langkah 1 (kalau berupa perubahan `MB_DB_USER`/`MB_DB_PASS` di
`docker-compose.yml`) akan jadi commit susulan terpisah.

### 3. Tambah starter questions + provisioning script, disambungkan ke instance yang sudah jalan

Bikin folder baru `deploy/metabase/` (bukan `metabase/` di root repo seperti plan awal
— folder ini sejajar dengan compose file yang benar-benar mendefinisikan service-nya):

```
deploy/metabase/
├── sql/
│   └── metabase_reader_role.sql
├── questions/
│   ├── manifest.json
│   └── 01..08_*.sql            # 8 file, isi & kolom sesuai rancangan plan awal (sudah tervalidasi)
└── setup/
    └── provision.mjs           # target: https://analyst.smart-tracer.id (bukan localhost:3001 baru)
```

- Isi 8 pertanyaan & struktur `manifest.json` ikuti persis rancangan di plan awal
  (semua kolom sudah diverifikasi valid) — tidak perlu didesain ulang dari nol.
- `provision.mjs` ikuti alur API yang sama (idempoten: cek-dulu-baru-buat, admin login
  pakai kredensial existing instance, bukan `POST /api/setup` karena instance ini sudah
  pernah di-setup — langkah setup admin diganti jadi "skip kalau sudah ter-setup,
  langsung login").
- Field Filter (`{{tahun_lulus}}`, `{{prodi}}`) dan layout dashboard 2 kolom (chart +
  kartu insight teks) — desainnya tetap dipakai, valid.
- Ganti semua referensi path Cube.js di dokumentasi/komentar ke path yang benar:
  `/opt/tracer-study/cube/tracer-analytics/model/cubes/`.
- Bagian "Kalau Ingin Dipindah ke Produksi Nanti" dari plan awal sudah tidak relevan
  (sudah di produksi). Soal RAM: service `metabase` sudah diberi `mem_limit: 512m` di
  compose yang sudah jalan, `JAVA_OPTS=-Xmx384m` — konsisten dengan batas VPS 2GB,
  tidak perlu diubah.

## Delapan pertanyaan starter (dari plan awal, kolom sudah divalidasi)

| # | Pertanyaan | Dimensi yang digabung |
|---|---|---|
| 1 | Masa Tunggu Kerja per Prodi & Tahun Lulus | `dim_prodi` x `dim_alumni.tahun_lulus` x AVG(`masa_tunggu_bekerja`) |
| 2 | Pola Gaji vs Lokasi Bekerja | `dim_perusahaan.nama_provinsi` x `dim_ump.nilai_ump` x AVG(`take_home_pay`) x rasio gaji/UMP |
| 3 | Keterserapan Kerja per Prodi | `dim_status_alumni.label` x `dim_prodi` (persentase per status) |
| 4 | Kesesuaian Bidang & Level Kerja vs Prodi | `dim_kesesuaian_bidang` x `dim_kesesuaian_level` x `dim_prodi` |
| 5 | Sebaran Instansi (Geografis & Jenis) | `dim_perusahaan.nama_provinsi` x `label_jenis_perusahaan` |
| 6 | Wirausaha: Sebaran & Masa Tunggu | `dim_wirausaha.nama_provinsi` x `dim_prodi` x AVG(`masa_tunggu_wirausaha`) |
| 7 | Kompetensi Gap & Metode Pembelajaran per Prodi | `fact_range_evaluasi` x `dim_indikator_evaluasi.grup_gap` x `dim_prodi`, AVG(`skor`) |
| 8 | Pembiayaan Studi Lanjut | `dim_studi_lanjut.sumber_biaya` x `perguruan_tinggi` |

**Sengaja tidak dibuatkan starter card**: Response Rate (lihat penjelasan di bagian
"Yang terverifikasi BENAR" di atas — penyebutnya tidak ada di OLAP).

## Verifikasi

- `docker compose exec postgres psql -U smarttracer -d tracer_study -c "\du"` → role
  `metabase_reader` muncul, tanpa atribut `Superuser`/`Createrole`/`Createdb`.
- Coba `SELECT`/`INSERT` manual pakai role baru itu → SELECT ke tabel OLAP berhasil,
  INSERT/UPDATE/DELETE ditolak, akses ke schema `tracer_oltp` ditolak.
- Login ke `https://analyst.smart-tracer.id` → Admin > Databases → pastikan data
  source query tracer study memakai `metabase_reader`, bukan `smarttracer`.
- Jalankan `node deploy/metabase/setup/provision.mjs` → 8 card + dashboard muncul di
  Collection "Template Analisis Tracer Study" di instance yang sudah jalan; jalankan
  2x untuk pastikan idempoten (tidak dobel card/dashboard).
- Buka dashboard, ganti filter Tahun Lulus/Program Studi → pastikan semua 8 chart ikut
  ter-filter (Field Filter bekerja).

### File yang disentuh/dibuat

- `deploy/metabase/sql/metabase_reader_role.sql` (baru)
- `deploy/metabase/questions/manifest.json` + 8 file `.sql` (baru)
- `deploy/metabase/setup/provision.mjs` (baru)
- `deploy/docker-compose.yml` (kemungkinan sesuaikan kredensial `MB_DB_*` kalau role
  metadata terpisah dipilih)
