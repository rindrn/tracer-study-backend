# Tracer Study Backend Service

Backend service untuk **Sistem Informasi Tracer Study**, dikembangkan sebagai bagian dari **Tugas Akhir (Final Project)**.

Backend ini dibangun dengan **Laravel (PHP)** dan menerapkan pendekatan **Layered Architecture** (Controller → Service → Repository → DTO) agar kode lebih rapi, mudah dirawat, dan aman untuk kebutuhan role-based access.

---

## Ringkasan Arsitektur

### Alur request (high level)

Frontend (React) → **Laravel Controller** → **Service** → **Repository** → *(PostgreSQL / Cube.js API)* → **DTO** → Response JSON

### Kenapa tidak langsung React → Cube.js?
Cube.js tetap berada di belakang backend (Laravel) agar:
- Token tidak bocor di client
- Query tidak bisa dimanipulasi sembarangan
- Kontrol akses berbasis role (mis. Kaprodi hanya prodi sendiri) tetap terpusat

> Penjelasan arsitektur lengkap (Before vs After: MVC vs Layered + Cube.js) ada di dokumen: **`docs/architecture.md`**.

---

## Tech Stack

- **Laravel / PHP**
- **PostgreSQL** (lihat dokumen pada folder `docs/`)
- (Opsional) **Cube.js** sebagai analytic layer untuk kebutuhan dashboard/OLAP

---

## Struktur Project (Ringkasan)

```text
app/
  DTOs/
  Exceptions/
  Http/
    Controllers/
    Middleware/
    Validators/
  Models/
  Providers/
  Repositories/
  Services/

routes/
  api.php
  web.php
  console.php

database/
config/
resources/
public/
storage/
tests/
docs/
```

---

## Dokumentasi (Folder `docs/`)

Dokumen yang tersedia:
- `docs/postgresql-connection-guide.md` — panduan koneksi PostgreSQL
- `docs/postgresql-two-schema-bootstrap.sql` — bootstrap SQL untuk skema PostgreSQL (two-schema)
- `docs/database-blueprint-two-schema.md` — rancangan/blueprint database
- `docs/migration-checklist.md` — checklist migrasi
- `docs/architecture.md` — konsep arsitektur (Before vs After) + Cube.js

---

## Cara Menjalankan (Local Development)

### Prasyarat
- PHP + Composer
- PostgreSQL

### Langkah

1) Clone
```bash
git clone <YOUR_GIT_URL>
cd tracer-study-backend
```

2) Install dependencies
```bash
composer install
```

3) Setup environment
```bash
cp .env.example .env
php artisan key:generate
```

4) Set konfigurasi database di `.env`  
Minimal:
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

> Untuk panduan detail PostgreSQL, baca `docs/postgresql-connection-guide.md`.

5) Jalankan migrasi (jika memakai migration)
```bash
php artisan migrate
```

> Jika kamu memakai bootstrap SQL two-schema, gunakan `docs/postgresql-two-schema-bootstrap.sql` sesuai kebutuhan.

6) Jalankan server
```bash
php artisan serve
```

---

## API Routes

- Endpoint API didefinisikan di: `routes/api.php`

---

## Lisensi

Internal / untuk kebutuhan Tugas Akhir (sesuaikan jika ingin open-source).
