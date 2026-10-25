# Deploy SmartTracer dengan Docker

Susunan satu VPS: nginx edge → (SPA React | Laravel php-fpm) → Postgres, Redis, Cube.

```
             :80
              │
        ┌─────▼─────┐
        │   nginx   │  satu-satunya port publik
        └──┬─────┬──┘
     /     │     │  /api, /sanctum, /storage
   ┌───────▼─┐ ┌─▼──────────┐
   │   fe    │ │    app     │ php-fpm ─┐
   │  (SPA)  │ │  Laravel   │          │
   └─────────┘ └────────────┘          │
                worker  scheduler      │
                   │        │          │
              ┌────▼────────▼──────────▼────┐
              │  postgres    redis    cube  │  internal saja
              └─────────────────────────────┘
```

Cube tidak pernah diakses browser. FE memanggil `/api`, Laravel yang meneruskan
query ke `http://cube:4000` dengan JWT (`config/cubejs.php`).

## Berkas

Compose ini tinggal di repositori backend tetapi membangun ketiga repositori
sekaligus. Karena itu **ketiga repositori harus berada dalam satu folder induk
yang sama**. Di VPS produksi folder induknya `/opt/tracer-study/`:

```
/opt/tracer-study/
├── tracer-study-backend/      <- repositori ini
│   └── deploy/                <- berkas di tabel bawah
├── fe-tracer-study/
├── tracer-study-analytics/    <- juga memuat model Cube, dipasang read-only ke service `cube`
├── certbot/                   <- sertifikat Let's Encrypt (lihat bagian HTTPS)
└── renew-cert.sh              <- perpanjang sertifikat, dipanggil cron harian
```

> Model Cube dulu sempat berada di clone terpisah bernama `cube/`. Clone itu
> sudah dihapus — satu-satunya sumber model sekarang `tracer-study-analytics/`
> (service `cube` memasangnya lewat `../../tracer-study-analytics/tracer-analytics`).

| Berkas | Isi |
|---|---|
| `deploy/docker-compose.yml` | Delapan service aktif + batas memori (plus `metabase` opsional yang tidak dipakai) |
| `deploy/docker-compose.override.yml` | Override server produksi: port 443/HTTPS, `nginx-local`, mount certbot |
| `deploy/nginx/default.conf` | nginx edge dasar (HTTP): pembagian jalur dan fastcgi |
| `deploy/nginx-local/default.conf` | nginx edge produksi (HTTPS), dipakai lewat override |
| `deploy/.env.example` | Contoh variabel; salin ke `deploy/.env` |
| `Dockerfile` | Image PHP-FPM (dipakai app, worker, scheduler) |
| `docker/` | `entrypoint.sh`, `php.ini`, `php-fpm.conf` |
| `../fe-tracer-study/Dockerfile` | Build Vite → nginx statis |

## Persiapan VPS

Swap wajib di VPS 2 GB, terutama saat `composer install` dan build FE:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## Menjalankan

```bash
cd deploy
cp .env.example .env      # lalu isi APP_KEY, POSTGRES_PASSWORD, CUBEJS_API_SECRET
# produksi: set juga COMPOSE_PROJECT_NAME=smarttracer di .env, supaya nama project
# konsisten dan docker-compose.override.yml (HTTPS) ikut termuat otomatis
docker compose up -d --build
docker compose logs -f app
```

Entrypoint container `app` mengerjakan otomatis: menunggu Postgres, membuat
schema `tracer_oltp` dan `dev_pre_aggregations`, `migrate --force`, lalu
`config:cache` dan `route:cache`.

## Tiga schema

Satu basis data, tiga schema, dan hanya satu di antaranya yang lahir dari
migration:

| Schema | Isi | Sumber | Koneksi Laravel |
|---|---|---|---|
| `tracer_oltp` | 39 tabel transaksional | 40 migration | `oltp` (default) |
| `public` | star schema OLAP + config ETL | `database/dump/olap_schema.sql` lewat seeder, lalu diisi `etl:run` | `olap` |
| `dev_pre_aggregations` | pra-agregasi Cube | Cube mengisinya sendiri | — |

Namanya memang `dev_pre_aggregations` sekalipun di produksi — nilai itu dipakai
apa adanya di dokumen struktur data, jangan diganti.

## Bootstrap sekali-jalan

`migrate` hanya membangun `tracer_oltp`. Schema OLAP tidak punya migration sama
sekali, jadi tanpa langkah ini seluruh dashboard error saat query.

> **Jalankan sekali saja, di basis data yang masih kosong.** Seeder melakukan
> `DROP SCHEMA public CASCADE` sebelum mengimpor rangka star schema. Karena itu
> ia sengaja TIDAK dipanggil dari entrypoint — kalau ikut jalan tiap restart,
> seluruh data OLAP hilang tiap kali container naik.

```bash
# data karangan (Faker) -- aman di mana pun
docker compose exec app php artisan db:seed --force

# atau, kalau memakai data alumni asli
docker compose exec app php artisan db:seed --class=RealDataSeeder --force
```

Keduanya juga memasang ekstensi `pg_trgm` di schema `public` (dipakai deteksi
pertanyaan bermakna sama; tanpa itu endpoint `/api/question-semantic-mappings/similar`
membalas 500). Jangan mencampur kedua jalur di satu basis data — template
kuesioner akan tergandakan dan laporan menghitung dobel.

Lalu isi tabel dimensi dan fakta:

```bash
docker compose exec app php artisan etl:run --reason=bootstrap
```

Tidak perlu `-d memory_limit=2G` seperti di panduan lokal: `docker/php.ini`
sudah memasang 2G untuk jalur CLI. Prosesnya sekitar 7 menit.

## Build FE di laptop, bukan di VPS

Build Vite memuat ~50 dependency dan sering OOM di 2 GB. Cara aman:

```bash
# di laptop, dari folder induk yang memuat ketiga repositori
docker build -t smarttracer-fe:latest ./fe-tracer-study
docker save smarttracer-fe:latest | gzip | ssh vps 'gunzip | docker load'
# di VPS: hapus blok build: pada service fe, atau cukup
docker compose up -d --no-build fe
```

## Deploy versi baru

```bash
git pull
docker compose build app fe
docker compose up -d
```

`opcache.validate_timestamps=0` berarti PHP tidak mengecek perubahan berkas,
jadi container `app` **harus** direstart tiap deploy — `up -d` di atas sudah
melakukannya karena image-nya berubah.

## HTTPS

Di server produksi TLS sudah aktif lewat `docker-compose.override.yml` (termuat
otomatis saat menjalankan compose dari folder `deploy`): service nginx membuka
port 443 dan memakai `deploy/nginx-local/default.conf`, dengan sertifikat
Let's Encrypt dipasang read-only dari `/opt/tracer-study/certbot/`. Sertifikat
diperpanjang otomatis oleh `/opt/tracer-study/renew-cert.sh` (dipanggil cron
harian) yang menjalankan `certbot renew` lalu me-reload nginx.

`docker-compose.yml` dasar sendiri hanya membuka port 80; seluruh konfigurasi
HTTPS berasal dari override.

## Backup

Volume `pgdata` menyimpan seluruh data. Cadangkan ke `deploy/backup/` yang
sudah dipasang ke container Postgres:

```bash
docker compose exec postgres pg_dump -U smarttracer -d tracer_study -Fc \
  -f /backup/tracer_study-$(date +%F).dump
```

Restore:

```bash
docker compose exec postgres pg_restore -U smarttracer -d tracer_study \
  --clean --if-exists /backup/tracer_study-2026-08-26.dump
```

## Perawatan rutin

```bash
docker compose exec app php artisan etl:run --reason=manual   # ETL manual
docker compose exec app php artisan queue:failed              # job gagal
docker compose logs -f cube                                   # log Cube
docker stats                                                  # pemakaian RAM
```

Kalau `docker stats` menunjukkan `app` atau `cube` mendekati `mem_limit` terus
menerus, itu tandanya VPS 2 GB sudah tidak cukup — naikkan `mem_limit` setelah
pindah ke 4 GB.
