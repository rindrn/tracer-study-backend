# SmartTracer — Backend Service

Backend service untuk **Sistem Informasi Tracer Study (SmartTracer)**, dikembangkan sebagai bagian dari **Tugas Akhir (Final Project)** di Politeknik Negeri Bandung.

Dibangun dengan **Laravel (PHP)** dan menerapkan **Layered Architecture** (Controller → Service → Repository → DTO) agar kode rapi, mudah dirawat, dan aman untuk kebutuhan role-based access.

---

## Daftar Isi

1. [Ringkasan Arsitektur](#ringkasan-arsitektur)
2. [Tech Stack](#tech-stack)
3. [Struktur Project](#struktur-project)
4. [Dokumentasi (`docs/`)](#dokumentasi-docs)
5. [Setup & Menjalankan (Local)](#setup--menjalankan-local)
6. [Konfigurasi `.env`](#konfigurasi-env)
7. [Cara Menambah Modul Baru](#cara-menambah-modul-baru)

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
QUEUE_CONNECTION=sync
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
