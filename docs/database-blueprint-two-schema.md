# Database Blueprint: Single PostgreSQL with Two Schemas

This document is the initial data blueprint for Tracer Study backend using one PostgreSQL database and two schemas:

- `tracer_oltp`: transactional and operational data
- `tracer_olap`: analytics and dashboard data

The design follows current backend configuration in `config/database.php` where two Laravel connections already exist: `oltp` and `olap`.

## 1) Recommended Deployment Topology

- One PostgreSQL instance (for example: `tracer_study`)
- Two schemas in the same database:
  - `tracer_oltp`
  - `tracer_olap`
- Laravel API is the only component that accesses the database.
- Frontend consumes API only and has no direct database connection.

## 2) Data Domains

### 2.1 Operational domain (`tracer_oltp`)

Current tables in migration:
- `programs`
- `users`
- `personal_access_tokens`
- `thresholds`
- `threshold_programs`
- `vw_users_with_programs`
- `vw_thresholds_with_programs`

Additional recommended OLTP tables for tracer study:
- `cohorts`: graduation periods, intake years
- `alumni_profiles`: alumni master profile
- `questionnaires`: questionnaire definition per period/version
- `questionnaire_sections`
- `questionnaire_questions`
- `questionnaire_options`
- `questionnaire_assignments`: who should answer what
- `responses`: one submission header per alumni x questionnaire
- `response_answers`: one answer row per question
- `employment_records`: first job and current job summary
- `education_records`: further study records
- `audit_logs`: operational activity log

### 2.2 Analytics domain (`tracer_olap`)

Recommended star schema layer:
- Dimensions:
  - `dim_time`
  - `dim_program`
  - `dim_alumni`
  - `dim_employment_status`
  - `dim_salary_range`
  - `dim_waiting_time_range`
- Facts:
  - `fact_response`
  - `fact_employment_outcome`
  - `fact_education_outcome`
- Serving layer:
  - materialized views for dashboard metrics
  - read-only views for API chart endpoints

## 3) Minimum OLTP Table Shape (Draft)

### 3.1 `alumni_profiles`
Purpose: identity and academic profile for alumni.

Suggested columns:
- `id` bigint primary key
- `nim` varchar(30) unique not null
- `name` varchar(150) not null
- `email` varchar(150) nullable
- `phone` varchar(30) nullable
- `program_id` bigint not null references `programs(id)`
- `entry_year` smallint nullable
- `graduation_year` smallint nullable
- `gpa` numeric(3,2) nullable
- `consent_at` timestamp nullable
- `is_active` boolean default true
- `created_at`, `updated_at`

Indexes:
- unique (`nim`)
- index (`program_id`, `graduation_year`)

### 3.2 `questionnaires`
Purpose: questionnaire metadata per period and version.

Suggested columns:
- `id` bigint primary key
- `code` varchar(50) unique not null
- `title` varchar(200) not null
- `period_year` smallint not null
- `version` integer not null default 1
- `status` varchar(20) not null check in (`draft`, `published`, `archived`)
- `published_at` timestamp nullable
- `created_by` bigint nullable references `users(id)`
- `created_at`, `updated_at`

### 3.3 `responses`
Purpose: one submission envelope per respondent.

Suggested columns:
- `id` bigint primary key
- `questionnaire_id` bigint not null references `questionnaires(id)`
- `alumni_id` bigint not null references `alumni_profiles(id)`
- `status` varchar(20) not null check in (`started`, `submitted`, `verified`)
- `started_at` timestamp nullable
- `submitted_at` timestamp nullable
- `source` varchar(30) nullable
- `created_at`, `updated_at`

Indexes:
- unique (`questionnaire_id`, `alumni_id`)
- index (`status`, `submitted_at`)

### 3.4 `response_answers`
Purpose: normalized answer store.

Suggested columns:
- `id` bigint primary key
- `response_id` bigint not null references `responses(id)` on delete cascade
- `question_id` bigint not null references `questionnaire_questions(id)`
- `answer_text` text nullable
- `answer_number` numeric(14,2) nullable
- `answer_date` date nullable
- `answer_option_id` bigint nullable references `questionnaire_options(id)`
- `created_at`, `updated_at`

Indexes:
- index (`response_id`)
- index (`question_id`)
- index (`answer_option_id`)

## 4) Minimum OLAP Table Shape (Draft)

### 4.1 `dim_program`
- surrogate key `program_key`
- natural key `program_id`
- attributes: code, name, degree
- SCD strategy: type 1 at initial stage

### 4.2 `fact_employment_outcome`
Grain: one row per alumni per questionnaire period.

Suggested measures:
- `waiting_months` numeric(6,2)
- `salary_first` numeric(14,2)
- `salary_current` numeric(14,2)
- `is_job_relevant` boolean
- `is_entrepreneur` boolean

Foreign keys:
- `time_key`
- `program_key`
- `alumni_key`
- `employment_status_key`

### 4.3 `fact_response`
Grain: one row per response submission.

Suggested measures:
- `is_submitted` smallint
- `is_verified` smallint
- `response_duration_minutes` integer

## 5) ETL Flow (OLTP -> OLAP)

Recommended schedule:
- incremental load every 15-60 minutes for dashboard freshness
- daily reconciliation job for consistency

High-level steps:
1. Read new or updated rows in `responses`, `response_answers`, `alumni_profiles`, and outcome tables.
2. Upsert dimension tables (`dim_time`, `dim_program`, `dim_alumni`, etc).
3. Upsert fact tables (`fact_response`, `fact_employment_outcome`, `fact_education_outcome`).
4. Refresh materialized views for dashboard endpoints.

## 6) Security and Governance

- Keep PII in OLTP and expose only required fields to OLAP.
- Use row-level authorization at API layer by role (`admin`, `p2mpp`, `prodi`).
- Store audit trail for critical updates and exports.
- Apply retention policy for personal data and old tokens.

## 7) Migration and Rollout Plan

Phase 1 (now):
- stabilize current OLTP core tables and API auth flow
- add alumni and questionnaire normalized tables

Phase 2:
- add OLAP dimensions and facts
- create ETL command or queued jobs

Phase 3:
- optimize dashboard queries using materialized views and indexes

## 8) Immediate Next Implementation Tasks

1. Add migration for `alumni_profiles`.
2. Add migration for questionnaire normalized tables.
3. Add migration for `responses` and `response_answers`.
4. Add seed data for `programs` and role users in development.
5. Add ETL skeleton command: `php artisan etl:sync-oltp-to-olap`.
6. Add API endpoints for response rate and employment KPI.
