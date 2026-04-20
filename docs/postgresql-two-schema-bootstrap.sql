-- Bootstrap script for one PostgreSQL database with two schemas
-- Usage example:
-- psql -h <host> -U <user> -d tracer_study -f docs/postgresql-two-schema-bootstrap.sql

BEGIN;

-- 1) Ensure schemas exist
CREATE SCHEMA IF NOT EXISTS tracer_oltp;
CREATE SCHEMA IF NOT EXISTS tracer_olap;

-- 2) Optional role strategy (adjust names and passwords for your environment)
-- CREATE ROLE tracer_app LOGIN PASSWORD 'change_me';
-- CREATE ROLE tracer_readonly LOGIN PASSWORD 'change_me';

-- 3) Grant schema usage
-- GRANT USAGE ON SCHEMA tracer_oltp TO tracer_app;
-- GRANT USAGE ON SCHEMA tracer_olap TO tracer_app;
-- GRANT USAGE ON SCHEMA tracer_olap TO tracer_readonly;

-- 4) Default privileges for future tables
-- ALTER DEFAULT PRIVILEGES IN SCHEMA tracer_oltp
-- GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tracer_app;

-- ALTER DEFAULT PRIVILEGES IN SCHEMA tracer_olap
-- GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tracer_app;

-- ALTER DEFAULT PRIVILEGES IN SCHEMA tracer_olap
-- GRANT SELECT ON TABLES TO tracer_readonly;

COMMIT;

-- Notes:
-- - Laravel connection `oltp` should use search_path=tracer_oltp
-- - Laravel connection `olap` should use search_path=tracer_olap
-- - Run Laravel migrations after this script.
