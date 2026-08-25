# Cara Menjalankan Instalasi Basis Data

Panduan singkat menyiapkan basis data SmartTracer dari nol.

`{nama_db}` di seluruh perintah adalah nama basis data pilihan Anda —
ganti dengan nama yang sama persis di setiap langkah, termasuk di `.env`.

## Yang perlu ada dulu

- PostgreSQL 15+ jalan di `127.0.0.1:5432`
- `psql` bisa dipanggil dari terminal — seeder memakainya untuk mengimpor
  berkas `.sql`. Di Windows biasanya perlu ditambahkan ke PATH:
  `C:\Program Files\PostgreSQL\17\bin`
Seluruh berkas data sudah ikut di repositori, termasuk
`database/dump/oltp_real_data.sql` (51 MB) yang berisi profil dan jawaban
alumni. Tidak ada yang perlu diminta ke siapa pun — cukup `clone` lalu
ikuti langkah di bawah.

> Karena berkas itu memuat data pribadi asli (nama, NIM, jawaban),
> **repositori ini harus tetap privat.**

Kalau berkas itu sengaja dihapus, instalasi tetap berhasil — hasilnya basis
data berisi master data saja (prodi, kuesioner, akun staf), siap diisi lewat
aplikasi.

## Langkah

**1. Buat basis data dan schema OLTP**

```bash
psql -U postgres -c "CREATE DATABASE {nama_db}"
psql -U postgres -d {nama_db} -c "CREATE SCHEMA IF NOT EXISTS tracer_oltp"
```

Baris kedua wajib. Koneksi `oltp` memakai `search_path = tracer_oltp`, jadi
`migrate` butuh schema itu sudah ada sebelum bisa membuat tabel `migrations`
sekalipun. Tidak ada migration yang bisa membuatnya sendiri — mereka semua
sudah berjalan di dalam schema tersebut. Tanpa baris ini `migrate` berhenti
dengan `SQLSTATE[3F000]: no schema has been selected to create in`.

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

Mengisi dengan **data karangan** (Faker): schema OLAP (`public`), ekstensi
`pg_trgm`, lalu alumni, respons, dan kontak penilai palsu. Aman dijalankan di
komputer mana pun.

Kalau punya berkas data alumni asli dan memang ingin memakainya, mintalah
eksplisit:

```bash
php artisan db:seed --class=RealDataSeeder
```

Empat langkah berurutan: schema OLAP (`public`), ekstensi `pg_trgm`, master
data OLTP, lalu data alumni asli. Berkas terakhir dilewati dengan peringatan
kalau tidak ada. Jangan mencampur kedua jalur di satu basis data — lihat
peringatan di bagian **Data karangan untuk pengembangan**.

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
php artisan migrate:fresh --seed
```

`migrate:fresh` hanya membersihkan `tracer_oltp`; schema `public` dijatuhkan
dan dibuat ulang oleh seeder, jadi aman diulang berkali-kali. Hasilnya data
karangan — `--seed` tidak pernah menyentuh berkas data asli.

## Kalau datanya berubah

Berkas dump di `database/dump/` adalah cerminan sebuah basis data, bukan
berkas yang disunting tangan. Sesudah mengubah data di basis data, bangun
ulang supaya repositori ikut terbarui:

```bash
bash scripts/rebuild-data-dumps.sh {nama_db}
```

Skrip ini **membaca** dari basis data yang sudah terisi — bukan membuat data
dari nol. Jadi jalankan di komputer yang basis datanya sudah lengkap, lalu
commit berkas yang berubah.

## Data karangan untuk pengembangan

Inilah jalur bawaan, jadi `php artisan db:seed` sudah cukup. Memanggil
kelasnya langsung juga boleh dan hasilnya sama persis:

```bash
php artisan db:seed --class=DemoSeeder
```

Menghasilkan alumni, respons, dan kontak penilai palsu lewat Faker.
**Hanya untuk basis data yang baru dimigrasi dan masih kosong.** Dijalankan
di atas data asli, kuesionernya tergandakan dan laporan menghitung dobel —
jawaban terhubung ke pertanyaan lewat `question_code`, bukan foreign key,
jadi basis data tidak akan menolaknya. Berlaku sebaliknya juga: jangan
menjalankan `--class=RealDataSeeder` di atas basis data yang sudah diisi
jalur demo.

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
