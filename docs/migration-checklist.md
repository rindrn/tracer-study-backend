# Migration Checklist

This checklist summarizes migrations prepared for the two-schema PostgreSQL setup.

## A. Existing baseline migrations

### A1. OLTP baseline (already in repository)
- `2026_03_22_000001_create_programs_table.php`
- `2026_03_22_000002_create_users_table.php`
- `2026_03_22_000003_create_personal_access_tokens_table.php`
- `2026_03_22_000004_create_thresholds_table.php`
- `2026_03_22_000005_create_threshold_programs_table.php`

### A2. OLTP views baseline
- `2026_03_23_000001_create_vw_thresholds_with_programs.php.php`
- `2026_03_23_000002_create_vw_users_with_programs.php`

## B. Newly prepared OLTP migrations

### B1. Core tracer entities
- `2026_04_20_000006_create_alumni_profiles_table.php`
- `2026_04_20_000007_create_questionnaires_table.php`
- `2026_04_20_000008_create_responses_table.php`
- `2026_04_20_000009_create_response_answers_table.php`

### B2. Questionnaire structure and assignments
- `2026_04_20_000010_create_questionnaire_sections_table.php`
- `2026_04_20_000011_create_questionnaire_questions_table.php`
- `2026_04_20_000012_create_questionnaire_options_table.php`
- `2026_04_20_000013_create_questionnaire_assignments_table.php`

### B3. Outcome and audit
- `2026_04_20_000014_create_employment_records_table.php`
- `2026_04_20_000015_create_education_records_table.php`
- `2026_04_20_000016_create_audit_logs_table.php`

## C. Newly prepared OLAP migrations

- `2026_04_20_100001_create_dim_time_table.php`
- `2026_04_20_100002_create_dim_program_table.php`
- `2026_04_20_100003_create_dim_alumni_table.php`
- `2026_04_20_100004_create_dim_employment_status_table.php`
- `2026_04_20_100005_create_fact_response_table.php`
- `2026_04_20_100006_create_fact_employment_outcome_table.php`

## D. Suggested execution order

1. Run PostgreSQL schema bootstrap script:
   - `docs/postgresql-two-schema-bootstrap.sql`
2. Run all migrations via Laravel:
   - `php artisan migrate`
3. Seed dimension defaults (recommended next):
   - Seed `dim_employment_status`
   - Seed calendar rows in `dim_time`

## E. Notes

- The current `response_answers` table uses flexible fields (`question_code`, `answer_option_code`) to allow gradual migration from legacy questionnaire payloads.
- When question bank API is stable, add a follow-up migration for strict FK columns to `questionnaire_questions` and `questionnaire_options`.
