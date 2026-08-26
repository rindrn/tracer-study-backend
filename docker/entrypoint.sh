#!/bin/sh
# ---------------------------------------------------------------------------
# Entrypoint bersama untuk service app, worker, dan scheduler.
#
# Dijalankan oleh ketiganya, tetapi tugas sekali-jalan (migrasi, cache) hanya
# dikerjakan container yang diberi RUN_MIGRATIONS=true -- yaitu service "app"
# di docker-compose. Kalau ketiganya migrate bersamaan, mereka berebut lock
# tabel migrations dan salah satu gagal start.
# ---------------------------------------------------------------------------
set -e

log() { echo "[entrypoint] $*"; }

# --- 1. Tunggu Postgres siap ----------------------------------------------
# depends_on: healthy sudah menjamin ini, tapi restart mendadak (mis. VPS
# reboot) bisa membuat app naik lebih dulu. Menunggu di sini lebih murah
# daripada crash-loop.
if [ -n "${OLTP_DB_HOST}" ]; then
    log "menunggu Postgres di ${OLTP_DB_HOST}:${OLTP_DB_PORT:-5432}"
    until pg_isready -h "${OLTP_DB_HOST}" -p "${OLTP_DB_PORT:-5432}" -U "${OLTP_DB_USERNAME}" -q; do
        sleep 2
    done
    log "Postgres siap"
fi

# --- 2. Sinkronkan public/ ke volume bersama ------------------------------
# nginx menyajikan file statis Laravel dari volume yang sama. Salin ulang tiap
# start supaya versi baru image ikut terbawa.
if [ -d /var/www/public-src ]; then
    cp -a /var/www/public-src/. /var/www/html/public/ 2>/dev/null || true
fi

# --- 3. Tugas sekali-jalan ------------------------------------------------
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    # Basis data ini memakai TIGA schema:
    #
    #   tracer_oltp          -- 39 tabel transaksional, dibuat migration.
    #                           Harus ada SEBELUM migrate: koneksi 'oltp'
    #                           ber-search_path tracer_oltp, dan tidak ada
    #                           migration yang bisa membuat schema-nya sendiri.
    #                           Tanpa ini migrate berhenti di SQLSTATE[3F000].
    #   public               -- star schema OLAP (koneksi 'olap'). Sudah ada
    #                           bawaan Postgres; ISINYA datang dari
    #                           dump/olap_schema.sql lewat seeder, bukan dari
    #                           migration. Lihat catatan bootstrap di README --
    #                           seeder itu melakukan DROP SCHEMA public CASCADE
    #                           sehingga TIDAK BOLEH jalan otomatis di sini.
    #   dev_pre_aggregations -- tabel pra-agregasi Cube. Cube yang mengisinya,
    #                           tapi schema-nya perlu disiapkan lebih dulu.
    #
    log "memastikan schema tracer_oltp & dev_pre_aggregations ada"
    PGPASSWORD="${OLTP_DB_PASSWORD}" psql \
        -h "${OLTP_DB_HOST}" -p "${OLTP_DB_PORT:-5432}" \
        -U "${OLTP_DB_USERNAME}" -d "${OLTP_DB_DATABASE}" -v ON_ERROR_STOP=1 <<'SQL'
CREATE SCHEMA IF NOT EXISTS tracer_oltp;
CREATE SCHEMA IF NOT EXISTS dev_pre_aggregations;
SQL

    log "menjalankan migrasi"
    php artisan migrate --force --no-interaction

    log "menyusun cache konfigurasi"
    php artisan config:cache
    php artisan route:cache
    # view:cache dilewati: satu-satunya blade (welcome) tidak punya route.

    php artisan storage:link --force 2>/dev/null || true
else
    # Worker dan scheduler tetap butuh config ter-cache supaya tidak membaca
    # ulang seluruh berkas config tiap kali job dijalankan.
    php artisan config:cache >/dev/null
fi

log "menjalankan: $*"
exec "$@"
