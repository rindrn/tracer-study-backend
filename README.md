# SmartTracer — Backend Service

Backend service untuk **Sistem Informasi Tracer Study (SmartTracer)**, dikembangkan sebagai bagian dari **Tugas Akhir (Final Project)** di Politeknik Negeri Bandung.

Dibangun dengan **Laravel (PHP)** dan menerapkan **Layered Architecture** (Controller → Service → Repository → DTO) agar kode rapi, mudah dirawat, dan aman untuk kebutuhan role-based access.

---

## Daftar Isi

1. [Ringkasan Arsitektur](#ringkasan-arsitektur)
2. [Tech Stack](#tech-stack)
3. [Struktur Project](#struktur-project)
4. [Dokumentasi (`docs/`)](#dokumentasi-docs)
5. [Pemetaan Semantik Dinamis](#pemetaan-semantik-dinamis)
6. [Setup & Menjalankan (Local)](#setup--menjalankan-local)
7. [Konfigurasi `.env`](#konfigurasi-env)
8. [Cara Menambah Modul Baru](#cara-menambah-modul-baru)

---

## Ringkasan Arsitektur

### Alur Request (High Level)

```
Frontend (React)
    │
    ▼
Laravel Controller        ← validasi input, mapping params
    │
    ▼
Service                   ← logika bisnis, kalkulasi (pct, total, dll.)
    │
    ▼
Repository                ← query ke PostgreSQL (OLTP) atau Cube.js (OLAP)
    │
    ▼
DTO (Data Transfer Object)← struktur response yang konsisten
    │
    ▼
JSON Response
```

### Mengapa React tidak langsung ke Cube.js?

Cube.js tetap berada di **belakang** Laravel agar:

- Token Cube.js tidak bocor ke client
- Query tidak bisa dimanipulasi sembarangan dari browser
- Kontrol akses berbasis role (mis. Kaprodi hanya bisa lihat prodi sendiri) tetap terpusat di satu tempat
- Rate limiting dan logging tetap berjalan di satu titik

### Dua Jenis Repository

| Repository | Koneksi | Digunakan untuk |
|---|---|---|
| `Transactional/` | PostgreSQL OLTP (`oltp`) | CRUD data master, ETL, import, auth |
| `Analytical/` | Cube.js REST API | Query KPI dashboard, pre-aggregation |

> Penjelasan arsitektur lengkap (Before vs After: MVC vs Layered + Cube.js) ada di: **`docs/architecture.md`**

---

## Tech Stack

| Komponen | Teknologi |
|---|---|
| Backend Framework | Laravel (PHP 8.2+) |
| Database Transaksional | PostgreSQL (koneksi `oltp`) |
| Database Analitik | PostgreSQL (koneksi `olap`) via Cube.js |
| Analytic Layer | Cube.js (JWT-secured, diakses dari backend) |
| Cache & Pre-aggregation Store | Redis (via `predis/predis`) |
| Auth | Laravel Sanctum (token-based) |
| Excel Import/Export | `maatwebsite/excel` |
| Data Eksternal | BPS Web API (untuk data UMP provinsi) |

---

## Struktur Project

```text
app/
├── DTOs/
│   └── Analytical/
│       └── Kesesuaian/
│           ├── KesesuaianBarDTO.php        ← struktur response bar chart
│           ├── KesesuaianPieDTO.php
│           ├── KesesuaianAlasanDTO.php
│           └── KesesuaianDrillDownDTO.php
├── Exceptions/                             ← custom exception handler
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── Analytical/
│   │           └── KesesuaianController.php
│   ├── Middleware/                         ← auth, role guard, dll.
│   └── Validators/                         ← (opsional) form request terpisah
├── Models/
│   └── Transactional/                      ← Eloquent model HANYA untuk OLTP
│       ├── RefUmp.php
│       └── ...
│   ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─
│   (Tidak ada Eloquent model untuk OLAP — query melalui Cube.js)
├── Providers/
│   └── AppServiceProvider.php              ← binding singleton CubeJsClient
├── Repositories/
│   ├── Analytical/
│   │   ├── BaseAnalyticalRepository.php    ← helper buildGlobalFilters, dll.
│   │   └── KesesuaianRepository.php
│   └── Transactional/                      ← (jika ada repo untuk OLTP)
└── Services/
    ├── Analytical/
    │   └── KesesuaianService.php
    ├── CubeJsClient.php                    ← HTTP client ke Cube.js (singleton)
    └── Transactional/                      ← service untuk data master, ETL, dll.

routes/
├── api.php                                 ← semua endpoint /api/*
├── web.php
└── console.php                             ← Laravel Scheduler (ETL mingguan)

database/
├── migrations/
└── seeders/

config/
├── cubejs.php                              ← CUBEJS_BASE_URL, CUBEJS_API_SECRET
├── excel.php                               ← config maatwebsite/excel
└── services.php                            ← BPS API key, dll.

docs/
resources/
public/
storage/
tests/
```

### Catatan Penting Struktur

- **`Models/`** hanya berisi Eloquent model untuk kebutuhan **transaksional** (CRUD, ETL, auth). Data analitik OLAP tidak memerlukan Eloquent — semua melalui `CubeJsClient`.
- **`DTOs/`** adalah output layer. Setiap endpoint punya DTO-nya sendiri sehingga shape response selalu konsisten dan tidak bergantung pada struktur internal.
- **`BaseAnalyticalRepository`** menyediakan helper `buildGlobalFilters()` yang dipakai semua repository analitik — sehingga filter `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot` konsisten di seluruh KPI.

---

## Dokumentasi (`docs/`)

| File | Isi |
|---|---|
| `docs/architecture.md` | Konsep arsitektur Before vs After + integrasi Cube.js |
| `docs/postgresql-connection-guide.md` | Panduan koneksi PostgreSQL (dual connection: oltp/olap) |
| `docs/postgresql-two-schema-bootstrap.sql` | Bootstrap SQL untuk skema PostgreSQL |
| `docs/database-blueprint-two-schema.md` | Rancangan/blueprint database (star schema, Kimball) |
| `docs/migration-checklist.md` | Checklist migrasi |
| `docs/api/` | Dokumentasi endpoint OLAP (13 segmen KPI) |
| `docs/semantic-mapping-architecture.md` | Dokumentasi arsitektur lengkap pemetaan semantik dinamis (10 bagian, termasuk riwayat bug & fix) |

---

## Pemetaan Semantik Dinamis

### Masalah yang diselesaikan

Sebelum fitur ini ada, hubungan "kode pertanyaan OLTP → kolom fact OLAP" dan "opsi jawaban → kategori KPI" (mis. status apa saja yang dihitung "Terserap") **di-hardcode** di beberapa tempat sekaligus: `OltpExtractRepository::RELEVANT_QUESTION_CODES`, `AlumniFactBuilderService`, `StatusAlumniDimService`, dan `FactTracerStudy.js` (Cube.js). Setiap kuesioner baru dengan kode berbeda (mis. `f8` → `f8_new`) atau perubahan definisi KPI (mis. status mana yang termasuk "terserap") butuh deploy ulang kode di 2 repo sekaligus.

4 tabel baru (murni aditif, tidak mengubah skema fact/dim yang ada) memindahkan hubungan ini ke data yang bisa diatur admin lewat UI, dengan versioning forward-only (`is_active` + `deactivated_at`, tidak pernah `DELETE`) mengikuti pola yang sudah ada di `lam_versions`/`thresholds`.

### Desain tabel

**1. `tracer_oltp.semantic_role_registry`** — kamus SEMUA "peran data" yang dikenal sistem. Baris ini **tidak berubah per kuesioner** — didefinisikan sekali oleh developer/admin sistem saat sebuah kebutuhan data baru muncul, bukan oleh alur mapping harian.

| Kolom | Sumber | Untuk apa |
|---|---|---|
| `role_key` | Ditentukan manual (PK) | Nama teknis unik dipakai sebagai kunci lookup di seluruh sistem, mis. `status_pekerjaan`, `pendapatan` |
| `label` | Ditentukan manual | Nama tampilan di UI (ini yang muncul sebagai isi kolom **"Peran Data"** di tabel Langkah 1) |
| `category` | Ditentukan manual | Domain KPI untuk pengelompokan dropdown (`keterserapan`, `waktu_tunggu`, `pendapatan`, dst.) — murni bantuan visual, mencegah salah pilih peran lintas domain |
| `expected_kind` | Ditentukan manual | Kontrak tipe data (`integer`/`decimal`/`categorical`/`boolean`/`text`/`date`) — divalidasi ETL saat menulis ke fact |
| `value_min` / `value_max` | Ditentukan manual | Batas wajar untuk role numerik (mis. `masa_tunggu_bekerja` 0–120 bulan) — di luar rentang ini dicatat sebagai anomali |
| `target_table` / `target_column` | Ditentukan manual | Kolom OLAP tujuan (fact/dim) tempat nilai role ini akhirnya ditulis oleh ETL |
| `grain` | Ditentukan manual | `narrow` = satu nilai per alumni (1 mapping aktif per kuesioner); `wide` = banyak `question_code` sah berbagi role yang sama (mis. 14 kode kompetensi `f1761`–`f1774` semua berperan `kompetensi_evaluasi`, dibedakan lewat `dim_indikator_evaluasi`) |

**2. `tracer_oltp.question_semantic_mapping`** — hasil **Langkah 1**: kode pertanyaan aktual di kuesioner tertentu ↔ salah satu `role_key` di atas.

| Kolom | Sumber | Untuk apa |
|---|---|---|
| `questionnaire_id`, `question_code` | Dipilih admin dari dropdown kode yang belum termapping | Identitas pertanyaan asli di OLTP |
| `question_text_snapshot` | Disalin otomatis dari `questionnaire_questions` saat mapping dibuat | Arsip teks pertanyaan pada saat itu (kuesioner bisa direvisi teksnya di masa depan tanpa mengubah histori ini) |
| `semantic_role` | Dipilih admin | FK ke `semantic_role_registry.role_key` — ini keputusan inti Langkah 1 |
| `grain` | Disalin dari registry saat insert | Duplikasi read-time karena predicate partial index Postgres harus immutable (tidak bisa subquery tabel lain) |
| `effective_date`, `is_active`, `deactivated_at` | Sistem / admin | Versioning forward-only |
| `mapped_by` | User login yang submit (baseline seed diarahkan ke `head.tracer@test.com`) | Atribusi/audit — kolom "Dipetakan Oleh" di tabel "Data Tersimpan" |

**3. `public.kpi_category_mapping`** — hasil **Langkah 2**: mengelompokkan opsi jawaban (option_code) dari sebuah role kategorikal menjadi kategori KPI. Sengaja hidup di schema `public` (bukan `tracer_oltp`) supaya query Cube.js (yang hanya baca schema `public`) bisa langsung subquery ke sini.

| Kolom | Sumber | Untuk apa |
|---|---|---|
| `semantic_role` | Sama seperti role di Langkah 1 (mis. `status_pekerjaan`) | Role kategorikal mana yang dikelompokkan |
| `option_code` | Dipilih admin, **wajib** dari daftar opsi asli `questionnaire_options` (endpoint `option-candidates`) — validasi server menolak kode karangan | Nilai OLTP mentah yang dikelompokkan |
| `option_label_snapshot` | Disalin otomatis dari opsi asli saat mapping dibuat | Label untuk tooltip dinamis di grafik ("Terserap = Bekerja + Wirausaha + ...") |
| `kpi_category`, `kpi_category_label` | Ditentukan admin | Kategori hasil bucketing, mis. `terserap`/`tidak`, `sesuai`/`tidak_sesuai` |
| `digunakan_oleh` | Ditentukan admin | Membedakan KPI mana yang mengonsumsi grouping ini — role yang sama bisa dipakai beberapa KPI dengan aturan bucket berbeda (lihat di bawah) |
| `effective_date`, `is_active`, `deactivated_at` | Sistem / admin | Versioning forward-only, **dan** dicocokkan point-in-time terhadap `dim_waktu.tanggal_refresh` tiap baris fact di Cube.js — supaya perubahan mapping admin tidak mengubah interpretasi snapshot historis yang sudah ada |

**4. `public.etl_anomaly_log`** — catatan gagal-validasi per jawaban saat ETL jalan (tipe tidak cocok `expected_kind`, atau di luar `value_min`/`value_max`). ETL tidak pernah crash karena ini — kolom fact terkait diisi `NULL` dan baris anomali dicatat untuk direview admin di menu "Log Anomali ETL".

### Alur Langkah 1 → Langkah 2 → Cube.js

```
Langkah 1 (question_semantic_mapping)
  "f8" di kuesioner Tracer 2026  →  role "status_pekerjaan"
                                            │
                                            ▼
Langkah 2 (kpi_category_mapping) — HANYA untuk role kategorikal
yang dikonsumsi sebagai bucket KPI, BUKAN semua role
  option "1" (Bekerja)      → kategori "terserap" → digunakan_oleh "iku2_keterserapan"
  option "1" (Bekerja)      → kategori "valid"    → digunakan_oleh "masa_tunggu_valid_status"
  option "2" (Belum kerja)  → kategori "tidak"    → digunakan_oleh "iku2_keterserapan"
                                            │
                                            ▼
Cube.js (FactTracerStudy.js) — measure count_terserap dkk melakukan
subquery point-in-time ke kpi_category_mapping (bukan hardcode list lagi)
                                            │
                                            ▼
Dashboard React (grafik Tren Keterserapan, dst.)
```

**Kenapa satu role bisa dipakai 3 `digunakan_oleh` sekaligus?** `status_pekerjaan` memberi makna berbeda tergantung KPI yang bertanya: untuk KPI Keterserapan, status "Melanjutkan Pendidikan" dihitung "terserap"; untuk KPI Masa Tunggu, status itu justru **dikecualikan** (tidak relevan dihitung masa tunggu kerja, karena melanjutkan studi bukan bekerja). Tanpa `digunakan_oleh`, satu baris mapping tidak bisa mewakili dua aturan bucket yang berbeda untuk role yang sama.

### Semantic Role vs kolom "Peran Data" di UI

**Keduanya konsep yang sama** — `semantic_role` adalah nama teknis (`role_key`), "Peran Data" adalah label Bahasa Indonesia yang ditampilkan (`semantic_role_registry.label`). Tidak ada perbedaan makna, hanya perbedaan representasi: satu untuk kode/API, satu untuk tampilan admin.

### Kenapa kode bisa punya "Peran Data" terisi tapi tidak muncul di kategori KPI manapun?

Karena **Langkah 2 hanya berlaku untuk role kategorikal yang perlu di-bucket jadi kategori KPI** (saat ini hanya `status_pekerjaan` dan `relevansi_bidang`). Sebagian besar role LAINNYA tidak pernah butuh Langkah 2 karena nilainya dipakai apa adanya oleh downstream:
- Role numerik (`pendapatan`, `masa_tunggu_bekerja`, `bulan_sebelum_lulus`, `kompetensi_evaluasi`) — nilai mentahnya langsung dipakai untuk perhitungan (rata-rata, perbandingan ke ambang dinamis), bukan dikelompokkan jadi kategori diskrit.
- Role teks (`nama_perusahaan`, `pt_lanjut`, `prodi_lanjut`, `jabatan_wirausaha`) — disimpan apa adanya sebagai atribut dimensi, tidak pernah jadi bucket KPI.
- Role kategorikal lain yang jadi FK dimensi (`provinsi_kerja`, `kota_kerja`, `jenis_perusahaan`, `tingkat_instansi`, `kesesuaian_level`, `sumber_biaya_lanjut`, `sumber_biaya_studi`) — dipakai sebagai label dimensi langsung (mis. nama provinsi di peta sebaran), bukan dikelompokkan jadi kategori KPI biner.

Jadi kode dengan "Peran Data" terisi tapi tanpa kategori KPI **bukan bug** — itu tandanya role tersebut bertipe numerik/teks/dimensi-langsung, bukan salah satu dari 2 role yang butuh bucketing eksplisit.

### Kenapa ada ~19 semantic role, bukan ~5 seperti rencana awal?

Rencana awal membayangkan hanya role yang punya threshold KPI eksplisit (mis. 5 "KPI inti": keterserapan, masa tunggu, kesesuaian bidang, pendapatan, kompetensi). Saat implementasi, ternyata **setiap kolom fact/dim yang sebelumnya diisi lewat kode pertanyaan hardcode** (bukan cuma yang punya threshold) perlu didaftarkan sebagai role supaya ETL bisa dijalankan sepenuhnya dari data mapping, bukan campuran data+kode. Contoh: `nama_perusahaan` atau `provinsi_kerja` tidak punya "KPI" dalam arti angka target, tapi tetap perlu tahu "kode pertanyaan mana yang mengisi kolom ini" secara dinamis supaya kuesioner baru dengan kode berbeda tidak butuh deploy ulang. Hasilnya: ~19 role menutup semua kolom yang tadinya hardcode, sementara hanya sebagian kecil (yang kategorikal dan dikonsumsi sebagai bucket KPI) lanjut ke Langkah 2.

### Cache & invalidasi

Semua endpoint `Analytical/*Service.php` (Keterserapan, MasaTunggu, Pendapatan, dst.) meng-cache response di Redis dengan TTL 1–24 jam untuk mengurangi beban ke Cube.js. Setiap `remember()` diberi tag `analytics-dashboard`. `KpiCategoryMappingService::store()/deactivate()` dan `QuestionSemanticMappingService::store()/deactivate()` memanggil `forgetTag('analytics-dashboard')` setelah berhasil menyimpan perubahan, sehingga perubahan mapping langsung terlihat di dashboard tanpa menunggu TTL habis.

---

## Setup & Menjalankan (Local)

### Prasyarat

- PHP 8.2+ & Composer
- PostgreSQL 14+
- Node.js (untuk menjalankan Cube.js secara terpisah)
- Redis (untuk cache Laravel dan pre-aggregation store Cube.js)
- Cube.js instance yang sudah berjalan (lihat repo Cube.js terpisah)
- Akun BPS Web API ([https://webapi.bps.go.id](https://webapi.bps.go.id)) — diperlukan untuk sinkronisasi data UMP

---

### Langkah 1 — Clone & Install

```bash
git clone <YOUR_GIT_URL>
cd smarttracer-backend

composer install
```

---

### Langkah 2 — Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

Lanjutkan isi `.env` sesuai bagian [Konfigurasi `.env`](#konfigurasi-env) di bawah.

---

### Langkah 3 — Buat API Key BPS

Sebelum menjalankan server, siapkan API key BPS terlebih dahulu karena sistem menggunakannya untuk sinkronisasi data UMP provinsi.

1. Daftar/login di [https://webapi.bps.go.id](https://webapi.bps.go.id)
2. Buat aplikasi baru → salin API key yang diberikan
3. Isi di `.env`:

```env
BPS_API_KEY=your_api_key_here
```

---

### Langkah 4 — Konfigurasi Database

Pastikan PostgreSQL sudah berjalan. Buat dua database (atau satu database dengan dua schema, sesuai blueprint):

```sql
-- Jalankan di psql / pgAdmin
CREATE DATABASE tracer_study;
```

Isi kredensial di `.env` (lihat bagian lengkap di bawah).

Lalu jalankan migrasi:

```bash
php artisan migrate
```

> Jika menggunakan bootstrap SQL two-schema: jalankan `docs/postgresql-two-schema-bootstrap.sql` terlebih dahulu sebelum migrasi.

---

### Langkah 5 — Install Package Excel

Package ini digunakan untuk fitur import data tracer study via Excel:

```bash
composer require maatwebsite/excel
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

---

### Langkah 6 — Install Redis Client (Predis)

Laravel membutuhkan PHP client untuk berkomunikasi dengan Redis. SmartTracer menggunakan `predis/predis`:

```bash
composer require predis/predis
```

Pastikan Redis sudah berjalan secara lokal (default port `6379`). Untuk instalasi Redis:

- **macOS:** `brew install redis && brew services start redis`
- **Ubuntu/Debian:** `sudo apt install redis-server && sudo systemctl start redis`
- **Windows:** Gunakan [Redis for Windows](https://github.com/microsoftarchive/redis/releases) atau WSL

Verifikasi Redis berjalan:

```bash
redis-cli ping
# Output: PONG
```

Redis digunakan untuk dua keperluan:
- **Laravel Cache** — cache response API agar tidak query berulang ke Cube.js
- **Cube.js Pre-aggregation Store** — menyimpan hasil pre-aggregation agar query dashboard lebih cepat (set `CUBEJS_REDIS_URL` di `.env` Cube.js)

---

### Langkah 7 — Konfigurasi Cube.js

Pastikan Cube.js sudah berjalan (repo terpisah). Isi di `.env`:

```env
CUBEJS_BASE_URL=http://localhost:4000
CUBEJS_API_SECRET=<salin dari .env Cube.js>
```

`CubeJsClient` di-register sebagai singleton di `AppServiceProvider`:

```php
$this->app->singleton(CubeJsClient::class, fn() => new CubeJsClient());
```

Artinya satu instance client digunakan selama satu request lifecycle — tidak ada koneksi berulang.

---

### Langkah 8 — Jalankan Server

```bash
php artisan serve
```

API tersedia di: `http://localhost:8000/api`

---

### (Opsional) Scheduler ETL

ETL mingguan dijadwalkan via Laravel Scheduler. Untuk menjalankan secara lokal:

```bash
# Jalankan sekali (manual trigger)
php artisan etl:run

# Atau aktifkan scheduler (harus ada cron atau jalankan terus-menerus)
php artisan schedule:work
```

---

### Queue Worker — WAJIB untuk auto-trigger ETL

Sejak fitur pemetaan semantik dinamis ada, menyimpan/menonaktifkan mapping di **Langkah 1** (`question_semantic_mapping`) otomatis men-trigger ETL penuh (`force: true`, menata ulang SEMUA jawaban historis, bukan cuma respons baru — lihat `App\Jobs\RunEtlJob`) lewat queue job, supaya endpoint simpan mapping tetap cepat dan tidak menggantung.

`QUEUE_CONNECTION=database` (bukan `sync`) — job ini **tidak akan pernah diproses** tanpa worker yang berjalan:

```bash
php artisan queue:work
```

Jalankan di terminal terpisah selama development. Tanpa ini, status ETL di UI ("Pemetaan Data Pertanyaan") akan tetap `queued` selamanya setelah simpan mapping. Di produksi, jalankan lewat process manager (mis. Supervisor) supaya worker otomatis restart kalau crash.

Status tiap eksekusi ETL (manual maupun auto-trigger) tercatat di `tracer_oltp.etl_runs` dan bisa dipoll lewat `GET /api/etl-runs/{id}` — inilah yang dipakai FE untuk menampilkan banner "ETL sedang berjalan...".

---

## Konfigurasi `.env`

Berikut adalah **semua variabel yang wajib diisi** untuk menjalankan sistem. Salin ke `.env` dan sesuaikan nilainya.

```env
# ── Aplikasi ──────────────────────────────────────────────────
APP_NAME=SmartTracer
APP_ENV=local
APP_KEY=                         # diisi otomatis oleh: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=id

# ── Database OLTP (Transaksional) ─────────────────────────────
# Digunakan untuk: auth, data master, ETL, import Excel
OLTP_DB_HOST=127.0.0.1
OLTP_DB_PORT=5432
OLTP_DB_DATABASE=tracer_study
OLTP_DB_USERNAME=postgres
OLTP_DB_PASSWORD=postgres

# ── Database OLAP (Analitik) ───────────────────────────────────
# Digunakan oleh Cube.js untuk membaca data warehouse
OLAP_DB_HOST=127.0.0.1
OLAP_DB_PORT=5432
OLAP_DB_DATABASE=tracer_study
OLAP_DB_USERNAME=postgres
OLAP_DB_PASSWORD=postgres

# ── Cube.js ────────────────────────────────────────────────────
# Base URL instance Cube.js (jalankan Cube.js terpisah)
CUBEJS_BASE_URL=http://localhost:4000
# Secret untuk generate JWT — salin dari .env Cube.js
CUBEJS_API_SECRET=

# ── Redis ──────────────────────────────────────────────────────
# Digunakan untuk Laravel Cache dan Cube.js pre-aggregation store
# Pastikan sudah install: composer require predis/predis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# ── Cache ──────────────────────────────────────────────────────
CACHE_STORE=redis

# ── Sanctum (CORS untuk frontend) ─────────────────────────────
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000

# ── BPS API (untuk sinkronisasi data UMP) ─────────────────────
# Daftar di: https://webapi.bps.go.id
BPS_API_KEY=

# ── Laravel Defaults (boleh dibiarkan) ────────────────────────
DB_CONNECTION=oltp
SESSION_DRIVER=array
# database (BUKAN sync) -- auto-trigger ETL (RunEtlJob) jalan lewat queue job,
# WAJIB ada `php artisan queue:work` berjalan, lihat bagian Queue Worker di atas.
QUEUE_CONNECTION=database
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

> **Catatan:** `DB_CONNECTION=oltp` adalah fallback default. Repository transaksional menggunakan `protected $connection = 'oltp'` secara eksplisit, dan `CubeJsClient` tidak menggunakan koneksi Eloquent sama sekali.

---

## Cara Menambah Modul Baru

Contoh: menambahkan KPI segmen **"Masa Tunggu Kerja"** (MasaTunggu).

Ikuti urutan layer dari dalam ke luar: **DTO → Repository → Service → Controller → Route**.

---

### Step 1 — Buat DTO

Buat satu file DTO per jenis response. Contoh untuk endpoint bar chart:

```
app/DTOs/Analytical/MasaTunggu/MasaTungguBarDTO.php
```

```php
<?php

namespace App\DTOs\Analytical\MasaTunggu;

class MasaTungguBarDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'data'    => $this->data,
        ];
    }
}
```

Buat DTO serupa untuk endpoint lain (pie, drill-down, dst.) sesuai kebutuhan.

---

### Step 2 — Buat Repository

```
app/Repositories/Analytical/MasaTungguRepository.php
```

```php
<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class MasaTungguRepository extends BaseAnalyticalRepository
{
    /**
     * Pre-agg: FactTracerStudy.distribusi_masa_tunggu
     */
    public function getBarData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimProdi.nama_prodi',
                'DimMasaTunggu.label',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ])->map(fn($r) => [
            'nama_prodi'   => $r['DimProdi.nama_prodi']      ?? '',
            'masa_tunggu'  => $r['DimMasaTunggu.label']      ?? '',
            'tahun_lulus'  => $r['DimAlumni.tahun_lulus']    ?? '',
            'count_alumni' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }
}
```

**Checklist Repository:**
- Extend `BaseAnalyticalRepository`
- Selalu gunakan `$this->buildGlobalFilters()` untuk filter standar
- Mapping field Cube.js → key PHP yang bersih di dalam `->map()`
- Satu method per jenis chart/query

---

### Step 3 — Buat Service

```
app/Services/Analytical/MasaTungguService.php
```

```php
<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\MasaTunggu\MasaTungguBarDTO;
use App\Repositories\Analytical\MasaTungguRepository;

class MasaTungguService
{
    public function __construct(
        private readonly MasaTungguRepository $repo,
    ) {}

    public function getBar(array $params): MasaTungguBarDTO
    {
        $raw = $this->repo->getBarData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $total = $raw->sum('count_alumni');

        $data = $raw->map(fn($r) => [
            ...$r,
            'pct' => $total > 0 ? round($r['count_alumni'] / $total * 100, 1) : 0.0,
        ])->values()->toArray();

        return new MasaTungguBarDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    private function activeFilters(array $params): array
    {
        $keys = ['jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'];
        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '',
        );
    }
}
```

**Checklist Service:**
- Hanya berisi logika bisnis (kalkulasi pct, konversi label, pagination logic)
- Tidak ada query langsung — semua delegasikan ke Repository
- Return DTO, bukan array mentah

---

### Step 4 — Buat Controller

```
app/Http/Controllers/Api/Analytical/MasaTungguController.php
```

```php
<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\MasaTungguService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MasaTungguController extends Controller
{
    public function __construct(
        private readonly MasaTungguService $service,
    ) {}

    public function bar(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getBar($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
```

**Checklist Controller:**
- Hanya bertanggung jawab atas: validasi input, memanggil service, mengembalikan response
- Tidak ada logika bisnis di sini
- Gunakan `try/catch` untuk `RuntimeException` dari layer bawah
- Semua filter menggunakan validasi `nullable` — tidak ada yang required kecuali untuk drill-down

---

### Step 5 — Daftarkan Route

Tambahkan di `routes/api.php`, di dalam group yang sesuai:

```php
// routes/api.php

Route::middleware(['auth:sanctum'])->prefix('dashboard')->group(function () {

    // ... route lain yang sudah ada ...

    // KPI: Masa Tunggu Kerja
    Route::prefix('masa-tunggu')->group(function () {
        Route::get('bar',        [MasaTungguController::class, 'bar']);
        Route::get('pie',        [MasaTungguController::class, 'pie']);        // jika ada
        Route::get('drill-down', [MasaTungguController::class, 'drillDown']); // jika ada
    });
});
```

Jangan lupa tambahkan `use` statement di bagian atas `api.php`:

```php
use App\Http\Controllers\Api\Analytical\MasaTungguController;
```

---

### Ringkasan Checklist Modul Baru

| # | Yang dibuat | Lokasi |
|---|---|---|
| 1 | DTO (per jenis response) | `app/DTOs/Analytical/NamaModul/` |
| 2 | Repository (query Cube.js) | `app/Repositories/Analytical/NamaModulRepository.php` |
| 3 | Service (logika bisnis) | `app/Services/Analytical/NamaModulService.php` |
| 4 | Controller (validasi + response) | `app/Http/Controllers/Api/Analytical/NamaModulController.php` |
| 5 | Route | `routes/api.php` |

> **Urutan penting:** selalu mulai dari DTO (tentukan dulu bentuk output), baru ke Repository, Service, Controller, Route. Ini memastikan tidak ada "kejutan" di shape response saat sudah sampai layer Controller.

---

## API Routes

Semua endpoint API didefinisikan di `routes/api.php`. Endpoint analitik mengikuti pola:

```
GET /api/dashboard/{kpi-slug}/bar
GET /api/dashboard/{kpi-slug}/pie
GET /api/dashboard/{kpi-slug}/drill-down
```

Dokumentasi lengkap 13 segmen KPI ada di folder `docs/api/`.

---

## Lisensi

Internal — untuk kebutuhan Tugas Akhir Politeknik Negeri Bandung.
