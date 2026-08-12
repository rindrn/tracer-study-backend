#!/usr/bin/env bash
#
# Membangun ulang ketiga berkas data-only di database/dump/ dari sebuah basis
# data OLTP yang sudah dimigrasi penuh.
#
#     bash scripts/rebuild-data-dumps.sh [nama_basis_data]
#
# Basis data sumber harus sudah `php artisan migrate` sampai habis -- itu yang
# menjamin kolomnya cocok dengan skema terbaru. Kredensial dibaca dari .env.
#
# Yang dihasilkan:
#   oltp_master_data.sql  referensi, konfigurasi, akun staf, template kuesioner
#   oltp_real_data.sql    profil alumni + seluruh jawaban (DATA PRIBADI ASLI)
#   oltp_config_data.sql  bagian konfigurasi saja, dipakai DemoSeeder
#
# Sengaja TIDAK ikut: migrations, roles, app_settings (ketiganya diisi sendiri
# oleh migration) dan personal_access_tokens (token sesi, tidak layak dibawa).
set -euo pipefail

cd "$(dirname "$0")/.."
SOURCE_DB="${1:-tracer_recon}"

export PGPASSWORD=$(grep '^OLTP_DB_PASSWORD=' .env | cut -d= -f2- | tr -d '\r"')
PGHOST=$(grep '^OLTP_DB_HOST=' .env | cut -d= -f2- | tr -d '\r"')
PGPORT=$(grep '^OLTP_DB_PORT=' .env | cut -d= -f2- | tr -d '\r"')
PGUSER=$(grep '^OLTP_DB_USERNAME=' .env | cut -d= -f2- | tr -d '\r"')

PG="pg_dump -h ${PGHOST} -p ${PGPORT} -U ${PGUSER} -d ${SOURCE_DB} --data-only --no-owner --no-privileges --schema=tracer_oltp"

echo "Sumber: ${SOURCE_DB}"

MASTER="database/dump/oltp_master_data.sql"
REAL="database/dump/oltp_real_data.sql"
CONFIG="database/dump/oltp_config_data.sql"

# Tabel konfigurasi yang TIDAK dihasilkan seeder PHP mana pun. Dipakai
# DemoSeeder, yang membuat sendiri programs/users/questionnaires lewat Faker.
CONFIG_TABLES="lams lam_versions lam_programs threshold_indicators thresholds \
threshold_configs ref_ump semantic_role_registry question_semantic_mapping \
tracer_response_thresholds"

MASTER_TABLES="provinces cities programs jurusans users permissions role_permissions \
lams lam_versions lam_programs threshold_indicators thresholds threshold_configs \
ref_ump semantic_role_registry questionnaires questionnaire_sections \
questionnaire_questions questionnaire_options question_semantic_mapping \
tracer_response_thresholds"

REAL_TABLES="alumni_profiles responses response_answers employment_records \
education_records questionnaire_assignments"

emit_header() {
  cat > "$1" <<HEADER
-- $2
--
-- Diekstrak dari tracer_recon: init.sql yang sudah di-restore, direkonsiliasi
-- (005_reconcile_init.sql), lalu dimigrasi penuh. Karena itu kolomnya sudah
-- cocok dengan skema hasil \`php artisan migrate\` terbaru.
--
-- HANYA DATA -- tidak ada CREATE TABLE sama sekali. Skema dimiliki migrasi.
-- Urutan tabel mengikuti dependensi foreign key.
--
-- Jangan disunting tangan. Bangun ulang lewat scripts/rebuild-data-dumps.sh
-- kalau isinya perlu diperbarui.

SET client_encoding = 'UTF8';
SET client_min_messages = warning;

HEADER
}

emit_header "$MASTER" "Data master OLTP: referensi, konfigurasi, akun staf, dan template kuesioner."
emit_header "$REAL" "Data asli OLTP: profil alumni dan seluruh jawaban tracer study."
emit_header "$CONFIG" "Konfigurasi OLTP untuk instalasi demo: LAM, ambang, UMP, dan peran semantik."

for t in $CONFIG_TABLES; do
  echo "  config: $t"
  $PG -t "tracer_oltp.$t" | grep -vE '^(--|SET |SELECT pg_catalog.set_config|\\restrict|\\unrestrict)' >> "$CONFIG"
done

for t in $MASTER_TABLES; do
  echo "  master: $t"
  $PG -t "tracer_oltp.$t" | grep -vE '^(--|SET |SELECT pg_catalog.set_config|\\restrict|\\unrestrict)' >> "$MASTER"
done

for t in $REAL_TABLES; do
  echo "  real:   $t"
  $PG -t "tracer_oltp.$t" | grep -vE '^(--|SET |SELECT pg_catalog.set_config|\\restrict|\\unrestrict)' >> "$REAL"
done

ls -la "$MASTER" "$REAL" "$CONFIG"
