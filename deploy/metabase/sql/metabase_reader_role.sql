-- Role read-only untuk Metabase, dipakai sebagai data source koneksi analitik
-- ke database tracer_study (schema public / OLAP). Idempoten: aman dijalankan ulang.
--
-- Jalankan sebagai superuser:
--   docker compose exec -T postgres psql -U smarttracer -d tracer_study \
--     -v reader_password='ganti-password-ini' \
--     -f metabase/sql/metabase_reader_role.sql

SELECT format('CREATE ROLE metabase_reader LOGIN PASSWORD %L', :'reader_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'metabase_reader')
\gexec

SELECT format('ALTER ROLE metabase_reader PASSWORD %L', :'reader_password')
WHERE EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'metabase_reader')
\gexec

GRANT CONNECT ON DATABASE tracer_study TO metabase_reader;
GRANT USAGE ON SCHEMA public TO metabase_reader;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO metabase_reader;

-- Supaya tabel baru yang dibuat migration Laravel di masa depan otomatis ter-cover.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT ON TABLES TO metabase_reader;

-- Tidak ada GRANT apa pun ke schema tracer_oltp (data mentah/PII) atau
-- dev_pre_aggregations (cache internal Cube.js) - role ini murni untuk OLAP.
