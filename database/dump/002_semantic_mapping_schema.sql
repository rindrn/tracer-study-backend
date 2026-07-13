-- ============================================================================
-- 002_semantic_mapping_schema.sql
--
-- Dynamic question_code -> semantic_role -> KPI category mapping.
-- Purely additive: no existing table/column from init.sql is touched.
--
-- Placement rationale:
--   - semantic_role_registry, question_semantic_mapping  -> schema tracer_oltp
--     (Laravel 'oltp' connection). These describe OLTP question codes and
--     are only ever read by the ETL/admin API, never by Cube.js directly.
--   - kpi_category_mapping, etl_anomaly_log               -> schema public
--     (Laravel 'olap' connection). Cube.js's raw SQL cubes (model/cubes/*.js)
--     query schema `public` directly, so kpi_category_mapping MUST live here
--     for the new subquery-based measures to reach it in the same schema.
--     kpi_category_mapping.semantic_role is intentionally a plain varchar
--     with NO cross-schema FK to tracer_oltp.semantic_role_registry: Cube.js's
--     DB role only needs SELECT on `public`, and a cross-schema FK would leak
--     that boundary. Referential validity is instead enforced at the Laravel
--     application layer (see KpiCategoryMappingController), which can see
--     both connections.
--
-- Versioning: forward-only. Never DELETE a mapping row -- deactivate
-- (is_active = false, deactivated_at/deactivated_by) and INSERT a new one.
-- This mirrors the existing is_active pattern already used by
-- tracer_oltp.lam_versions / tracer_oltp.thresholds.
--
-- NOTE: mapping rows deliberately do NOT reference tracer_oltp.lam_versions.
-- That table tracks accreditation-standard versions (BAN-PT/LAM), a
-- different concept from "when this question<->role mapping took effect".
-- Conflating the two would force a mapping to point at an unrelated
-- accreditation version row. Use effective_date + is_active instead --
-- the same versioning primitive already used elsewhere in this schema.
-- ============================================================================

BEGIN;

-- ─────────────────────────────────────────────────────────────────────────
-- tracer_oltp.semantic_role_registry
-- Single source of truth for what a semantic_role IS: its data-type
-- contract (expected_kind/value_min/value_max), which OLAP column it
-- feeds (if any -- some roles resolve through a dim-service business key
-- instead of a direct fact column write), and which KPI "category" it
-- belongs to for UI grouping (prevents e.g. a waktu_tunggu question being
-- mapped to a keterserapan role by mistake -- the dropdown groups by this
-- field so the two are visually separated before type-checking even runs).
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE tracer_oltp.semantic_role_registry (
    role_key             varchar(50)  PRIMARY KEY,
    label                varchar(100) NOT NULL,
    category             varchar(50)  NOT NULL,
    description          text,
    expected_kind        varchar(20)  NOT NULL
                          CHECK (expected_kind IN ('integer','decimal','categorical','boolean','text','date')),
    value_min            numeric(14,2),
    value_max            numeric(14,2),
    sample_valid_answer  varchar(200),
    target_table         varchar(100),
    target_column        varchar(100),
    grain                varchar(20)  NOT NULL DEFAULT 'narrow'
                          CHECK (grain IN ('narrow','wide')),
    is_active            boolean      NOT NULL DEFAULT true,
    created_at            timestamp   NOT NULL DEFAULT now(),
    updated_at            timestamp   NOT NULL DEFAULT now()
);

COMMENT ON TABLE tracer_oltp.semantic_role_registry IS
  'Registry of valid semantic roles: data-type contract + target OLAP column + KPI-domain category for UI grouping.';
COMMENT ON COLUMN tracer_oltp.semantic_role_registry.category IS
  'KPI domain used to group roles in the admin mapping dropdown (e.g. keterserapan, waktu_tunggu, pendapatan) -- distinct roles in different categories should never share a question_code by mistake.';
COMMENT ON COLUMN tracer_oltp.semantic_role_registry.grain IS
  'narrow = feeds a single-valued fact_tracer_study column, one mapping per (questionnaire, role). wide = feeds fact_multi_select/fact_range_evaluasi, many question_codes legitimately share the role (uses dim_indikator_evaluasi for per-item identity instead of this table''s uniqueness).';

-- ─────────────────────────────────────────────────────────────────────────
-- tracer_oltp.question_semantic_mapping
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE tracer_oltp.question_semantic_mapping (
    id                     bigint       GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    questionnaire_id       bigint       NOT NULL REFERENCES tracer_oltp.questionnaires(id),
    question_code          varchar(80)  NOT NULL,
    question_text_snapshot text,
    semantic_role          varchar(50)  NOT NULL REFERENCES tracer_oltp.semantic_role_registry(role_key),
    grain                  varchar(20)  NOT NULL CHECK (grain IN ('narrow','wide')),
    effective_date         date         NOT NULL DEFAULT CURRENT_DATE,
    is_active              boolean      NOT NULL DEFAULT true,
    mapped_by              bigint       REFERENCES tracer_oltp.users(id),
    deactivated_at         timestamp,
    deactivated_by         bigint       REFERENCES tracer_oltp.users(id),
    created_at             timestamp    NOT NULL DEFAULT now(),
    updated_at             timestamp    NOT NULL DEFAULT now()
);

COMMENT ON TABLE tracer_oltp.question_semantic_mapping IS
  'Maps an OLTP question_code (scoped to one questionnaire) to a semantic_role. Forward-only versioned via is_active -- never delete.';
COMMENT ON COLUMN tracer_oltp.question_semantic_mapping.grain IS
  'Denormalized copy of semantic_role_registry.grain at write time. Needed because Postgres partial-index predicates must be immutable and cannot subquery another table -- this is what makes the narrow-role uniqueness index below possible.';

-- Constraint B: a question_code cannot be double-mapped within one questionnaire.
CREATE UNIQUE INDEX uq_qsm_active_code
  ON tracer_oltp.question_semantic_mapping (questionnaire_id, question_code)
  WHERE is_active;

-- Constraint A: a narrow-grain role can have at most one ACTIVE source
-- question_code per questionnaire (scoped PER questionnaire, not global --
-- the same role legitimately gets mapped by a different question_code
-- across different questionnaire years/instances, e.g. f8 vs f8_new).
-- Wide-grain rows are excluded entirely (Constraint C uses
-- dim_indikator_evaluasi for per-item identity instead).
CREATE UNIQUE INDEX uq_qsm_active_narrow_role
  ON tracer_oltp.question_semantic_mapping (questionnaire_id, semantic_role)
  WHERE is_active AND grain = 'narrow';

CREATE INDEX ix_qsm_role ON tracer_oltp.question_semantic_mapping (semantic_role) WHERE is_active;

-- ─────────────────────────────────────────────────────────────────────────
-- public.kpi_category_mapping
-- Replaces the hardcoded option_code lists in FactTracerStudy.js
-- (count_terserap, count_masa_tunggu_*, count_sesuai_bidang, ...) and in
-- KeterserapanService.php::STATUS_TERSERAP. `digunakan_oleh` disambiguates
-- multiple KPIs that read the SAME semantic_role with DIFFERENT bucketing
-- rules (e.g. status_pekerjaan feeds both "keterserapan" grouping and
-- "eligible for masa tunggu" grouping, with different option_code sets).
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE public.kpi_category_mapping (
    id                    bigint       GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    semantic_role         varchar(50)  NOT NULL,
    option_code           varchar(80)  NOT NULL,
    option_label_snapshot varchar(200),
    kpi_category          varchar(30)  NOT NULL,
    kpi_category_label    varchar(150),
    digunakan_oleh        varchar(50)  NOT NULL,
    is_active             boolean      NOT NULL DEFAULT true,
    effective_date        date         NOT NULL DEFAULT CURRENT_DATE,
    mapped_by             bigint,
    deactivated_at        timestamp,
    deactivated_by        bigint,
    created_at            timestamp    NOT NULL DEFAULT now(),
    updated_at            timestamp    NOT NULL DEFAULT now()
);

COMMENT ON TABLE public.kpi_category_mapping IS
  'Groups a semantic_role''s option_codes into KPI-facing categories (e.g. terserap/tidak). Lives in schema public so Cube.js cubes can subquery it directly. No cross-schema FK to semantic_role_registry by design -- see file header.';
COMMENT ON COLUMN public.kpi_category_mapping.digunakan_oleh IS
  'Which downstream KPI/measure consumes this grouping (e.g. iku2_keterserapan, masa_tunggu_valid_status, kesesuaian_bidang_relevance) -- lets the same semantic_role+option_code be bucketed differently for different KPIs.';

CREATE UNIQUE INDEX uq_kcm_active
  ON public.kpi_category_mapping (semantic_role, option_code, digunakan_oleh)
  WHERE is_active;

CREATE INDEX ix_kcm_lookup ON public.kpi_category_mapping (semantic_role, digunakan_oleh) WHERE is_active;

-- ─────────────────────────────────────────────────────────────────────────
-- public.etl_anomaly_log
-- Runtime validation failures: an answer didn't match its semantic_role's
-- expected_kind, or fell outside value_min/value_max. Soft-fail posture --
-- AlumniFactBuilderService stores NULL in the fact column and logs here
-- instead of crashing the run or silently coercing bad data.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE public.etl_anomaly_log (
    id              bigint      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    etl_run_id      varchar(50) NOT NULL,
    alumni_nim      varchar(30),
    questionnaire_id bigint,
    question_code   varchar(80),
    semantic_role   varchar(50),
    raw_answer      text,
    expected_kind   varchar(20),
    reason          varchar(50) NOT NULL,
    detail          text,
    occurred_at     timestamp   NOT NULL DEFAULT now()
);

COMMENT ON TABLE public.etl_anomaly_log IS
  'Per-answer validation failures encountered during ETL. Never blocks the run -- the offending fact field is left NULL and logged here for admin review.';
COMMENT ON COLUMN public.etl_anomaly_log.reason IS
  'Short machine-readable cause, e.g. type_mismatch, out_of_range, unmapped_code.';

CREATE INDEX ix_anomaly_run ON public.etl_anomaly_log (etl_run_id);
CREATE INDEX ix_anomaly_role ON public.etl_anomaly_log (semantic_role);
CREATE INDEX ix_anomaly_nim ON public.etl_anomaly_log (alumni_nim);

COMMIT;
