# Pemetaan Semantik Dinamis — Dokumentasi Arsitektur & Perubahan

> Status: implementasi selesai, diverifikasi terhadap database dev live.
> Repo yang terdampak: `tracer-study-backend` (ETL + API), `tracer-study-analytics` (Cube.js), `fe-tracer-study` (admin UI).

## Daftar isi

1. [Latar belakang: masalah yang mendorong perubahan ini](#1-latar-belakang)
2. [Tantangan desain yang dihadapi, dan bagaimana masing-masing diselesaikan](#2-tantangan-desain)
3. [Arsitektur akhir](#3-arsitektur-akhir)
4. [Alur validasi — dan kenapa setiap langkahnya ada](#4-alur-validasi)
5. [Perubahan per repo](#5-perubahan-per-repo)
6. [Keunggulan dibanding kode sebelumnya](#6-keunggulan-dibanding-kode-sebelumnya)
7. [Verifikasi non-regresi](#7-verifikasi-non-regresi)
8. [Bug yang ditemukan selama proses, dan apa yang bisa dipelajari darinya](#8-bug-yang-ditemukan-selama-proses)
9. [Yang belum selesai](#9-yang-belum-selesai)

---

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

---

## 8. Bug yang ditemukan selama proses

Dua cacat berikut **tidak** ada di rencana awal — keduanya baru terlihat saat hasil implementasi ditinjau ulang baris-per-baris, bukan sekadar dipercaya dari ringkasan laporan. Dicatat di sini karena keduanya menunjukkan jenis kesalahan yang mudah lolos kalau hanya mengandalkan "kode berjalan tanpa error":

1. **`option_code` dikarang dari slug label (kritis, silent failure).** Implementasi awal Langkah 2 mengirim `slugify(label)` sebagai `option_code` untuk status yang belum pernah dikategorikan. Ini lolos type-check dan lolos constraint database (keduanya hanya memvalidasi *bentuk*, bukan *kebenaran* nilai) — kategorisasi akan tersimpan sukses di UI, tapi karena `option_code` karangan tidak pernah cocok dengan `SPLIT_PART(id_status_alumni,':',3)` yang dibaca Cube.js, angka KPI di dashboard **tidak akan pernah berubah**. Diperbaiki dengan menutup jalur input bebas sama sekali (§2.5), bukan menambah validasi — karena validasi tidak bisa membedakan slug yang kebetulan benar dari yang salah.
2. **`semantic_role` salah ketik di satu tooltip chart.** `Kpi6FieldRelevanceChart.tsx` memanggil formula dinamis dengan `semantic_role="kesesuaian_bidang"` — nama yang terdengar benar tapi tidak ada di registry (yang benar `relevansi_bidang`, lihat §1 tabel 4 kolom `question_code` untuk asal penamaannya). Akibatnya tooltip akan selalu kosong, gagal senyap tanpa error di console.

Pelajaran yang diambil: constraint database dan validasi tipe menjamin data **berbentuk benar**, bukan **berarti benar** — kesalahan makna (option_code yang valid secara format tapi tidak pernah match, nama variabel yang valid secara sintaks tapi salah role) hanya tertangkap lewat verifikasi langsung terhadap data nyata, bukan lewat lolos-tidaknya compiler atau constraint.

---

## 9. Yang belum selesai

Dicatat apa adanya sebagai daftar kerja lanjutan:

- Label tampilan untuk `digunakan_oleh` (mis. `"iku2_keterserapan"` → `"IKU 2 — Keterserapan"`) masih kamus statis di FE, belum dari API.
- Kolom "Dikelompokkan Oleh" di tabel audit Langkah 2 belum ada di skema `kpi_category_mapping` — sengaja tidak ditampilkan di UI daripada memalsukan nilai.
- Belum diuji lewat `php artisan etl:run` penuh terhadap data nyata end-to-end — verifikasi sejauh ini lewat transaksi yang di-rollback dan query langsung terhadap database dev.
