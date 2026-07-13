# Pemetaan Semantik Dinamis — Dokumentasi Arsitektur & Perubahan

> Status: implementasi selesai, diverifikasi terhadap database dev live —
> termasuk auto-trigger ETL (§10), cache invalidation (§11), dan snapshot
> unik berbasis `id_waktu` (§12).
> Repo yang terdampak: `tracer-study-backend` (ETL + API + queue), `tracer-study-analytics` (Cube.js), `fe-tracer-study` (admin UI + dashboard).

## Daftar isi

1. [Latar belakang: masalah yang mendorong perubahan ini](#1-latar-belakang)
2. [Tantangan desain yang dihadapi, dan bagaimana masing-masing diselesaikan](#2-tantangan-desain)
3. [Arsitektur akhir](#3-arsitektur-akhir)
4. [Alur validasi — dan kenapa setiap langkahnya ada](#4-alur-validasi)
5. [Perubahan per repo](#5-perubahan-per-repo)
6. [Keunggulan dibanding kode sebelumnya](#6-keunggulan-dibanding-kode-sebelumnya)
7. [Verifikasi non-regresi](#7-verifikasi-non-regresi)
8. [Bug yang ditemukan selama proses, dan apa yang bisa dipelajari darinya](#8-bug-yang-ditemukan-selama-proses)
9. [Ambang threshold dinamis: masa tunggu & UMP mengikuti LAM terpilih](#9-ambang-threshold-dinamis)
10. [Auto-trigger ETL & status tracking (Langkah 1)](#10-auto-trigger-etl)
11. [Cache invalidation — kenapa dashboard sempat tidak update setelah mapping berubah](#11-cache-invalidation)
12. [Snapshot unik: `id_waktu` menggantikan teks `minggu_snapshot`](#12-snapshot-unik)
13. [Yang belum selesai](#13-yang-belum-selesai)

## 1. Latar belakang

Sistem tracer study ini menggunakan arsitektur OLTP → ETL → OLAP: jawaban kuesioner alumni disimpan sebagai EAV (`question_code` + `answer_text`) di `tracer_oltp.response_answers`, lalu proses ETL memindahkan subset yang relevan ke star schema OLAP (`fact_tracer_study`, `dim_status_alumni`, dst) yang dibaca Cube.js untuk dashboard.

Sebelum perubahan ini, definisi "question_code apa berarti apa" dan "status apa masuk kategori KPI apa" **hardcode di empat tempat berbeda, di dua repo**:

| # | Lokasi | Bentuk hardcode |
|---|---|---|
| 1 | `OltpExtractRepository::RELEVANT_QUESTION_CODES` | Array PHP ~40 `question_code` yang ditarik dari OLTP |
| 2 | `AlumniFactBuilderService` | Pembacaan key literal `$resolved['f502']` dst untuk mengisi kolom fact |
| 3 | `FactTracerStudy.js` (Cube.js) | `SPLIT_PART(id_status_alumni,':',3) IN ('1','3','4','6','7')` di 8 measure |
| 4 | `KeterserapanService::STATUS_TERSERAP` | Salinan ke-4, berbasis label, ditemukan saat eksplorasi — tidak terdokumentasi di mana pun sebelumnya |

**Masalah intinya bukan "hardcode itu buruk"** — masalahnya adalah *empat salinan independen dari fakta yang sama*, dua di antaranya di repo terpisah. Menambah satu status baru ke KPI Terserap berarti mengedit empat file, dua repo, deploy ulang Cube.js, dengan risiko nyata satu tempat terlewat sehingga angka KPI di dashboard yang berbeda saling tidak konsisten — sebuah kegagalan yang sulit terdeteksi karena tidak ada error, hanya angka yang diam-diam salah.

Kebutuhan yang muncul dari sini ada dua, dan keduanya membentuk seluruh desain di bawah:

- **Dinamis tanpa redeploy** — menambah status/kategori KPI baru harus jadi perubahan data (lewat UI), bukan perubahan kode.
- **Tidak boleh mengubah skema yang sudah ada** — solusi harus murni aditif, karena `fact_tracer_study` dan seluruh star schema sudah diproduksi dan dikonsumsi banyak dashboard.

---

## 2. Tantangan desain

Bagian ini menjelaskan lima titik keputusan yang tidak trivial, dan alasan di baliknya — karena "kenapa didesain begini" sering lebih penting untuk didokumentasikan daripada "apa yang dibangun".

### 2.1 Di mana tabel baru harus hidup — dua schema, satu database

Database ini secara fisik satu instance Postgres, tapi dibagi dua schema: `tracer_oltp` (data transaksional + config admin seperti `lam_versions`) dan `public` (star schema OLAP, satu-satunya schema yang dibaca Cube.js).

**Tantangan:** `kpi_category_mapping` perlu dibaca langsung oleh Cube.js lewat SQL mentah (bukan lewat API Laravel) — jadi ia *harus* ada di schema `public`. Tapi `question_semantic_mapping` mereferensikan `questionnaire_id` yang hanya bermakna di schema `tracer_oltp`.

**Keputusan:** tabel dipecah sesuai siapa konsumennya, bukan disatukan demi kerapian. `semantic_role_registry` + `question_semantic_mapping` → `tracer_oltp` (dibaca ETL Laravel). `kpi_category_mapping` + `etl_anomaly_log` → `public` (dibaca Cube.js langsung). Dan yang lebih penting: **`kpi_category_mapping.semantic_role` sengaja TIDAK diberi foreign key** ke `semantic_role_registry`, walau secara teknis bisa (satu database fisik yang sama). Alasannya: Cube.js hanya butuh hak `SELECT` ke schema `public`; memberinya FK ke `tracer_oltp` — meski hanya metadata constraint — mengikis batas keamanan yang menjadi alasan kedua schema itu dipisahkan sejak awal. Validitas referensial dipindah ke lapisan aplikasi Laravel, yang memang satu-satunya pihak yang perlu melihat kedua schema sekaligus.

### 2.2 Constraint uniqueness yang tidak bisa langsung dinyatakan

**Tantangan:** Constraint A ("satu role *narrow* maksimal satu kode aktif per kuesioner") seharusnya hanya berlaku untuk role dengan `grain='narrow'` — role *wide* (battery kompetensi, dst) sengaja boleh punya banyak kode aktif untuk role yang sama. Cara paling jujur menyatakan ini adalah index parsial dengan predicate yang mengacu ke `semantic_role_registry.grain`. Tapi Postgres mewajibkan predicate index parsial bersifat *immutable* — tidak boleh melibatkan subquery ke tabel lain.

**Keputusan:** kolom `grain` **didenormalisasi** — disalin dari registry ke `question_semantic_mapping` pada saat baris ditulis, oleh lapisan aplikasi (bukan trigger). Ini satu-satunya cara index parsial berikut bisa benar-benar berjalan:

```sql
CREATE UNIQUE INDEX uq_qsm_active_narrow_role
  ON tracer_oltp.question_semantic_mapping (questionnaire_id, semantic_role)
  WHERE is_active AND grain = 'narrow';
```

Trade-off yang disadari: kalau `grain` sebuah role di registry berubah di masa depan, baris mapping lama yang sudah menyalin nilai grain lama **tidak otomatis ikut berubah**. Ini dianggap dapat diterima karena `grain` adalah properti struktural sebuah role (apakah ia mengisi satu kolom atau banyak baris) yang secara desain seharusnya tidak pernah berubah setelah role dipakai — bukan properti yang wajar untuk di-edit.

### 2.3 Scope constraint A: per kuesioner, bukan global

**Tantangan awal (kekhawatiran yang justru muncul dari brainstorming sebelum implementasi):** kalau constraint "satu role satu kode aktif" dibuat global, maka kasus yang sah — kode `f8` di kuesioner 2025 dan `f8_new` di kuesioner 2026 sama-sama memetakan ke role `status_pekerjaan` — akan **ditolak salah**, padahal itu justru pola yang paling sering terjadi: kuesioner berubah nomor kode antar tahun, maknanya tetap sama.

**Keputusan:** constraint A di-scope per `questionnaire_id` (lihat definisi index di atas — `questionnaire_id` ikut jadi bagian kunci index). Role yang sama boleh dipegang kode berbeda di kuesioner berbeda; yang tidak boleh hanyalah dua kode aktif untuk role yang sama **dalam kuesioner yang sama**. Ini memvalidasi kekhawatiran awal secara langsung: skenario "pertanyaan mirip termapping ke role yang sama" yang benar-benar berbahaya adalah ketika keduanya ada di kuesioner yang sama, bukan lintas tahun.

### 2.4 Kenapa validasi tipe hanya "hard block" untuk numerik

**Tantangan:** ingin mencegah kesalahan mapping seperti pertanyaan "waktu tunggu bekerja" (jawaban numerik) terpetakan ke role kategorikal seperti `status_pekerjaan` — tapi juga tidak ingin sistem menjadi terlalu kaku sehingga menolak data sah yang bentuknya bebas (teks, tanggal).

**Keputusan:** validasi tipe di `AlumniFactBuilderService::passesRoleValidation()` dan endpoint `POST /question-semantic-mappings` **hanya benar-benar tegas untuk `expected_kind` numerik** (integer/decimal) — kalau seluruh sample jawaban tidak bisa di-parse jadi angka, itu sinyal yang hampir pasti salah, jadi diblokir keras (422 `type_mismatch` di API, `null` + catat ke `etl_anomaly_log` di ETL). Untuk `categorical`/`text`/`boolean`/`date`, validasi berhenti di sekadar "ada isinya" — karena tidak ada cara struktural untuk memvalidasi "bentuk" teks bebas lebih jauh dari itu tanpa risiko menolak jawaban sah. Baris pertahanan kedua untuk kasus non-numerik (dua role kategorikal yang tertukar) bukan validasi tipe, tapi **desain UI**: role dikelompokkan per `category` di dropdown (lihat §4), sehingga kesalahan lintas-domain terlihat sebelum admin sempat submit.

### 2.5 option_code tidak boleh dikarang di klien

**Tantangan ini ditemukan saat review, bukan direncanakan dari awal** — lihat detail penuh di §8. Ringkasnya: alur "kategorikan status baru" awalnya membuat `option_code` dengan men-slug label jawaban (`"Bekerja penuh waktu"` → `"bekerja_penuh_waktu"`). Ini terlihat masuk akal tapi salah secara diam-diam: Cube.js mencocokkan `option_code` **persis** dengan `SPLIT_PART(id_status_alumni, ':', 3)`, yang berasal dari `option_code` asli OLTP (`"1"`, `"2"`, dst). Slug karangan tidak akan pernah match apa pun.

**Keputusan:** ditambah satu endpoint (`GET /question-semantic-mappings/option-candidates?semantic_role=`) yang mengembalikan pasangan `(option_code, option_label)` **asli** dari `questionnaire_options`, bersumber dari kode aktif yang sedang memegang role tersebut. UI Langkah 2 sekarang hanya boleh memilih dari daftar ini — tidak ada lagi input bebas yang bisa menghasilkan kode palsu.

### 2.6 kpi_category_mapping harus point-in-time, bukan sekadar "is_active"

**Tantangan ini awalnya luput dari desain awal, dan baru terlihat lewat pertanyaan langsung**: "kalau histori snapshot lama tetap tersimpan di fact table, apakah histori KPI-nya juga aman kalau definisi kategori berubah di kemudian hari?" Jawaban versi pertama fitur ini: **tidak** — `kpi_category_mapping` hanya punya `is_active`, tanpa keterkaitan ke `id_waktu`/snapshot mana pun. Subquery Cube.js selalu memakai `WHERE is_active = true`, yaitu definisi **hari ini**, diterapkan seragam ke **semua** baris fact tanpa peduli baris itu dari snapshot kapan. Konsekuensinya: mengubah definisi "terserap" besok akan diam-diam menghitung ulang **seluruh snapshot historis** pakai definisi baru — persis kelas masalah yang coba dihindari dengan menyimpan snapshot secara insert-only di §1.

**Kenapa ini penting secara institusional, bukan cuma teknis:** untuk pelaporan akreditasi (BAN-PT/LAM), pertanyaan yang relevan bukan "berapa Terserap menurut definisi HARI INI", tapi "berapa Terserap menurut definisi yang **berlaku saat laporan periode itu disusun**". Kedua pertanyaan itu harus bisa dijawab berbeda, dan sebelum perbaikan ini, sistem hanya bisa menjawab yang pertama.

**Keputusan:** ganti syarat `is_active = true` di semua subquery Cube.js dengan lookup *point-in-time*, memakai kolom yang **sudah ada** di skema (tidak ada kolom baru):

```sql
kcm.effective_date <= dw.tanggal_refresh
AND (kcm.deactivated_at IS NULL OR kcm.deactivated_at::date > dw.tanggal_refresh)
ORDER BY kcm.effective_date DESC
LIMIT 1
```

`dw.tanggal_refresh` adalah tanggal snapshot milik baris fact itu sendiri (`dim_waktu` via `${CUBE}.id_waktu`) — jadi tiap baris fact dicocokkan ke definisi yang **efektif pada tanggalnya sendiri**, bukan definisi terbaru secara global. Snapshot lama otomatis terkunci ke definisi lama; snapshot baru (dan snapshot mendatang) otomatis ikut definisi terbaru karena tanggalnya juga baru.

**Jebakan migrasi yang baru ketahuan lewat verifikasi langsung, bukan lewat membaca kode** (lihat §8 untuk kronologi lengkap): baseline seed pertama sempat memakai `effective_date = CURRENT_DATE` (tanggal seed dijalankan). Akibatnya, snapshot fact yang **sudah ada sebelum seed dijalankan** terlihat seolah "belum punya kategori apa pun" di bawah aturan point-in-time — kebalikan dari yang diinginkan. Baseline harus di-backdate ke tanggal yang pasti lebih lama dari data historis manapun (`2020-01-01`, merepresentasikan "definisi ini sudah berlaku sejak sebelum sistem ini ada"), sementara perubahan admin yang genuin sesudahnya tetap memakai tanggal aslinya.
---

## 3. Arsitektur akhir

```
tracer_oltp (schema, koneksi 'oltp')          public (schema, koneksi 'olap', dibaca Cube.js)
┌─────────────────────────────┐               ┌─────────────────────────────┐
│ semantic_role_registry       │               │ kpi_category_mapping         │
│  role_key PK, category,      │               │  semantic_role (no FK),      │
│  expected_kind, value_min/   │               │  option_code, kpi_category,  │
│  max, target_column, grain   │               │  digunakan_oleh              │
├─────────────────────────────┤               ├─────────────────────────────┤
│ question_semantic_mapping    │               │ etl_anomaly_log              │
│  questionnaire_id, code,     │               │  etl_run_id, question_code,  │
│  semantic_role FK, grain     │               │  reason, raw_answer          │
│  (denormalized), is_active   │               └─────────────────────────────┘
└─────────────────────────────┘
        ▲                                                    ▲
        │ dibaca admin API + ETL                             │ dibaca Cube.js
        │ (Laravel, kedua koneksi)                           │ langsung via SQL
        └────────────────────────┬───────────────────────────┘
                                  │
                    fact_tracer_study, dim_status_alumni, dst
                    (skema TIDAK berubah sama sekali)
```

Empat constraint yang ditegakkan (lihat §2.2–2.3 untuk alasan masing-masing):

| Constraint | Ditegakkan di | Sifat |
|---|---|---|
| **A** — satu role *narrow* per kuesioner | Unique partial index (DB) + pre-check di service (409, bisa `force_replace`) | Soft-block |
| **B** — satu kode tidak boleh dobel-role | Unique partial index (DB) + pre-check di service (409) | Hard-block, tanpa opsi force |
| **C** — role *wide* pakai `dim_indikator_evaluasi` | Konvensi (kolom `grain` mengecualikan dari index A) | Reuse mekanisme lama, bukan mekanisme baru |
| **D** — deteksi pertanyaan mirip | `pg_trgm` similarity, endpoint `/similar` | Soft, informatif di UI sebelum commit |

---

## 4. Alur validasi

### 4.1 Membuat mapping baru (`POST /question-semantic-mappings`)

Urutan pengecekan ini **disengaja**, bukan sembarang urutan:

1. **Role harus ada** di registry (404 kalau tidak) — gagal cepat sebelum query lain dijalankan.
2. **Constraint B dulu, baru A.** Kenapa urutan ini penting: constraint B ("kode ini sudah berarti sesuatu yang lain") adalah kesalahan yang *tidak pernah* boleh ditimpa — kalau kode `f8` sudah berarti `status_pekerjaan`, tidak ada skenario di mana memaksanya juga berarti `masa_tunggu` itu benar. Maka B dicek lebih dulu dan **tidak punya jalur force**. Constraint A ("role ini sudah dipegang kode lain") adalah situasi yang *kadang* memang perlu ditimpa — kuesioner berganti nomor kode antar tahun — sehingga ia punya jalur `force_replace` yang eksplisit, dan sengaja dicek belakangan supaya kalau keduanya sekaligus terjadi, pesan error yang lebih tidak bisa dikompromikan (B) yang duluan tampil ke admin.
3. **Cek tipe** — hanya soal "apakah role ini bisa menerima nilai numerik" (lihat §2.4), dan hanya dijalankan setelah lolos B/A supaya tidak membuang kerja query sample jawaban untuk kombinasi yang toh akan ditolak duluan.
4. **Transaksi:** kalau `force_replace=true`, nonaktifkan baris lama (`is_active=false`, `deactivated_at`, `deactivated_by`) DAN insert baris baru dalam satu transaksi — supaya tidak pernah ada jeda waktu di mana constraint A "sengaja" dilanggar (baris lama sudah mati tapi baris baru belum hidup, atau sebaliknya).

**Kenapa tidak sekalian truncate/replace langsung?** Karena baris yang dinonaktifkan tetap ada di database (forward-only, lihat §6) — itu jejak audit yang dibutuhkan untuk akreditasi LAM/BAN-PT: harus bisa dijawab "kode apa yang memetakan role ini pada tanggal X", bukan cuma "kode apa yang memetakannya sekarang".

### 4.2 Resolusi runtime saat ETL berjalan

Filosofinya **gagal lembut, bukan gagal diam atau gagal keras**:

- **Gagal keras (dihindari):** ETL berhenti total karena satu jawaban tidak valid — tidak dapat diterima, karena satu alumni bermasalah tidak boleh memblokir seluruh batch.
- **Gagal diam (dihindari):** nilai yang tidak valid dipaksa jadi `0` atau string kosong — tidak dapat diterima, karena ini mencemari rata-rata dan agregat tanpa jejak.
- **Gagal lembut (dipilih):** kolom fact dibiarkan `NULL`, dan kejadian dicatat ke `etl_anomaly_log` dengan `etl_run_id`, `question_code`, `semantic_role`, `raw_answer`, dan `reason` (`type_mismatch` atau `out_of_range`). Admin bisa menelusuri semua anomali satu *run* dan menilai sendiri apakah itu data kotor satu alumni, atau sinyal bahwa satu mapping salah secara sistemik (kalau satu `question_code` muncul puluhan kali di log yang sama).

### 4.3 Mengelompokkan status baru ke kategori KPI (Langkah 2 UI)

Alur ini sengaja dirancang **tidak bisa menerima input bebas** untuk `option_code` (lihat §2.5 untuk kronologi kenapa) — satu-satunya sumber adalah endpoint `option-candidates`, yang menyaring hanya kandidat yang belum masuk kategori apa pun di `digunakan_oleh` yang sama. Ini membuat kesalahan "kode karangan yang tidak pernah match" secara struktural tidak mungkin terjadi lagi, bukan sekadar diperingatkan.

---

## 5. Perubahan per repo

### `tracer-study-backend`

| Area | Sebelum | Sesudah |
|---|---|---|
| Whitelist ekstraksi | `const RELEVANT_QUESTION_CODES` (~40 kode, PHP literal) | Query ke `question_semantic_mapping` (WHERE `is_active`), di-cache sekali per run |
| Resolusi kolom fact | `$resolved['f502']`, `$resolved['f8']`, dst (literal) | `$resolvedByRole['masa_tunggu_bekerja']` dst, key-nya `semantic_role`, tervalidasi terhadap kontrak registry |
| Business key `id_status_alumni` | `"{id}:f8:{option_code}"` (literal `'f8'`) | `"{id}:{questionCode}:{option_code}"`, `questionCode` resolve dinamis — **format tetap sama**, hanya isi segmen tengah yang dinamis (aman untuk Cube.js, lihat §7) |
| `KeterserapanService::STATUS_TERSERAP` | Salinan literal ke-4 | Query ke `kpi_category_mapping` via `KpiCategoryMappingRepository` |
| API admin | Tidak ada | 4 controller baru: semantic-roles, question-semantic-mappings (+unmapped/similar/option-candidates/questionnaires), kpi-category-mappings (+formula), etl-anomaly-log |

### `tracer-study-analytics` (Cube.js)

8 measure di `FactTracerStudy.js` — `count_terserap`, `count_tidak_terserap`, 4 varian `count_tunggu_*`, `count_sesuai_bidang`, `count_tidak_sesuai_bidang` — diganti dari literal `IN (...)` menjadi subquery ke `public.kpi_category_mapping`. Konstanta numerik institusional (batas bulan 0/3/6 untuk kategori waktu tunggu) **sengaja tetap hardcode** — itu bukan fakta kode→kategori, melainkan kebijakan DIKTI yang memang tempatnya di kode, bukan di tabel mapping.

### `fe-tracer-study`

Halaman `QuestionMappingPage.tsx` (sebelumnya 100% data mock, nol pemanggilan API) disambungkan penuh ke backend, ditambah pengaman yang tidak ada di versi mock:

- Dropdown role dikelompokkan per `category` (lihat §2.4) — pertahanan utama terhadap salah pilih role lintas domain KPI.
- Badge tipe data (`expected_kind` + rentang) di tiap opsi role.
- Kotak perbandingan sample-jawaban berdampingan sebelum commit.
- Hard-block tombol "Aktifkan mapping" saat backend menolak karena `type_mismatch`.
- Kotak peringatan pertanyaan mirip (constraint D) sebelum commit.
- Dialog konflik constraint A dengan tombol "Ganti mapping lama" (`force_replace`).
- Formula tooltip 3 chart KPI (Keterserapan, Waktu Tunggu, Kesesuaian Bidang) sekarang menampilkan daftar status **aktual** dari API, bukan teks statis generik "A + B + C".
- Selector kuesioner sekarang dari `GET /question-semantic-mappings/questionnaires` (kuesioner nasional, `program_id IS NULL`) — bukan lagi daftar 3 baris statis yang di-hardcode di komponen.

---

## 6. Keunggulan dibanding kode sebelumnya

- **Satu sumber kebenaran.** Empat definisi independen yang bisa saling tidak sinkron menjadi satu tabel yang dibaca semua konsumen.
- **Perubahan KPI = data, bukan deploy.** Menambah status baru ke kategori "terserap" adalah satu `INSERT` lewat UI admin — tidak menyentuh kode, tidak deploy ulang Cube.js.
- **Kesalahan tertangkap sebelum tersimpan, bukan setelah angka dashboard salah.** Constraint database (A/B) menolak mapping yang saling bertabrakan secara struktural; UI (grouping per kategori, badge tipe, perbandingan sample) menangkap kesalahan logis yang tidak bisa dicegah constraint.
- **Tidak ada lagi jalur silent-failure untuk data kategori.** `option_code` wajib dari sumber asli (§2.5) — kelas bug "tersimpan di UI tapi tidak pernah mengubah angka KPI" ditutup secara struktural, bukan lewat disiplin manual.
- **Riwayat tidak pernah hilang.** Forward-only (`is_active=false` + baris baru) konsisten dengan pola `lam_versions`/`thresholds` yang sudah ada — penting untuk audit LAM/BAN-PT yang butuh jawaban "berlaku sejak kapan", bukan cuma "apa nilainya sekarang".
- **Skema lama tidak tersentuh.** Seluruh perubahan aditif — nol risiko regresi terhadap fact/dim yang sudah diproduksi.

---

## 7. Verifikasi non-regresi

Seed pertama diisi persis dengan nilai hardcode lama (option_code `1,3,4,6,7` untuk terserap, dst — lihat `database/dump/003_semantic_mapping_seed.sql`), lalu tiap measure Cube.js dijalankan dengan SQL lama dan SQL baru berdampingan terhadap database dev yang sama:

| Measure | Sebelum | Sesudah |
|---|---:|---:|
| `count_terserap` | 7.803 | 7.803 |
| `count_tidak_terserap` | 564 | 564 |
| `count_masa_tunggu_cepat` | 4.593 | 4.593 |
| `count_tunggu_0_3_bulan` | 4.093 | 4.093 |
| `count_tunggu_3_6_bulan` | 2.153 | 2.153 |
| `count_tunggu_lebih_6_bulan` | 909 | 909 |
| `count_sesuai_bidang` | 4.091 | 4.091 |
| `count_tidak_sesuai_bidang` | 1.064 | 1.064 |

Kedelapan angka identik — perubahan format business key (§5, `id_status_alumni`) juga aman karena Cube.js hanya pernah membaca segmen ke-3 lewat `SPLIT_PART(...,3)`, tidak pernah segmen ke-2 yang isinya berubah jadi dinamis.

**Verifikasi kedua, setelah point-in-time lookup ditambahkan (§2.6)** — dijalankan dengan ETL nyata (`php artisan etl:run --force`) yang menghasilkan snapshot kedua (`id_waktu=2`, hari ini) berdampingan dengan snapshot lama (`id_waktu=1`, 24 Juni), lalu sengaja mengubah dua definisi kategori di antara kedua snapshot itu (menonaktifkan `iku2_keterserapan`, mengubah kategori satu opsi kesesuaian bidang) untuk membuktikan histori tidak ikut berubah:

| Measure | Snapshot lama (24 Jun) | Snapshot baru (hari ini) | Ekspektasi |
|---|---:|---:|---|
| `count_terserap` | 7.803 | 0 | Snapshot lama TIDAK terpengaruh nonaktifnya `iku2_keterserapan` hari ini |
| `count_sesuai_bidang` (bagian relevansi) | 4.225 | 1.492 | Snapshot baru mengikuti kategori "Sangat Erat" yang baru diubah hari ini; snapshot lama tetap pakai definisi lama |
| Jumlah baris fact per snapshot | 8.367 | 8.367 | Tidak ada baris lama yang hilang/tertimpa |


---

## 8. Bug yang ditemukan selama proses

Dua cacat berikut **tidak** ada di rencana awal — keduanya baru terlihat saat hasil implementasi ditinjau ulang baris-per-baris, bukan sekadar dipercaya dari ringkasan laporan. Dicatat di sini karena keduanya menunjukkan jenis kesalahan yang mudah lolos kalau hanya mengandalkan "kode berjalan tanpa error":

1. **`option_code` dikarang dari slug label (kritis, silent failure).** Implementasi awal Langkah 2 mengirim `slugify(label)` sebagai `option_code` untuk status yang belum pernah dikategorikan. Ini lolos type-check dan lolos constraint database (keduanya hanya memvalidasi *bentuk*, bukan *kebenaran* nilai) — kategorisasi akan tersimpan sukses di UI, tapi karena `option_code` karangan tidak pernah cocok dengan `SPLIT_PART(id_status_alumni,':',3)` yang dibaca Cube.js, angka KPI di dashboard **tidak akan pernah berubah**. Diperbaiki dengan menutup jalur input bebas sama sekali (§2.5), bukan menambah validasi — karena validasi tidak bisa membedakan slug yang kebetulan benar dari yang salah.
2. **`semantic_role` salah ketik di satu tooltip chart.** `Kpi6FieldRelevanceChart.tsx` memanggil formula dinamis dengan `semantic_role="kesesuaian_bidang"` — nama yang terdengar benar tapi tidak ada di registry (yang benar `relevansi_bidang`, lihat §1 tabel 4 kolom `question_code` untuk asal penamaannya). Akibatnya tooltip akan selalu kosong, gagal senyap tanpa error di console.
3. **Endpoint `option_code` yang sudah dibangun untuk FE ternyata belum divalidasi di server (defense-in-depth gap).** Setelah bug #1 ditutup di jalur FE resmi, `POST /kpi-category-mappings` di backend ternyata masih menerima `option_code` apa saja tanpa mengecek keasliannya — celah yang sama, jalur berbeda (klien API lain selain FE resmi masih bisa lolos). Ditemukan lewat *test case yang sengaja dibuat salah* (kirim `option_code="bekerja_penuh_waktu"` langsung ke service layer), bukan lewat membaca kode. Diperbaiki dengan validasi di `KpiCategoryMappingService::store()` yang mencocokkan ke `SemanticMappingRepository::getOptionCandidatesForRole()` — sekarang 422 `invalid_option_code` di kedua jalur (FE dan langsung ke API).
4. **Baseline seed `kpi_category_mapping` memakai `CURRENT_DATE`, merusak point-in-time lookup untuk data historis yang sudah ada (§2.6).** Ini bukan ditemukan dari membaca kode — ditemukan lewat urutan kejadian nyata: pengguna sendiri mengedit satu kategori lewat UI Edit yang baru dibangun (`mapped_by`/`deactivated_by` terisi user id asli, bukan `null` seperti skrip pengujian), lalu verifikasi numerik dua arah (query "old" vs "new" pada instan yang sama) menunjukkan hasil 0 yang mencurigakan untuk measure yang seharusnya masih punya data. Root cause: seed baseline memakai tanggal hari ini sebagai `effective_date`, sehingga di bawah aturan point-in-time yang baru, snapshot fact yang sudah ada SEBELUM seed dijalankan (24 Juni) terlihat "belum ada kategori apa pun". Diperbaiki dengan backdate baseline ke `2020-01-01` — hanya baris `mapped_by IS NULL` (seed asli) yang disentuh, baris hasil edit pengguna sendiri (`mapped_by` terisi) sengaja tidak ikut diubah.
5. **`apiClient.ts::getKpiCategoryMappingFormula()` unwrap response satu level terlalu sedikit (silent failure, mirip pola bug #1).** Fungsi ini mengembalikan `response.data` dengan komentar eksplisit "Respons TIDAK dibungkus `{ data }`" — padahal `KpiCategoryMappingController::formula()` MEMBUNGKUS hasilnya persis seperti endpoint lain (`{ success, data }`). Akibatnya `result.data?.groups` di `useKpiFormula.ts` selalu mencari `.groups` di objek pembungkus terluar yang tidak punya properti itu, selalu `undefined`, dan tooltip DIAM-DIAM jatuh ke teks fallback statis ("A = bekerja, B = lanjut studi, ...") — persis seperti gejala bug #1 (kategorisasi tersimpan sukses, tapi tidak pernah terlihat memengaruhi apa pun) walau root cause-nya di lapisan HTTP client, bukan di data. Ditemukan dengan menguji `formula()` langsung lewat `php artisan tinker` (memastikan backend mengembalikan data yang benar) SEBELUM menyimpulkan bug ada di FE — kalau langsung dipercaya "backend pasti salah" tanpa bukti, perbaikan akan salah sasaran. Diperbaiki dengan `response.data.data`, komentar lama dihapus dan diganti penjelasan yang benar.
6. **Drill-down bar "% Lulusan ≤ N Bulan" memakai `rentang: "0-3"` yang di-hardcode, bukan ambang dinamis.** Klik pada bar tren masa-tunggu-cepat (menampilkan agregat sesuai `batas_cepat_bulan` yang bisa berubah per LAM) selalu memicu drill-down dengan `rentang="0-3"` — bucket histogram TETAP, bukan definisi "cepat" yang sesungguhnya (`≤ batas_cepat_bulan`, bisa lebih dari 3 bulan). Kalau ambang sedang 6 bulan, angka di bar mencakup bucket 0-3 DAN 3-6, tapi daftar drill-down hanya menampilkan bucket 0-3 — jumlah baris di modal tidak pernah cocok dengan angka di bar (mis. bar bilang 262, modal cuma tampilkan 203). Ditemukan lewat laporan pengguna yang membandingkan angka bar vs isi modal secara manual. Diperbaiki dengan menambah mode `rentang="cepat"` end-to-end (`MasaTungguRepository::buildRentangFilters()` → `MasaTungguService::getDrillDown()` → `MasaTungguController` → hook FE → `Kpi5WaitingTimeChart.tsx`), memakai filter yang PERSIS sama dengan `cepatCountsByGroup()` yang menghasilkan angka di bar.
7. **`masa_tunggu_bekerja = 0` (bekerja sebelum/tepat saat lulus) sengaja dikecualikan dari kategori "cepat" (`operator gt 0`, bukan `gte 0`).** Ditemukan saat memverifikasi perbaikan #6 secara numerik: jumlah "cepat" hasil perbaikan (200) masih tidak sama dengan jumlah bucket 0-3 + 3-6 (203+59=262) — selisihnya persis 62, yang ternyata adalah alumni dengan masa tunggu 0 bulan, dikecualikan oleh filter `gt 0` yang sudah ada sejak `cepatCountsByGroup()` pertama dibuat (bukan bug baru dari perbaikan #6, tapi baru KETAHUAN lewat verifikasi #6). Ini keputusan bisnis, bukan bug teknis murni — dikonsultasikan ke pengguna, dan diputuskan 0 bulan HARUS ikut dihitung "cepat" (lebih cepat dari siapa pun). Diperbaiki di kedua tempat yang punya filter sama (`cepatCountsByGroup()` dan `buildRentangFilters()` case `'cepat'`), `gt` → `gte`.
8. **Filter snapshot berbasis teks `minggu_snapshot` ambigu kalau ada >1 ETL run di minggu kalender yang sama — dan fitur auto-trigger ETL (§10) membuat ini rutin terjadi, bukan lagi kasus langka.** Lihat kronologi penuh di §12. Ditemukan lewat laporan pengguna: angka di judul modal drill-down (60/130) tidak cocok dengan isi datanya (54) — root cause-nya BUKAN bug baru, tapi 2 baris `dim_waktu` dari 2 kali ETL test hari yang sama, keduanya berlabel `minggu_snapshot="29"`, sehingga difilter berbarengan dan meng-inflate angka agregat.
9. **Tooltip formula KPI tidak point-in-time — selalu menampilkan definisi "hari ini", bukan definisi yang berlaku pada snapshot yang sedang dilihat.** Beda dari bug #5 (yang membuat tooltip SELALU fallback statis), bug ini baru terlihat SETELAH bug #5 diperbaiki: tooltip jadi dinamis, tapi nilainya "kadang benar kadang salah" tergantung snapshot mana yang sedang aktif di filter global — karena `formulaRows()` cuma memfilter `is_active=true` (definisi SEKARANG), padahal data chart-nya sendiri sudah point-in-time (§2.6) sejak awal. Kalau pengguna melihat snapshot lama sementara admin lain baru saja mengubah kategori hari ini, chart menampilkan definisi lama (benar) tapi tooltip menampilkan definisi baru (salah untuk konteks itu). Diperbaiki dengan membuat `formulaRows()` menerima `$snapshotDate` dan memakai kondisi point-in-time yang PERSIS sama dengan subquery Cube.js (`effective_date <= snapshotDate AND (deactivated_at IS NULL OR deactivated_at::date > snapshotDate)`, ambil baris `effective_date` terbaru per `option_code`) — lihat §9.5 untuk detail lengkap.

Pelajaran yang diambil: constraint database dan validasi tipe menjamin data **berbentuk benar**, bukan **berarti benar** — kesalahan makna (option_code yang valid secara format tapi tidak pernah match, nama variabel yang valid secara sintaks tapi salah role, tanggal efektif yang valid tapi terlalu baru, response HTTP yang valid JSON tapi salah level pembungkusan) hanya tertangkap lewat verifikasi langsung terhadap data nyata dan skenario yang sengaja dibuat gagal, bukan lewat lolos-tidaknya compiler atau constraint. Pola yang berulang di bug #1/#5: kode BERASUMSI tentang bentuk data (dibungkus/tidak, cocok/tidak) tanpa memverifikasi asumsi itu terhadap sumber sebenarnya — komentar yang salah (#5) bahkan lebih berbahaya dari tidak ada komentar sama sekali, karena meyakinkan pembaca berikutnya bahwa asumsi itu sudah diverifikasi padahal tidak.

---

## 9. Ambang threshold dinamis

Fitur terpisah dari pemetaan semantik di atas, tapi masalahnya sejenis: dua chart
(% lulusan bekerja "cepat", % lulusan bergaji "di atas UMP") membandingkan data
terhadap ambang yang **dulu hardcode** di kode — 6 bulan untuk "cepat", 1,2× UMP
untuk "layak" — padahal sistem sudah punya `threshold_configs.param_value`
per LAM version yang seharusnya jadi sumber ambang itu. Memilih LAM version
berbeda di dropdown filter sudah mengganti angka target (garis referensi merah
di chart), tapi *tidak* mengganti ambang yang dipakai menghitung data itu sendiri.

### 9.1 Dua bentuk masalah yang berbeda

**Masa tunggu**: `masa_tunggu_bekerja` (jumlah bulan mentah) tetap tersimpan
utuh per baris fact — jadi query time bisa membandingkan terhadap ambang
berapa pun. Perbaikannya murni di lapisan Laravel: `MasaTungguRepository`
sudah lama tahu cara membangun *ad hoc filter* Cube.js (`buildRentangFilters()`,
dipakai drill-down) — tinggal terapkan pola yang sama untuk menghitung "cepat"
(`cepatCountsByGroup()`), menggantikan measure `count_masa_tunggu_cepat` yang
ambangnya dibakukan 6 bulan di `FactTracerStudy.js` dan tidak bisa menerima
parameter di query time.

**Pendapatan/UMP**: jauh lebih sulit. `flag_above_ump` dihitung SEKALI saat ETL
(`AlumniFactBuilderService.php`, ambang 1.2 hardcode) dan disimpan sebagai
angka 0/1 — begitu dihitung, **rasio aslinya hilang**, jadi tidak ada cara
membandingkan ulang terhadap ambang lain hanya dari kolom itu. Perbaikannya
harus di Cube.js: dimension baru `salary_ump_multiplier` (`take_home_pay / nilai_ump`,
dihitung ULANG di setiap query, bukan disimpan) yang bisa dibandingkan
terhadap ambang berapa pun via ad hoc filter — pola yang sama dengan masa
tunggu, tapi butuh join baru (`DimUmp`) karena `nilai_ump` sebelumnya tidak
pernah diambil dari cube manapun untuk keperluan ini.

### 9.2 Batu sandungan: Cube.js menolak dimension lintas-cube

Percobaan pertama menaruh `salary_ump_multiplier` di `FactTracerStudy`
langsung mereferensikan `${DimUmp}.nilai_ump` — Cube.js **menolak kompilasi**:

```
Member 'FactTracerStudy.salary_ump_multiplier' references foreign cubes:
DimUmp, DimUmp. Please split and move this definition to corresponding cubes.
```

Ini baru ketahuan lewat percobaan live terhadap Cube.js sungguhan, bukan dari
membaca dokumentasi Cube.js sebelumnya — dan sebetulnya file ini sendiri sudah
punya catatan soal aturan itu di bagian atas (`CATATAN CUBE.JS: Dimension dari
joined cube TIDAK boleh didefinisikan di sini`), hanya terlewat saat menulis
dimension baru. Solusinya konsisten dengan pola yang sudah dipakai measure
`count_terserap` dkk untuk lookup point-in-time: ganti `${DimUmp}.nilai_ump`
dengan **subquery SQL mentah** (`(SELECT du.nilai_ump FROM dim_ump du WHERE
du.ump_sk = ${CUBE}.ump_sk)`) yang tidak melibatkan referensi antar-cube sama
sekali di level Cube.js — join `DimUmp` di blok `joins` tetap dipertahankan
untuk dokumentasi relasi, tapi dimension computed lintas-tabel harus lewat
subquery, bukan template `${OtherCube}`.

### 9.3 Verifikasi

Dijalankan langsung terhadap Cube.js live (`php artisan tinker`, backend
sudah reachable), membandingkan hasil dengan ambang berbeda:

| Chart | Ambang A | Ambang B | Default (tanpa param) | Baseline lama |
|---|---|---|---|---|
| % Masa Tunggu Cepat | 6 bln → 9.186 | 3 bln → 6.468 (lebih ketat = lebih kecil ✓) | 6 bln | — |
| % ≥ Ambang UMP | 1,2× → 9.194 | 1,4× → 8.488 (lebih ketat = lebih kecil ✓) | 9.194 | `flag_above_ump=1` → 9.194 ✓ MATCH |

Ambang UMP default (tanpa param dikirim) cocok **persis** dengan hitungan
`flag_above_ump` versi lama — memastikan chart yang belum tersambung ke LAM
manapun (mis. filter prodi belum dipilih) tidak berubah perilakunya sama
sekali dibanding sebelum perubahan ini.

### 9.4 Yang berubah di FE

`useLamFilter(kpiKey)` sudah lama mengembalikan `dynamicParam.value` (nilai
`param_value` milik LAM version terpilih) tapi sebelumnya cuma dipakai untuk
garis referensi target, tidak untuk menghitung data itu sendiri.
`Kpi5WaitingTimeChart.tsx` dan `Kpi8IncomeChart.tsx` sekarang meneruskan
`lam.dynamicParam?.value` ke hook data (`useMasaTungguBar`, `usePendapatanBar`,
`usePendapatanDistribusi`, `usePendapatanDrillDown`), dan seluruh teks yang
tadinya menulis literal "6 Bulan"/"1,2×" (judul chart, label sumbu, formula,
tooltip, nama seri) sekarang mengikuti angka ambang yang sedang aktif.

### 9.5 Formula tooltip kategori KPI juga harus point-in-time

Bug terpisah dari §9.1–9.4, tapi berasal dari kelas masalah yang sama dengan
§2.6: `GET /kpi-category-mappings/formula` (dipakai `useKpiFormula` di semua
chart Keterserapan/Kesesuaian Bidang/Masa Tunggu untuk menampilkan daftar
status "sesuai"/"terserap" yang SEDANG berlaku di tooltip) awalnya cuma
memfilter `is_active = true` — definisi **hari ini**, tanpa peduli snapshot
mana yang sedang dilihat pengguna. Padahal data chart-nya sendiri SUDAH
point-in-time sejak §2.6. Efeknya: tooltip tampak "kadang benar kadang
salah" — benar kalau kebetulan snapshot yang dilihat = definisi hari ini,
salah kalau sedang melihat snapshot lama yang definisinya sudah berubah
sejak itu (lihat bug #9 di §8 untuk kronologi penemuan).

**Perbaikan** — `KpiCategoryMappingRepository::formulaRows()` sekarang
menerima `?string $snapshotDate` dan memakai kondisi PERSIS sama dengan
subquery Cube.js di §2.6:

```php
->where('effective_date', '<=', $snapshotDate)
->where(fn ($q) => $q->whereNull('deactivated_at')
                     ->orWhereRaw('deactivated_at::date > ?', [$snapshotDate]))
->orderByDesc('effective_date')
// lalu di PHP: groupBy(option_code)->map(fn($g) => $g->first())
// -- ambil baris effective_date TERBARU per option_code, mirror
// "ORDER BY effective_date DESC LIMIT 1" per-baris di Cube.js.
```

`$snapshotDate` diresolusi dari `id_waktu` yang sedang aktif di filter
global FE (`GET /kpi-category-mappings/formula?...&minggu_snapshot=<id_waktu>`,
lihat §12 untuk kenapa parameter ini sekarang berisi `id_waktu`, bukan lagi
teks minggu) lewat `KpiCategoryMappingRepository::tanggalRefreshForIdWaktu()`
— null/kosong berarti pakai hari ini (perilaku lama, dipertahankan sebagai
default). `useKpiFormula.ts` menambahkan `weekKey` (dari `GlobalFiltersContext`)
ke query key React Query, supaya pindah snapshot memicu refetch tooltip,
bukan diam memakai hasil cache dari snapshot sebelumnya.

**Verifikasi** — dua snapshot yang sengaja mengalami perubahan kategori
`relevansi_bidang` di antara keduanya (opsi "Sangat Erat" dipindah dari
"sesuai" → "tidak_sesuai" hari ini) menghasilkan tooltip berbeda sesuai
periodenya:

| Snapshot | `sesuai` | `tidak_sesuai` |
|---|---|---|
| Lama (24 Jun) | Erat, Sangat Erat | Tidak Sama Sekali, Kurang Erat |
| Terbaru (hari ini) | Erat | Sangat Erat, Tidak Sama Sekali, Kurang Erat |

---

## 10. Auto-trigger ETL & status tracking

### 10.1 Masalah: Langkah 1 butuh ETL, tapi tidak ada yang men-trigger atau memberi tahu penggunanya

Langkah 2 (`kpi_category_mapping`) TIDAK PERNAH butuh ETL — Cube.js membaca
tabel itu langsung lewat subquery point-in-time (§2.6) setiap query, jadi
perubahan kategori terlihat di dashboard begitu cache di-invalidate (§11),
tanpa proses batch apa pun.

Langkah 1 (`question_semantic_mapping`) BEDA SECARA FUNDAMENTAL: memetakan
kode pertanyaan baru ke sebuah role tidak otomatis mengisi kolom fact
`fact_tracer_study` untuk jawaban yang SUDAH ADA di OLTP — itu perlu
`AlumniFactBuilderService` benar-benar dijalankan ulang untuk menata ulang
jawaban historis sesuai mapping baru. Sebelum perbaikan ini, ETL:

- Hanya bisa dipicu manual (`php artisan etl:run`) atau lewat jadwal
  mingguan (`Schedule::command('etl:run')`).
- Tidak ada trigger otomatis setelah admin menyimpan/menonaktifkan mapping
  Langkah 1 — admin harus tahu dan mengingat untuk menjalankan ETL sendiri.
- Tidak ada UI yang memberi tahu status ETL ("sedang berjalan", "selesai",
  "gagal") — kalaupun ETL dipicu, admin tidak tahu kapan aman mengecek
  dashboard.

### 10.2 Arsitektur

Satu tabel status baru + satu queue job, murni aditif:

```sql
-- tracer_oltp.etl_runs (lihat 005_etl_runs_and_queue.sql)
id, status ('queued'|'running'|'completed'|'failed'), reason,
triggered_by, id_waktu, summary (jsonb), error_message,
started_at, finished_at
```

`RunEtlJob implements ShouldQueue` membungkus `EtlOrchestratorService::run()`
yang sudah ada (tidak ada logika ETL baru), menulis status ke `etl_runs`
di setiap tahap (queued → running → completed/failed), lalu memanggil
`Cache::store('redis')->tags(['analytics-dashboard'])->flush()` setelah
selesai (§11) supaya dashboard langsung segar tanpa menunggu TTL.

`QuestionSemanticMappingService::store()` dan `::deactivate()` masing-masing
membuat baris `etl_runs` baru dan men-dispatch `RunEtlJob` setelah mapping
tersimpan, mengembalikan `etl_run_id` di response API. FE (`QuestionMappingPage.tsx`)
menyimpan id itu dan poll `GET /api/etl-runs/{id}` tiap 2 detik
(`useEtlRunStatus`, `refetchInterval` React Query, berhenti otomatis begitu
status `completed`/`failed`) untuk menampilkan banner status — "ETL sedang
berjalan, jangan tutup halaman ini" sampai "ETL selesai, data sudah
mengikuti mapping terbaru".

**Kenapa `force: true`, bukan mode incremental default:** `EtlOrchestratorService::run()`
normalnya hanya memproses response yang `updated_at >= snapshot terakhir`
(mode incremental, cocok untuk jadwal mingguan). Tapi perubahan mapping
Langkah 1 perlu menata ulang jawaban yang SUDAH ADA sejak dulu, bukan cuma
response baru — kalau memakai mode incremental, alumni yang sudah lama
mengisi kuesioner tidak akan pernah ter-refresh mengikuti mapping baru.
`RunEtlJob` selalu memanggil `run(force: true)`, memproses ULANG seluruh
response `submitted` tanpa syarat tanggal.

**Kenapa queue, bukan langsung di request HTTP:** `EtlOrchestratorService::run()`
memproses seluruh `fact_tracer_study`/`fact_multi_select`/`fact_range_evaluasi`
dalam satu transaksi — bisa berlangsung lama tergantung volume data.
Menjalankannya langsung di request `POST /question-semantic-mappings` akan
membuat endpoint itu "menggantung" sampai ETL selesai. `QUEUE_CONNECTION`
diubah dari `sync` ke `database` (tabel `jobs`/`job_batches`/`failed_jobs`
standar Laravel, ditambahkan di `005_etl_runs_and_queue.sql`) supaya
endpoint mapping tetap cepat merespons, ETL berjalan di background.

**Prasyarat operasional yang WAJIB dipenuhi:** karena `QUEUE_CONNECTION=database`,
job tidak akan pernah diproses tanpa worker berjalan
(`php artisan queue:work`, atau `queue:listen` untuk dev). Ini BUKAN
opsional — tanpa worker, `etl_runs.status` akan diam selamanya di `queued`
dan FE akan terus menampilkan banner loading tanpa pernah selesai. Lihat
README bagian Setup untuk instruksi menjalankan worker.

### 10.3 Verifikasi

Dijalankan langsung terhadap queue database live: `EtlRun::create()` +
`RunEtlJob::dispatch()` lewat `php artisan tinker`, dikonfirmasi masuk ke
tabel `jobs`, lalu diproses dengan `php artisan queue:work --once` —
`etl_runs.status` berpindah `queued` → `running` → `completed` dengan
`id_waktu` dan `summary` (jumlah baris per tahap) terisi benar.

---

## 11. Cache invalidation

### 11.1 Masalah: dashboard tidak update setelah mapping berubah, tanpa error apa pun

14 service di `app/Services/Analytical/*.php` (Keterserapan, MasaTunggu,
Pendapatan, FilterMeta, dst) meng-cache seluruh response-nya di Redis lewat
`WithCache::remember()` dengan TTL 1–24 jam, untuk mengurangi beban query
ke Cube.js. Sebelum perbaikan ini, **tidak satu pun** panggilan `remember()`
diberi `$tags` — parameter opsional yang ada di trait sejak awal
(`forgetTag()` di `WithCache` hanya bisa menghapus entri yang PUNYA tag).
Efeknya: admin mengubah kategori KPI atau mapping pertanyaan, cache lama
tetap terpakai sampai TTL alaminya habis (bisa sampai 24 jam) — dashboard
tampak "tidak berubah" tanpa error apa pun yang menjelaskan kenapa, gejala
yang paling sering disalahartikan sebagai bug di mapping itu sendiri,
padahal mapping-nya sudah benar sejak awal.

### 11.2 Perbaikan

Satu tag bersama `analytics-dashboard` ditambahkan ke SEMUA panggilan
`remember()` di 14 service tsb (`}, self::TTL);` → `}, self::TTL,
['analytics-dashboard']);`), lalu tiga titik mutasi memanggil
`$this->forgetTag('analytics-dashboard')` setelah berhasil menyimpan
perubahan:

- `KpiCategoryMappingService::store()` dan `::deactivate()` (Langkah 2 —
  invalidasi LANGSUNG, karena Cube.js membaca tabel ini live, tidak perlu
  menunggu ETL).
- `QuestionSemanticMappingService::store()` dan `::deactivate()` (Langkah 1
  — invalidasi langsung SAAT mapping disimpan, ditambah invalidasi KEDUA
  setelah `RunEtlJob` selesai di §10, karena datanya baru benar-benar
  berubah setelah ETL, bukan saat mapping disimpan).

### 11.3 Verifikasi

Satu kali flush manual (`Cache::store('redis')->flush()`) dijalankan untuk
membersihkan entri yang ter-cache SEBELUM perbaikan ini ada (tidak
bertag, tidak akan pernah ter-invalidate oleh `forgetTag()` yang baru
ditambahkan) — supaya efek perbaikan langsung terlihat tanpa menunggu TTL
lama habis satu per satu.

---

## 12. Snapshot unik: `id_waktu` menggantikan teks `minggu_snapshot`

### 12.1 Masalah, dan kenapa baru terasa SEKARANG

Filter snapshot global (dropdown "Snapshot Minggu" di semua chart) sejak
awal mencocokkan `DimWaktu.minggu_snapshot` — teks angka minggu ISO (mis.
`"29"`). Kolom ini **tidak unik**: dua baris `dim_waktu` dari dua ETL run
berbeda bisa punya `minggu_snapshot` yang identik kalau keduanya jatuh di
minggu kalender yang sama. Sebelum §10 ada, ini kasus langka (ETL cuma
jalan terjadwal mingguan). Begitu §10 selesai — ETL bisa ter-trigger KAPAN
SAJA setiap Langkah 1 berubah — tabrakan "2 run di minggu yang sama" jadi
RUTIN, bukan lagi edge case, karena admin bisa mengubah mapping beberapa
kali dalam hari/minggu yang sama.

Ditemukan lewat laporan pengguna nyata: angka di judul modal drill-down
(60/130) tidak cocok dengan isi datanya (54) — root cause: dua baris
`dim_waktu` (dari dua kali ETL test di hari yang sama) sama-sama berlabel
`minggu_snapshot="29"`, sehingga filter berbasis teks mencocokkan
KEDUANYA sekaligus, meng-inflate semua angka agregat (bar/pie/formula)
sementara drill-down individual kebetulan tidak ikut dobel — hasilnya
angka yang saling tidak konsisten antar bagian dashboard yang sama.

### 12.2 Perbaikan

`DimWaktu.id_waktu` sudah menjadi dimension `primary_key` di Cube.js sejak
awal (`DimWaktu.js`) — TIDAK perlu perubahan apa pun di repo Cube.js.
Perbaikan murni di sisi Laravel:

- `BaseAnalyticalRepository::buildGlobalFilters()` — member filter diganti
  dari `DimWaktu.minggu_snapshot` ke `DimWaktu.id_waktu`.
- `FilterMetaRepository::getSnapshot()` — field `value` di tiap opsi
  dropdown sekarang berisi `id_waktu` (mis. `"2"`), bukan lagi
  `minggu_snapshot` mentah (`"29"`); field `label` (menampilkan
  "2026 · Minggu 29 · Juli (13 Jul 2026)") tidak berubah.

**Nama parameter SENGAJA tidak diubah** di seluruh codebase (query string
`minggu_snapshot`, variable `$mingguSnapshot`/`weekKey`) — mengganti nama
di puluhan file (semua repository Analytical, semua hook FE) hanya untuk
konsistensi penamaan akan menambah risiko tanpa manfaat fungsional. Yang
berubah hanya NILAI yang mengalir lewat parameter itu (id_waktu, bukan
teks minggu) — FE tidak perlu ubah apa pun karena cuma meneruskan apa
adanya nilai `value` yang diberikan dropdown dari backend.

### 12.3 Verifikasi

Baris duplikat (`id_waktu` dari ETL test) dihapus dari database dev,
kemudian filter dengan `id_waktu` dibandingkan terhadap perhitungan manual
per-bucket:

| Query | Hasil |
|---|---|
| Drill-down `rentang=0-3`, filter `id_waktu=2` | 203 |
| Drill-down `rentang=3-6`, filter `id_waktu=2` | 59 |
| Drill-down `rentang=cepat`, filter `id_waktu=2` | 262 (=203+59) ✓ |
| Bar agregat `count_masa_tunggu_cepat`, filter `id_waktu=2` | 262 ✓ MATCH |

Tidak ada lagi selisih antara angka agregat dan drill-down.

---

## 13. Yang belum selesai

Dicatat apa adanya sebagai daftar kerja lanjutan:

- Label tampilan untuk `digunakan_oleh` (mis. `"iku2_keterserapan"` → `"IKU 2 — Keterserapan"`) masih kamus statis di FE, belum dari API.
- Kolom "Dikelompokkan Oleh" di tabel audit Langkah 2 belum ada di skema `kpi_category_mapping` — sengaja tidak ditampilkan di UI daripada memalsukan nilai.

- **`WirausahaRepository`, `KesesuaianRepository`, `SebaranInstansiRepository` (Laravel, sisi Analytical) masih hardcode `status_alumni_sk` literal** (`= '3'` untuk wirausaha, `= '1'` untuk bekerja) di 8 lokasi berbeda — pola yang sama persis dengan hardcode yang sudah dibereskan di `FactTracerStudy.js`, tapi belum tersentuh karena letaknya di query builder ad hoc Laravel→Cube.js, bukan di measure Cube.js sendiri. Ini LEBIH rapuh dari yang sudah diperbaiki: `status_alumni_sk` adalah surrogate key auto-increment yang menurut catatan di kode ini sendiri "bisa berubah antar-rebuild ETL", bukan `option_code` yang stabil. Rencana perbaikan: dua dimension boolean baru di `FactTracerStudy.js` (`is_bekerja_status`, `is_wirausaha_status`) yang dibangun point-in-time seperti measure lain, plus satu grouping baru (`digunakan_oleh='wirausaha_scope'`) supaya cakupan "wirausaha" (murni vs termasuk yang sambil studi lanjut) jadi bisa dikonfigurasi lewat UI, bukan permanen di kode. Belum dikerjakan.
- **Ambang dinamis (§9) belum sampai ke halaman "Bandingkan Prodi".** Backend (`getBandingkan()` di `MasaTungguRepository`/`PendapatanRepository`) sudah menerima parameter ambang, tapi FE halaman perbandingan antar-prodi belum meneruskan `lam.dynamicParam?.value` seperti yang sudah dilakukan di chart utama (`Kpi5WaitingTimeChart`, `Kpi8IncomeChart`).
- Label tampilan untuk `digunakan_oleh` (mis. `"iku2_keterserapan"` → `"IKU 2 — Keterserapan"`) masih kamus statis di FE, belum dari API.
- Kolom "Dikelompokkan Oleh" di tabel audit Langkah 2 belum ada di skema `kpi_category_mapping` — sengaja tidak ditampilkan di UI daripada memalsukan nilai.

Sudah selesai (dipindah dari daftar ini):
- ~~Daftar kuesioner statis di FE~~ — endpoint `GET /question-semantic-mappings/questionnaires` sudah ada.
- ~~`kpi_category_mapping` tidak point-in-time~~ — lihat §2.6, diverifikasi dengan ETL run nyata + dua snapshot berdampingan.
- ~~Belum diuji lewat `php artisan etl:run` penuh terhadap data nyata~~ — sudah dijalankan (`--force`), menghasilkan snapshot kedua yang benar secara point-in-time.
- ~~Tidak ada auto-trigger ETL setelah Langkah 1 berubah, tidak ada UI status~~ — lihat §10, `RunEtlJob` + `etl_runs` + polling FE.
- ~~Cache Redis 14 service Analytical tidak pernah ter-invalidate~~ — lihat §11, tag `analytics-dashboard` + `forgetTag()` di titik mutasi.
- ~~Filter snapshot ambigu (teks `minggu_snapshot`, bukan unik per run)~~ — lihat §12, diganti `DimWaktu.id_waktu`.
- ~~Tooltip formula KPI tidak point-in-time~~ — lihat §9.5.
