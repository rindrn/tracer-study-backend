-- ============================================================================
-- 004_pg_trgm.sql
--
-- Constraint D helper (GET /api/question-semantic-mappings/similar) needs
-- Postgres's pg_trgm extension for similarity(text, text). Purely additive
-- and safe to run repeatedly (IF NOT EXISTS) -- same bootstrap pattern as
-- 002/003. Confirmed via `SELECT * FROM pg_extension WHERE extname='pg_trgm'`
-- that the live dev DB does NOT have it installed yet.
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS pg_trgm;
