-- ============================================================================
-- 005_etl_runs_and_queue.sql
--
-- Infrastruktur untuk auto-trigger ETL setelah Langkah 1 (question_semantic_
-- mapping) berubah, dengan status yang bisa di-poll FE (loading UI) alih-alih
-- diam-diam menunggu jadwal mingguan atau dijalankan manual lewat CLI.
--
-- 1. tracer_oltp.jobs / job_batches / failed_jobs -- tabel standar queue
--    driver "database" milik Laravel (QUEUE_CONNECTION berubah dari "sync"
--    ke "database" -- lihat .env). Skema PERSIS mengikuti migrasi bawaan
--    Laravel supaya kompatibel dengan queue worker tanpa modifikasi apa pun.
--    Perlu WORKER berjalan (`php artisan queue:work`) supaya job benar-benar
--    diproses -- lihat catatan di README bagian Setup.
--
-- 2. tracer_oltp.etl_runs -- satu baris per eksekusi ETL yang di-trigger
--    (baik manual `etl:run` di masa depan, maupun otomatis dari mapping
--    Langkah 1). FE poll endpoint GET /api/etl-runs/{id} untuk tahu kapan
--    selesai, alih-alih diam tanpa info seperti sebelumnya.
-- ============================================================================

BEGIN;

CREATE TABLE tracer_oltp.jobs (
    id           bigint       GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    queue        varchar(255) NOT NULL,
    payload      text         NOT NULL,
    attempts     smallint     NOT NULL,
    reserved_at  integer,
    available_at integer      NOT NULL,
    created_at   integer      NOT NULL
);
CREATE INDEX ix_jobs_queue ON tracer_oltp.jobs (queue);

CREATE TABLE tracer_oltp.job_batches (
    id                varchar(255) PRIMARY KEY,
    name              varchar(255) NOT NULL,
    total_jobs        integer      NOT NULL,
    pending_jobs      integer      NOT NULL,
    failed_jobs       integer      NOT NULL,
    failed_job_ids    text         NOT NULL,
    options           text,
    cancelled_at      integer,
    created_at        integer      NOT NULL,
    finished_at       integer
);

CREATE TABLE tracer_oltp.failed_jobs (
    id          bigint       GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    uuid        varchar(255) NOT NULL UNIQUE,
    connection  text         NOT NULL,
    queue       text         NOT NULL,
    payload     text         NOT NULL,
    exception   text         NOT NULL,
    failed_at   timestamp    NOT NULL DEFAULT now()
);

-- ─────────────────────────────────────────────────────────────────────────
-- tracer_oltp.etl_runs
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE tracer_oltp.etl_runs (
    id             bigint       GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    status         varchar(20)  NOT NULL DEFAULT 'queued'
                   CHECK (status IN ('queued','running','completed','failed')),
    reason         varchar(50)  NOT NULL,
    triggered_by   bigint       REFERENCES tracer_oltp.users(id),
    id_waktu       integer,
    summary        jsonb,
    error_message  text,
    started_at     timestamp,
    finished_at    timestamp,
    created_at     timestamp    NOT NULL DEFAULT now(),
    updated_at     timestamp    NOT NULL DEFAULT now()
);

COMMENT ON TABLE tracer_oltp.etl_runs IS
  'Satu baris per eksekusi ETL yang di-trigger lewat queue job (RunEtlJob) -- dipoll FE (GET /api/etl-runs/{id}) supaya ada UI loading yang jelas, bukan diam-diam menunggu tanpa status.';
COMMENT ON COLUMN tracer_oltp.etl_runs.reason IS
  'Pemicu run ini, mis. langkah1_mapping_store, langkah1_mapping_deactivate, manual_cli.';
COMMENT ON COLUMN tracer_oltp.etl_runs.id_waktu IS
  'FK longgar (tanpa constraint lintas-koneksi) ke public.dim_waktu.id_waktu -- diisi EtlOrchestratorService::run() setelah snapshot baru berhasil dibuat.';

CREATE INDEX ix_etl_runs_status ON tracer_oltp.etl_runs (status);

COMMIT;
