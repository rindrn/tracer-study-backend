# Cara Menjalankan Instalasi Basis Data

Panduan singkat menyiapkan basis data SmartTracer dari nol.

`{nama_db}` di seluruh perintah adalah nama basis data pilihan Anda —
ganti dengan nama yang sama persis di setiap langkah, termasuk di `.env`.

## Yang perlu ada dulu

- PostgreSQL 15+ jalan di `127.0.0.1:5432`
- `psql` bisa dipanggil dari terminal — seeder memakainya untuk mengimpor
  berkas `.sql`. Di Windows biasanya perlu ditambahkan ke PATH:
  `C:\Program Files\PostgreSQL\17\bin`
- `database/dump/oltp_real_data.sql` (52 MB, data alumni asli).
  **Tidak ada di repositori** karena berisi data pribadi. Minta ke tim, atau
  bangun dari basis data yang sudah terisi:
  `bash scripts/rebuild-data-dumps.sh {nama_db}`

Tanpa berkas itu instalasi tetap berhasil — hasilnya basis data berisi master
data saja (prodi, kuesioner, akun staf), siap diisi lewat aplikasi.

## Langkah

**1. Buat basis data**

```bash
psql -U postgres -c "CREATE DATABASE {nama_db}"
```

**2. Arahkan `.env`**

OLTP dan OLAP menempati satu basis data yang sama, dibedakan oleh schema
(`tracer_oltp` dan `public`):

```
OLTP_DB_DATABASE={nama_db}
OLAP_DB_DATABASE={nama_db}
```

**3. Bangun schema**

```bash
php artisan migrate
```

Membuat seluruh tabel di schema `tracer_oltp`. Schema OLAP belum dibuat di
langkah ini — itu tugas seeder.

**4. Isi data**

```bash
php artisan db:seed
```

Empat langkah berurutan: schema OLAP (`public`), ekstensi `pg_trgm`, master
data OLTP, lalu data alumni asli. Berkas terakhir dilewati dengan peringatan
kalau tidak ada.

**5. Bangun data dashboard**

```bash
php -d memory_limit=2G artisan etl:run
```

Mengisi tabel `dim_*` dan `fact_*` di schema `public` dari data OLTP. Tanpa
langkah ini seluruh dashboard kosong. `memory_limit` wajib dinaikkan —
batas bawaan 128 MB tidak cukup dan prosesnya mati di tengah jalan. Perlu
sekitar 7 menit.

**6. Schema untuk Cube.js**

```bash
psql -U postgres -d {nama_db} -c "CREATE SCHEMA IF NOT EXISTS dev_pre_aggregations"
```

Cube.js mengisinya sendiri saat query pertama datang. Pastikan
`CUBEJS_DB_NAME` di repositori `tracer-study-analytics` menunjuk basis data
yang sama.

## Mengulang dari awal

```bash
php artisan migrate:fresh
php artisan db:seed
```

`migrate:fresh` hanya membersihkan `tracer_oltp`; schema `public` dijatuhkan
dan dibuat ulang oleh seeder, jadi aman diulang berkali-kali.

## Data karangan untuk pengembangan

Kalau tidak punya data asli dan hanya butuh isi untuk mencoba tampilan:

```bash
php artisan db:seed --class=DemoSeeder
```

Menghasilkan alumni, respons, dan kontak penilai palsu lewat Faker.
**Hanya untuk basis data yang baru dimigrasi dan masih kosong.** Dijalankan
di atas data asli, kuesionernya tergandakan dan laporan menghitung dobel —
jawaban terhubung ke pertanyaan lewat `question_code`, bukan foreign key,
jadi basis data tidak akan menolaknya.

## Kalau memulihkan dari `init.sql`

`init.sql` adalah dump lengkap produksi, bukan hasil migrasi. Skemanya
tertinggal beberapa langkah, jadi urutannya berbeda:

```bash
psql -U postgres -d {nama_db} -c "DROP SCHEMA IF EXISTS public CASCADE"
psql -U postgres -d {nama_db} -f database/dump/init.sql
psql -U postgres -d {nama_db} -f database/dump/005_reconcile_init.sql
php artisan migrate
```

`005_reconcile_init.sql` menjembatani selisihnya: menandai migrasi yang
tabelnya sudah ada di dump, dan membuat satu tabel yang tercatat sudah jalan
tapi tidak ikut ter-dump. Tanpa itu `migrate` berhenti dengan galat
"relation already exists".

Jangan jalankan `db:seed` sesudah ini — datanya sudah ikut di `init.sql`.

## Akun untuk masuk

```
head.tracer@test.com / password123     Ketua Tracer (akses penuh)
spmi@test.com        / password123     Pimpinan
```

Alumni belum bisa masuk: kolom `email` dan `password` di `alumni_profiles`
kosong untuk seluruh 10.257 baris — data impor tidak membawa keduanya.
