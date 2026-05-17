# PostgreSQL Connection Guide

This guide explains what must be prepared in PostgreSQL and how to connect this Laravel backend using one database with two schemas.

## 1. What to prepare in PostgreSQL

You need:
- PostgreSQL server running
- One database, recommended name: tracer_study
- Two schemas inside that database:
  - tracer_oltp
  - tracer_olap
- One database user with privileges to both schemas

### Minimal SQL to prepare

```sql
CREATE DATABASE tracer_study;

-- connect to tracer_study first, then run:
CREATE SCHEMA IF NOT EXISTS tracer_oltp;
CREATE SCHEMA IF NOT EXISTS tracer_olap;
```

If using app user:

```sql
CREATE ROLE tracer_app WITH LOGIN PASSWORD 'change_this_password';
GRANT ALL PRIVILEGES ON DATABASE tracer_study TO tracer_app;
GRANT USAGE, CREATE ON SCHEMA tracer_oltp TO tracer_app;
GRANT USAGE, CREATE ON SCHEMA tracer_olap TO tracer_app;
```

Optional default grants:

```sql
ALTER DEFAULT PRIVILEGES IN SCHEMA tracer_oltp
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tracer_app;

ALTER DEFAULT PRIVILEGES IN SCHEMA tracer_olap
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tracer_app;
```

## 2. Backend environment variables

In .env set these values:

```dotenv
DB_CONNECTION=oltp

OLTP_DB_HOST=127.0.0.1
OLTP_DB_PORT=5432
OLTP_DB_DATABASE=tracer_study
OLTP_DB_USERNAME=postgres
OLTP_DB_PASSWORD=postgres

OLAP_DB_HOST=127.0.0.1
OLAP_DB_PORT=5432
OLAP_DB_DATABASE=tracer_study
OLAP_DB_USERNAME=postgres
OLAP_DB_PASSWORD=postgres
```

Note:
- This project already maps `oltp` to search_path `tracer_oltp`
- This project already maps `olap` to search_path `tracer_olap`

## 3. Laravel setup and migration

Run from backend folder:

```bash
composer install
php artisan key:generate
php artisan migrate
```

## 4. Common issues

- Could not find driver: enable pdo_pgsql and pgsql extension in php.ini
- Connection refused: PostgreSQL service not running or wrong host/port
- Permission denied for schema: user lacks privileges on tracer_oltp or tracer_olap
- relation migrations does not exist: run migrate after DB and schema are ready

## 5. Alternative without psql CLI

If `psql` command is unavailable, use pgAdmin Query Tool to run SQL scripts, then continue with Laravel commands in terminal.
