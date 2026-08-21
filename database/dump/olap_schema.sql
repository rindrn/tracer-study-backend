--
-- PostgreSQL database dump
--

-- Dumped from database version 15.8
-- Dumped by pg_dump version 16.4

-- Started on 2026-07-15 14:42:01

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 219 (class 1259 OID 99094)
-- Name: dim_alumni; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_alumni (
    id_alumni integer NOT NULL,
    nim character varying(20) NOT NULL,
    nama character varying(100),
    tahun_lulus character varying(5),
    label_sumber_biaya_dipolban character varying(100)
);


ALTER TABLE public.dim_alumni OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 99097)
-- Name: dim_alumni_id_alumni_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_alumni_id_alumni_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_alumni_id_alumni_seq OWNER TO postgres;

--
-- TOC entry 4154 (class 0 OID 0)
-- Dependencies: 220
-- Name: dim_alumni_id_alumni_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_alumni_id_alumni_seq OWNED BY public.dim_alumni.id_alumni;


--
-- TOC entry 221 (class 1259 OID 99098)
-- Name: dim_indikator_evaluasi; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_indikator_evaluasi (
    id_indikator_evaluasi integer NOT NULL,
    kode_field character varying(50) NOT NULL,
    label_pertanyaan character varying(255),
    kategori_pertanyaan character varying(50),
    jenis_skala character varying(50),
    grup_gap character varying(100)
);


ALTER TABLE public.dim_indikator_evaluasi OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 99103)
-- Name: dim_indikator_evaluasi_id_indikator_evaluasi_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_indikator_evaluasi_id_indikator_evaluasi_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_indikator_evaluasi_id_indikator_evaluasi_seq OWNER TO postgres;

--
-- TOC entry 4155 (class 0 OID 0)
-- Dependencies: 222
-- Name: dim_indikator_evaluasi_id_indikator_evaluasi_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_indikator_evaluasi_id_indikator_evaluasi_seq OWNED BY public.dim_indikator_evaluasi.id_indikator_evaluasi;


--
-- TOC entry 223 (class 1259 OID 99104)
-- Name: dim_kesesuaian_bidang; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_kesesuaian_bidang (
    kesesuaian_bidang_sk integer NOT NULL,
    id_kesesuaian_bidang character varying(50) NOT NULL,
    label character varying(100)
);


ALTER TABLE public.dim_kesesuaian_bidang OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 99107)
-- Name: dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq OWNER TO postgres;

--
-- TOC entry 4156 (class 0 OID 0)
-- Dependencies: 224
-- Name: dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq OWNED BY public.dim_kesesuaian_bidang.kesesuaian_bidang_sk;


--
-- TOC entry 225 (class 1259 OID 99108)
-- Name: dim_kesesuaian_level; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_kesesuaian_level (
    kesesuaian_level_sk integer NOT NULL,
    id_kesesuaian_level character varying(50) NOT NULL,
    label character varying(100)
);


ALTER TABLE public.dim_kesesuaian_level OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 99111)
-- Name: dim_kesesuaian_level_kesesuaian_level_sk_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_kesesuaian_level_kesesuaian_level_sk_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_kesesuaian_level_kesesuaian_level_sk_seq OWNER TO postgres;

--
-- TOC entry 4157 (class 0 OID 0)
-- Dependencies: 226
-- Name: dim_kesesuaian_level_kesesuaian_level_sk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_kesesuaian_level_kesesuaian_level_sk_seq OWNED BY public.dim_kesesuaian_level.kesesuaian_level_sk;


--
-- TOC entry 227 (class 1259 OID 99112)
-- Name: dim_perusahaan; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_perusahaan (
    perusahaan_sk integer NOT NULL,
    id_perusahaan character varying(255) NOT NULL,
    company_name character varying(200),
    label_jenis_perusahaan character varying(100),
    label_tingkat_instansi character varying(100),
    nama_kota character varying(100),
    nama_provinsi character varying(100),
    valid_from date,
    valid_to date,
    flag_perusahaan boolean DEFAULT true NOT NULL
);


ALTER TABLE public.dim_perusahaan OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 99118)
-- Name: dim_perusahaan_perusahaan_sk_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_perusahaan_perusahaan_sk_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_perusahaan_perusahaan_sk_seq OWNER TO postgres;

--
-- TOC entry 4158 (class 0 OID 0)
-- Dependencies: 228
-- Name: dim_perusahaan_perusahaan_sk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_perusahaan_perusahaan_sk_seq OWNED BY public.dim_perusahaan.perusahaan_sk;


--
-- TOC entry 229 (class 1259 OID 99119)
-- Name: dim_prodi; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_prodi (
    prodi_sk integer NOT NULL,
    id_prodi integer NOT NULL,
    kode_prodi character varying(10),
    nama_prodi character varying(100),
    jurusan character varying(100),
    jenjang character varying(5),
    nama_pt character varying(150),
    akreditasi_prodi character varying(10),
    valid_from date,
    valid_to date,
    flag_prodi boolean DEFAULT true NOT NULL
);


ALTER TABLE public.dim_prodi OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 99123)
-- Name: dim_prodi_prodi_sk_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_prodi_prodi_sk_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_prodi_prodi_sk_seq OWNER TO postgres;

--
-- TOC entry 4159 (class 0 OID 0)
-- Dependencies: 230
-- Name: dim_prodi_prodi_sk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_prodi_prodi_sk_seq OWNED BY public.dim_prodi.prodi_sk;


--
-- TOC entry 231 (class 1259 OID 99124)
-- Name: dim_status_alumni; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_status_alumni (
    status_alumni_sk integer NOT NULL,
    id_status_alumni character varying(50) NOT NULL,
    label character varying(100)
);


ALTER TABLE public.dim_status_alumni OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 99127)
-- Name: dim_status_alumni_status_alumni_sk_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_status_alumni_status_alumni_sk_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_status_alumni_status_alumni_sk_seq OWNER TO postgres;

--
-- TOC entry 4160 (class 0 OID 0)
-- Dependencies: 232
-- Name: dim_status_alumni_status_alumni_sk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_status_alumni_status_alumni_sk_seq OWNED BY public.dim_status_alumni.status_alumni_sk;


--
-- TOC entry 233 (class 1259 OID 99128)
-- Name: dim_studi_lanjut; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_studi_lanjut (
    id_studi_lanjut integer NOT NULL,
    perguruan_tinggi character varying(200),
    program_studi character varying(150),
    sumber_biaya character varying(100)
);


ALTER TABLE public.dim_studi_lanjut OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 99131)
-- Name: dim_studi_lanjut_id_studi_lanjut_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_studi_lanjut_id_studi_lanjut_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_studi_lanjut_id_studi_lanjut_seq OWNER TO postgres;

--
-- TOC entry 4161 (class 0 OID 0)
-- Dependencies: 234
-- Name: dim_studi_lanjut_id_studi_lanjut_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_studi_lanjut_id_studi_lanjut_seq OWNED BY public.dim_studi_lanjut.id_studi_lanjut;


--
-- TOC entry 235 (class 1259 OID 99132)
-- Name: dim_ump; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_ump (
    ump_sk bigint NOT NULL,
    id_ump bigint NOT NULL,
    tahun character varying(4) NOT NULL,
    nama_provinsi character varying(100) NOT NULL,
    nilai_ump numeric(15,2) NOT NULL
);


ALTER TABLE public.dim_ump OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 99135)
-- Name: dim_ump_ump_sk_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_ump_ump_sk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_ump_ump_sk_seq OWNER TO postgres;

--
-- TOC entry 4162 (class 0 OID 0)
-- Dependencies: 236
-- Name: dim_ump_ump_sk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_ump_ump_sk_seq OWNED BY public.dim_ump.ump_sk;


--
-- TOC entry 237 (class 1259 OID 99136)
-- Name: dim_waktu; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_waktu (
    id_waktu integer NOT NULL,
    minggu_snapshot character varying(10),
    bulan_snapshot character varying(15),
    tahun_snapshot character varying(5),
    tanggal_refresh date
);


ALTER TABLE public.dim_waktu OWNER TO postgres;

--
-- TOC entry 238 (class 1259 OID 99139)
-- Name: dim_waktu_id_waktu_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_waktu_id_waktu_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_waktu_id_waktu_seq OWNER TO postgres;

--
-- TOC entry 4163 (class 0 OID 0)
-- Dependencies: 238
-- Name: dim_waktu_id_waktu_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_waktu_id_waktu_seq OWNED BY public.dim_waktu.id_waktu;


--
-- TOC entry 239 (class 1259 OID 99140)
-- Name: dim_wirausaha; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dim_wirausaha (
    wirausaha_sk integer NOT NULL,
    id_wirausaha integer NOT NULL,
    jabatan character varying(150),
    label_tingkat_instansi character varying(100),
    nama_provinsi character varying(100),
    nama_kota character varying(100),
    valid_from date,
    valid_to date,
    flag_wirausaha boolean DEFAULT true NOT NULL
);


ALTER TABLE public.dim_wirausaha OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 99144)
-- Name: dim_wirausaha_wirausaha_sk_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dim_wirausaha_wirausaha_sk_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dim_wirausaha_wirausaha_sk_seq OWNER TO postgres;

--
-- TOC entry 4164 (class 0 OID 0)
-- Dependencies: 240
-- Name: dim_wirausaha_wirausaha_sk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dim_wirausaha_wirausaha_sk_seq OWNED BY public.dim_wirausaha.wirausaha_sk;


--
-- TOC entry 315 (class 1259 OID 106961)
-- Name: etl_anomaly_log; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.etl_anomaly_log (
    id bigint NOT NULL,
    etl_run_id character varying(50) NOT NULL,
    alumni_nim character varying(30),
    questionnaire_id bigint,
    question_code character varying(80),
    semantic_role character varying(50),
    raw_answer text,
    expected_kind character varying(20),
    reason character varying(50) NOT NULL,
    detail text,
    occurred_at timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.etl_anomaly_log OWNER TO postgres;

--
-- TOC entry 4165 (class 0 OID 0)
-- Dependencies: 315
-- Name: TABLE etl_anomaly_log; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.etl_anomaly_log IS 'Per-answer validation failures encountered during ETL. Never blocks the run -- the offending fact field is left NULL and logged here for admin review.';


--
-- TOC entry 4166 (class 0 OID 0)
-- Dependencies: 315
-- Name: COLUMN etl_anomaly_log.reason; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.etl_anomaly_log.reason IS 'Short machine-readable cause, e.g. type_mismatch, out_of_range, unmapped_code.';


--
-- TOC entry 314 (class 1259 OID 106960)
-- Name: etl_anomaly_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.etl_anomaly_log ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.etl_anomaly_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 241 (class 1259 OID 99145)
-- Name: fact_multi_select; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.fact_multi_select (
    id_multi_select integer NOT NULL,
    id_alumni integer NOT NULL,
    prodi_sk integer NOT NULL,
    id_waktu integer NOT NULL,
    id_indikator_evaluasi integer NOT NULL
);


ALTER TABLE public.fact_multi_select OWNER TO postgres;

--
-- TOC entry 242 (class 1259 OID 99148)
-- Name: fact_multi_select_id_multi_select_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.fact_multi_select_id_multi_select_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.fact_multi_select_id_multi_select_seq OWNER TO postgres;

--
-- TOC entry 4167 (class 0 OID 0)
-- Dependencies: 242
-- Name: fact_multi_select_id_multi_select_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.fact_multi_select_id_multi_select_seq OWNED BY public.fact_multi_select.id_multi_select;


--
-- TOC entry 243 (class 1259 OID 99149)
-- Name: fact_range_evaluasi; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.fact_range_evaluasi (
    id_range_evaluasi integer NOT NULL,
    prodi_sk integer NOT NULL,
    id_alumni integer NOT NULL,
    id_waktu integer NOT NULL,
    id_indikator_evaluasi integer NOT NULL,
    skor integer,
    CONSTRAINT fact_range_evaluasi_skor_check CHECK (((skor >= 1) AND (skor <= 5)))
);


ALTER TABLE public.fact_range_evaluasi OWNER TO postgres;

--
-- TOC entry 244 (class 1259 OID 99153)
-- Name: fact_range_evaluasi_id_range_evaluasi_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.fact_range_evaluasi_id_range_evaluasi_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.fact_range_evaluasi_id_range_evaluasi_seq OWNER TO postgres;

--
-- TOC entry 4168 (class 0 OID 0)
-- Dependencies: 244
-- Name: fact_range_evaluasi_id_range_evaluasi_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.fact_range_evaluasi_id_range_evaluasi_seq OWNED BY public.fact_range_evaluasi.id_range_evaluasi;


--
-- TOC entry 245 (class 1259 OID 99154)
-- Name: fact_tracer_study; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.fact_tracer_study (
    id_fact integer NOT NULL,
    id_alumni integer NOT NULL,
    id_waktu integer NOT NULL,
    status_alumni_sk integer NOT NULL,
    prodi_sk integer NOT NULL,
    kesesuaian_bidang_sk integer,
    kesesuaian_level_sk integer,
    id_studi_lanjut integer,
    perusahaan_sk integer,
    wirausaha_sk integer,
    ump_sk bigint,
    masa_tunggu_bekerja integer,
    bulan_sebelum_lulus integer,
    bulan_sesudah_lulus integer,
    masa_tunggu_wirausaha integer,
    take_home_pay integer,
    flag_above_ump integer
);


ALTER TABLE public.fact_tracer_study OWNER TO postgres;

--
-- TOC entry 246 (class 1259 OID 99157)
-- Name: fact_tracer_study_id_fact_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.fact_tracer_study_id_fact_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.fact_tracer_study_id_fact_seq OWNER TO postgres;

--
-- TOC entry 4169 (class 0 OID 0)
-- Dependencies: 246
-- Name: fact_tracer_study_id_fact_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.fact_tracer_study_id_fact_seq OWNED BY public.fact_tracer_study.id_fact;


--
-- TOC entry 313 (class 1259 OID 106947)
-- Name: kpi_category_mapping; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.kpi_category_mapping (
    id bigint NOT NULL,
    semantic_role character varying(50) NOT NULL,
    option_code character varying(80) NOT NULL,
    option_label_snapshot character varying(200),
    kpi_category character varying(30) NOT NULL,
    kpi_category_label character varying(150),
    digunakan_oleh character varying(50) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    effective_date date DEFAULT CURRENT_DATE NOT NULL,
    mapped_by bigint,
    deactivated_at timestamp without time zone,
    deactivated_by bigint,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.kpi_category_mapping OWNER TO postgres;

--
-- TOC entry 4170 (class 0 OID 0)
-- Dependencies: 313
-- Name: TABLE kpi_category_mapping; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.kpi_category_mapping IS 'Groups a semantic_role''s option_codes into KPI-facing categories (e.g. terserap/tidak). Lives in schema public so Cube.js cubes can subquery it directly. No cross-schema FK to semantic_role_registry by design -- see file header.';


--
-- TOC entry 4171 (class 0 OID 0)
-- Dependencies: 313
-- Name: COLUMN kpi_category_mapping.digunakan_oleh; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.kpi_category_mapping.digunakan_oleh IS 'Which downstream KPI/measure consumes this grouping (e.g. iku2_keterserapan, masa_tunggu_valid_status, kesesuaian_bidang_relevance) -- lets the same semantic_role+option_code be bucketed differently for different KPIs.';


--
-- TOC entry 312 (class 1259 OID 106946)
-- Name: kpi_category_mapping_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.kpi_category_mapping ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.kpi_category_mapping_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 247 (class 1259 OID 99158)
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- TOC entry 248 (class 1259 OID 99161)
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- TOC entry 4172 (class 0 OID 0)
-- Dependencies: 248
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- TOC entry 3521 (class 2604 OID 99343)
-- Name: dim_alumni id_alumni; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_alumni ALTER COLUMN id_alumni SET DEFAULT nextval('public.dim_alumni_id_alumni_seq'::regclass);


--
-- TOC entry 3522 (class 2604 OID 99344)
-- Name: dim_indikator_evaluasi id_indikator_evaluasi; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_indikator_evaluasi ALTER COLUMN id_indikator_evaluasi SET DEFAULT nextval('public.dim_indikator_evaluasi_id_indikator_evaluasi_seq'::regclass);


--
-- TOC entry 3523 (class 2604 OID 99345)
-- Name: dim_kesesuaian_bidang kesesuaian_bidang_sk; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_kesesuaian_bidang ALTER COLUMN kesesuaian_bidang_sk SET DEFAULT nextval('public.dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq'::regclass);


--
-- TOC entry 3524 (class 2604 OID 99346)
-- Name: dim_kesesuaian_level kesesuaian_level_sk; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_kesesuaian_level ALTER COLUMN kesesuaian_level_sk SET DEFAULT nextval('public.dim_kesesuaian_level_kesesuaian_level_sk_seq'::regclass);


--
-- TOC entry 3525 (class 2604 OID 99347)
-- Name: dim_perusahaan perusahaan_sk; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_perusahaan ALTER COLUMN perusahaan_sk SET DEFAULT nextval('public.dim_perusahaan_perusahaan_sk_seq'::regclass);


--
-- TOC entry 3527 (class 2604 OID 99348)
-- Name: dim_prodi prodi_sk; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_prodi ALTER COLUMN prodi_sk SET DEFAULT nextval('public.dim_prodi_prodi_sk_seq'::regclass);


--
-- TOC entry 3529 (class 2604 OID 99349)
-- Name: dim_status_alumni status_alumni_sk; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_status_alumni ALTER COLUMN status_alumni_sk SET DEFAULT nextval('public.dim_status_alumni_status_alumni_sk_seq'::regclass);


--
-- TOC entry 3530 (class 2604 OID 99350)
-- Name: dim_studi_lanjut id_studi_lanjut; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_studi_lanjut ALTER COLUMN id_studi_lanjut SET DEFAULT nextval('public.dim_studi_lanjut_id_studi_lanjut_seq'::regclass);


--
-- TOC entry 3531 (class 2604 OID 99351)
-- Name: dim_ump ump_sk; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_ump ALTER COLUMN ump_sk SET DEFAULT nextval('public.dim_ump_ump_sk_seq'::regclass);


--
-- TOC entry 3532 (class 2604 OID 99352)
-- Name: dim_waktu id_waktu; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_waktu ALTER COLUMN id_waktu SET DEFAULT nextval('public.dim_waktu_id_waktu_seq'::regclass);


--
-- TOC entry 3533 (class 2604 OID 99353)
-- Name: dim_wirausaha wirausaha_sk; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_wirausaha ALTER COLUMN wirausaha_sk SET DEFAULT nextval('public.dim_wirausaha_wirausaha_sk_seq'::regclass);


--
-- TOC entry 3535 (class 2604 OID 99354)
-- Name: fact_multi_select id_multi_select; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_multi_select ALTER COLUMN id_multi_select SET DEFAULT nextval('public.fact_multi_select_id_multi_select_seq'::regclass);


--
-- TOC entry 3536 (class 2604 OID 99355)
-- Name: fact_range_evaluasi id_range_evaluasi; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_range_evaluasi ALTER COLUMN id_range_evaluasi SET DEFAULT nextval('public.fact_range_evaluasi_id_range_evaluasi_seq'::regclass);


--
-- TOC entry 3537 (class 2604 OID 99356)
-- Name: fact_tracer_study id_fact; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study ALTER COLUMN id_fact SET DEFAULT nextval('public.fact_tracer_study_id_fact_seq'::regclass);


--
-- TOC entry 3538 (class 2604 OID 99357)
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- TOC entry 4138 (class 0 OID 106947)
-- Dependencies: 313
-- Data for Name: kpi_category_mapping; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.kpi_category_mapping (id, semantic_role, option_code, option_label_snapshot, kpi_category, kpi_category_label, digunakan_oleh, is_active, effective_date, mapped_by, deactivated_at, deactivated_by, created_at, updated_at) FROM stdin;
8	status_pekerjaan	1	Bekerja (full time / part time)	valid	Dihitung Masa Tunggu	masa_tunggu_valid_status	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
9	status_pekerjaan	3	Wiraswasta	valid	Dihitung Masa Tunggu	masa_tunggu_valid_status	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
10	status_pekerjaan	6	Melanjutkan pendidikan sambil bekerja	valid	Dihitung Masa Tunggu	masa_tunggu_valid_status	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
11	status_pekerjaan	7	Melanjutkan pendidikan sambil wiraswasta	valid	Dihitung Masa Tunggu	masa_tunggu_valid_status	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
12	status_pekerjaan	1	Bekerja (full time / part time)	valid	Dihitung Kesesuaian Bidang	kesesuaian_bidang_employed_status	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
13	status_pekerjaan	6	Melanjutkan pendidikan sambil bekerja	valid	Dihitung Kesesuaian Bidang	kesesuaian_bidang_employed_status	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
15	relevansi_bidang	2	Erat	sesuai	Sesuai Bidang	kesesuaian_bidang_relevance	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
16	relevansi_bidang	4	Kurang Erat	tidak_sesuai	Tidak Sesuai Bidang	kesesuaian_bidang_relevance	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
17	relevansi_bidang	5	Tidak Sama Sekali	tidak_sesuai	Tidak Sesuai Bidang	kesesuaian_bidang_relevance	t	2020-01-01	\N	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
1	status_pekerjaan	1	Bekerja (full time / part time)	terserap	Terserap	iku2_keterserapan	f	2020-01-01	\N	2026-07-12 22:31:52	\N	2026-07-12 23:23:38.609238	2026-07-12 22:31:52
2	status_pekerjaan	3	Wiraswasta	terserap	Terserap	iku2_keterserapan	f	2020-01-01	\N	2026-07-12 22:31:52	\N	2026-07-12 23:23:38.609238	2026-07-12 22:31:52
3	status_pekerjaan	4	Melanjutkan Pendidikan	terserap	Terserap	iku2_keterserapan	f	2020-01-01	\N	2026-07-12 22:31:52	\N	2026-07-12 23:23:38.609238	2026-07-12 22:31:52
4	status_pekerjaan	6	Melanjutkan pendidikan sambil bekerja	terserap	Terserap	iku2_keterserapan	f	2020-01-01	\N	2026-07-12 22:31:52	\N	2026-07-12 23:23:38.609238	2026-07-12 22:31:52
5	status_pekerjaan	7	Melanjutkan pendidikan sambil wiraswasta	terserap	Terserap	iku2_keterserapan	f	2020-01-01	\N	2026-07-12 22:31:52	\N	2026-07-12 23:23:38.609238	2026-07-12 22:31:52
6	status_pekerjaan	2	Belum memungkinkan bekerja	tidak	Tidak Terserap	iku2_keterserapan	f	2020-01-01	\N	2026-07-12 22:31:52	\N	2026-07-12 23:23:38.609238	2026-07-12 22:31:52
7	status_pekerjaan	5	Tidak kerja tetapi sedang mencari kerja	tidak	Tidak Terserap	iku2_keterserapan	f	2020-01-01	\N	2026-07-12 22:31:52	\N	2026-07-12 23:23:38.609238	2026-07-12 22:31:52
14	relevansi_bidang	1	Sangat Erat	sesuai	Sesuai Bidang	kesesuaian_bidang_relevance	f	2020-01-01	\N	2026-07-12 22:47:12	1	2026-07-12 23:23:38.609238	2026-07-12 22:47:12
19	status_pekerjaan	1	Bekerja (full time / part time)	terserap	Terserap	iku2_keterserapan	t	2026-07-13	1	\N	\N	2026-07-13 15:06:55	2026-07-13 15:06:55
20	status_pekerjaan	2	Belum memungkinkan bekerja	tidak	Tidak Terserap	iku2_keterserapan	t	2026-07-13	1	\N	\N	2026-07-13 15:06:58	2026-07-13 15:06:58
21	status_pekerjaan	3	Wiraswasta	terserap	Terserap	iku2_keterserapan	t	2026-07-13	1	\N	\N	2026-07-13 15:07:04	2026-07-13 15:07:04
22	status_pekerjaan	4	Melanjutkan Pendidikan	terserap	Terserap	iku2_keterserapan	t	2026-07-13	1	\N	\N	2026-07-13 15:07:07	2026-07-13 15:07:07
23	status_pekerjaan	5	Tidak kerja tetapi sedang mencari kerja	tidak	Tidak Terserap	iku2_keterserapan	t	2026-07-13	1	\N	\N	2026-07-13 15:07:18	2026-07-13 15:07:18
18	relevansi_bidang	1	Sangat Erat	tidak_sesuai	Tidak Sesuai Bidang	kesesuaian_bidang_relevance	f	2026-07-12	1	2026-07-13 19:26:39	1	2026-07-12 22:47:14	2026-07-13 19:26:39
26	relevansi_bidang	1	Sangat Erat	sesuai	Sesuai Bidang	kesesuaian_bidang_relevance	f	2026-07-13	1	2026-07-13 20:26:08	1	2026-07-13 19:26:43	2026-07-13 20:26:08
27	relevansi_bidang	1	Sangat Erat	tidak_sesuai	Tidak Sesuai Bidang	kesesuaian_bidang_relevance	t	2026-07-13	1	\N	\N	2026-07-13 20:26:12	2026-07-13 20:26:12
24	status_pekerjaan	6	Melanjutkan pendidikan sambil bekerja	terserap	Terserap	iku2_keterserapan	f	2026-07-13	1	2026-07-14 04:20:13	1	2026-07-13 15:07:26	2026-07-14 04:20:13
28	status_pekerjaan	6	Melanjutkan pendidikan sambil bekerja	tidak	Tidak Terserap	iku2_keterserapan	t	2026-07-14	1	\N	\N	2026-07-14 04:20:19	2026-07-14 04:20:19
25	status_pekerjaan	7	Melanjutkan pendidikan sambil wiraswasta	terserap	Terserap	iku2_keterserapan	f	2026-07-13	1	2026-07-14 04:20:27	1	2026-07-13 15:07:32	2026-07-14 04:20:27
29	status_pekerjaan	7	Melanjutkan pendidikan sambil wiraswasta	tidak	Tidak Terserap	iku2_keterserapan	t	2026-07-14	1	\N	\N	2026-07-14 04:20:30	2026-07-14 04:20:30
\.


--
-- TOC entry 4076 (class 0 OID 99158)
-- Dependencies: 247
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2026_06_19_000001_create_olap_schema	1
\.


--
-- TOC entry 4208 (class 0 OID 0)
-- Dependencies: 220
-- Name: dim_alumni_id_alumni_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_alumni_id_alumni_seq', 1, false);


--
-- TOC entry 4209 (class 0 OID 0)
-- Dependencies: 222
-- Name: dim_indikator_evaluasi_id_indikator_evaluasi_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_indikator_evaluasi_id_indikator_evaluasi_seq', 1, false);


--
-- TOC entry 4210 (class 0 OID 0)
-- Dependencies: 224
-- Name: dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_kesesuaian_bidang_kesesuaian_bidang_sk_seq', 1, false);


--
-- TOC entry 4211 (class 0 OID 0)
-- Dependencies: 226
-- Name: dim_kesesuaian_level_kesesuaian_level_sk_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_kesesuaian_level_kesesuaian_level_sk_seq', 1, false);


--
-- TOC entry 4212 (class 0 OID 0)
-- Dependencies: 228
-- Name: dim_perusahaan_perusahaan_sk_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_perusahaan_perusahaan_sk_seq', 1, false);


--
-- TOC entry 4213 (class 0 OID 0)
-- Dependencies: 230
-- Name: dim_prodi_prodi_sk_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_prodi_prodi_sk_seq', 1, false);


--
-- TOC entry 4214 (class 0 OID 0)
-- Dependencies: 232
-- Name: dim_status_alumni_status_alumni_sk_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_status_alumni_status_alumni_sk_seq', 1, false);


--
-- TOC entry 4215 (class 0 OID 0)
-- Dependencies: 234
-- Name: dim_studi_lanjut_id_studi_lanjut_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_studi_lanjut_id_studi_lanjut_seq', 1, false);


--
-- TOC entry 4216 (class 0 OID 0)
-- Dependencies: 236
-- Name: dim_ump_ump_sk_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_ump_ump_sk_seq', 1, false);


--
-- TOC entry 4217 (class 0 OID 0)
-- Dependencies: 238
-- Name: dim_waktu_id_waktu_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_waktu_id_waktu_seq', 1, false);


--
-- TOC entry 4218 (class 0 OID 0)
-- Dependencies: 240
-- Name: dim_wirausaha_wirausaha_sk_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dim_wirausaha_wirausaha_sk_seq', 1, false);


--
-- TOC entry 4219 (class 0 OID 0)
-- Dependencies: 314
-- Name: etl_anomaly_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.etl_anomaly_log_id_seq', 1, false);


--
-- TOC entry 4220 (class 0 OID 0)
-- Dependencies: 242
-- Name: fact_multi_select_id_multi_select_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.fact_multi_select_id_multi_select_seq', 1, false);


--
-- TOC entry 4221 (class 0 OID 0)
-- Dependencies: 244
-- Name: fact_range_evaluasi_id_range_evaluasi_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.fact_range_evaluasi_id_range_evaluasi_seq', 1, false);


--
-- TOC entry 4222 (class 0 OID 0)
-- Dependencies: 246
-- Name: fact_tracer_study_id_fact_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.fact_tracer_study_id_fact_seq', 1, false);


--
-- TOC entry 4223 (class 0 OID 0)
-- Dependencies: 312
-- Name: kpi_category_mapping_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.kpi_category_mapping_id_seq', 29, true);


--
-- TOC entry 4224 (class 0 OID 0)
-- Dependencies: 248
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 1, true);


--
-- TOC entry 3628 (class 2606 OID 99386)
-- Name: dim_alumni dim_alumni_nim_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_alumni
    ADD CONSTRAINT dim_alumni_nim_unique UNIQUE (nim);


--
-- TOC entry 3630 (class 2606 OID 99388)
-- Name: dim_alumni dim_alumni_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_alumni
    ADD CONSTRAINT dim_alumni_pkey PRIMARY KEY (id_alumni);


--
-- TOC entry 3632 (class 2606 OID 99390)
-- Name: dim_indikator_evaluasi dim_indikator_evaluasi_kode_field_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_indikator_evaluasi
    ADD CONSTRAINT dim_indikator_evaluasi_kode_field_unique UNIQUE (kode_field);


--
-- TOC entry 3634 (class 2606 OID 99392)
-- Name: dim_indikator_evaluasi dim_indikator_evaluasi_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_indikator_evaluasi
    ADD CONSTRAINT dim_indikator_evaluasi_pkey PRIMARY KEY (id_indikator_evaluasi);


--
-- TOC entry 3636 (class 2606 OID 99394)
-- Name: dim_kesesuaian_bidang dim_kesesuaian_bidang_id_kesesuaian_bidang_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_kesesuaian_bidang
    ADD CONSTRAINT dim_kesesuaian_bidang_id_kesesuaian_bidang_unique UNIQUE (id_kesesuaian_bidang);


--
-- TOC entry 3638 (class 2606 OID 99396)
-- Name: dim_kesesuaian_bidang dim_kesesuaian_bidang_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_kesesuaian_bidang
    ADD CONSTRAINT dim_kesesuaian_bidang_pkey PRIMARY KEY (kesesuaian_bidang_sk);


--
-- TOC entry 3640 (class 2606 OID 99398)
-- Name: dim_kesesuaian_level dim_kesesuaian_level_id_kesesuaian_level_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_kesesuaian_level
    ADD CONSTRAINT dim_kesesuaian_level_id_kesesuaian_level_unique UNIQUE (id_kesesuaian_level);


--
-- TOC entry 3642 (class 2606 OID 99400)
-- Name: dim_kesesuaian_level dim_kesesuaian_level_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_kesesuaian_level
    ADD CONSTRAINT dim_kesesuaian_level_pkey PRIMARY KEY (kesesuaian_level_sk);


--
-- TOC entry 3645 (class 2606 OID 99402)
-- Name: dim_perusahaan dim_perusahaan_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_perusahaan
    ADD CONSTRAINT dim_perusahaan_pkey PRIMARY KEY (perusahaan_sk);


--
-- TOC entry 3648 (class 2606 OID 99404)
-- Name: dim_prodi dim_prodi_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_prodi
    ADD CONSTRAINT dim_prodi_pkey PRIMARY KEY (prodi_sk);


--
-- TOC entry 3650 (class 2606 OID 99406)
-- Name: dim_status_alumni dim_status_alumni_id_status_alumni_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_status_alumni
    ADD CONSTRAINT dim_status_alumni_id_status_alumni_unique UNIQUE (id_status_alumni);


--
-- TOC entry 3652 (class 2606 OID 99408)
-- Name: dim_status_alumni dim_status_alumni_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_status_alumni
    ADD CONSTRAINT dim_status_alumni_pkey PRIMARY KEY (status_alumni_sk);


--
-- TOC entry 3654 (class 2606 OID 99410)
-- Name: dim_studi_lanjut dim_studi_lanjut_perguruan_tinggi_program_studi_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_studi_lanjut
    ADD CONSTRAINT dim_studi_lanjut_perguruan_tinggi_program_studi_unique UNIQUE (perguruan_tinggi, program_studi);


--
-- TOC entry 3656 (class 2606 OID 99412)
-- Name: dim_studi_lanjut dim_studi_lanjut_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_studi_lanjut
    ADD CONSTRAINT dim_studi_lanjut_pkey PRIMARY KEY (id_studi_lanjut);


--
-- TOC entry 3658 (class 2606 OID 99414)
-- Name: dim_ump dim_ump_id_ump_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_ump
    ADD CONSTRAINT dim_ump_id_ump_unique UNIQUE (id_ump);


--
-- TOC entry 3660 (class 2606 OID 99416)
-- Name: dim_ump dim_ump_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_ump
    ADD CONSTRAINT dim_ump_pkey PRIMARY KEY (ump_sk);


--
-- TOC entry 3664 (class 2606 OID 99418)
-- Name: dim_waktu dim_waktu_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_waktu
    ADD CONSTRAINT dim_waktu_pkey PRIMARY KEY (id_waktu);


--
-- TOC entry 3667 (class 2606 OID 99420)
-- Name: dim_wirausaha dim_wirausaha_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dim_wirausaha
    ADD CONSTRAINT dim_wirausaha_pkey PRIMARY KEY (wirausaha_sk);


--
-- TOC entry 3827 (class 2606 OID 106968)
-- Name: etl_anomaly_log etl_anomaly_log_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etl_anomaly_log
    ADD CONSTRAINT etl_anomaly_log_pkey PRIMARY KEY (id);


--
-- TOC entry 3671 (class 2606 OID 99422)
-- Name: fact_multi_select fact_multi_select_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_multi_select
    ADD CONSTRAINT fact_multi_select_pkey PRIMARY KEY (id_multi_select);


--
-- TOC entry 3675 (class 2606 OID 99424)
-- Name: fact_range_evaluasi fact_range_evaluasi_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_range_evaluasi
    ADD CONSTRAINT fact_range_evaluasi_pkey PRIMARY KEY (id_range_evaluasi);


--
-- TOC entry 3680 (class 2606 OID 99426)
-- Name: fact_tracer_study fact_tracer_study_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_pkey PRIMARY KEY (id_fact);


--
-- TOC entry 3824 (class 2606 OID 106957)
-- Name: kpi_category_mapping kpi_category_mapping_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.kpi_category_mapping
    ADD CONSTRAINT kpi_category_mapping_pkey PRIMARY KEY (id);


--
-- TOC entry 3683 (class 2606 OID 99428)
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- TOC entry 3643 (class 1259 OID 99551)
-- Name: dim_perusahaan_id_perusahaan_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dim_perusahaan_id_perusahaan_index ON public.dim_perusahaan USING btree (id_perusahaan);


--
-- TOC entry 3646 (class 1259 OID 99552)
-- Name: dim_prodi_id_prodi_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dim_prodi_id_prodi_index ON public.dim_prodi USING btree (id_prodi);


--
-- TOC entry 3661 (class 1259 OID 99553)
-- Name: dim_ump_tahun_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dim_ump_tahun_index ON public.dim_ump USING btree (tahun);


--
-- TOC entry 3662 (class 1259 OID 99554)
-- Name: dim_ump_tahun_nama_provinsi_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dim_ump_tahun_nama_provinsi_index ON public.dim_ump USING btree (tahun, nama_provinsi);


--
-- TOC entry 3665 (class 1259 OID 99555)
-- Name: dim_wirausaha_id_wirausaha_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dim_wirausaha_id_wirausaha_index ON public.dim_wirausaha USING btree (id_wirausaha);


--
-- TOC entry 3668 (class 1259 OID 99556)
-- Name: fact_multi_select_id_alumni_id_waktu_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_multi_select_id_alumni_id_waktu_index ON public.fact_multi_select USING btree (id_alumni, id_waktu);


--
-- TOC entry 3669 (class 1259 OID 99557)
-- Name: fact_multi_select_id_indikator_evaluasi_id_waktu_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_multi_select_id_indikator_evaluasi_id_waktu_index ON public.fact_multi_select USING btree (id_indikator_evaluasi, id_waktu);


--
-- TOC entry 3672 (class 1259 OID 99558)
-- Name: fact_range_evaluasi_id_alumni_id_waktu_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_range_evaluasi_id_alumni_id_waktu_index ON public.fact_range_evaluasi USING btree (id_alumni, id_waktu);


--
-- TOC entry 3673 (class 1259 OID 99559)
-- Name: fact_range_evaluasi_id_indikator_evaluasi_prodi_sk_id_waktu_ind; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_range_evaluasi_id_indikator_evaluasi_prodi_sk_id_waktu_ind ON public.fact_range_evaluasi USING btree (id_indikator_evaluasi, prodi_sk, id_waktu);


--
-- TOC entry 3676 (class 1259 OID 99560)
-- Name: fact_tracer_study_id_alumni_id_waktu_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_tracer_study_id_alumni_id_waktu_index ON public.fact_tracer_study USING btree (id_alumni, id_waktu);


--
-- TOC entry 3677 (class 1259 OID 99561)
-- Name: fact_tracer_study_id_alumni_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_tracer_study_id_alumni_index ON public.fact_tracer_study USING btree (id_alumni);


--
-- TOC entry 3678 (class 1259 OID 99562)
-- Name: fact_tracer_study_id_waktu_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_tracer_study_id_waktu_index ON public.fact_tracer_study USING btree (id_waktu);


--
-- TOC entry 3681 (class 1259 OID 99563)
-- Name: fact_tracer_study_prodi_sk_id_waktu_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX fact_tracer_study_prodi_sk_id_waktu_index ON public.fact_tracer_study USING btree (prodi_sk, id_waktu);


--
-- TOC entry 3828 (class 1259 OID 106971)
-- Name: ix_anomaly_nim; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX ix_anomaly_nim ON public.etl_anomaly_log USING btree (alumni_nim);


--
-- TOC entry 3829 (class 1259 OID 106970)
-- Name: ix_anomaly_role; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX ix_anomaly_role ON public.etl_anomaly_log USING btree (semantic_role);


--
-- TOC entry 3830 (class 1259 OID 106969)
-- Name: ix_anomaly_run; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX ix_anomaly_run ON public.etl_anomaly_log USING btree (etl_run_id);


--
-- TOC entry 3822 (class 1259 OID 106959)
-- Name: ix_kcm_lookup; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX ix_kcm_lookup ON public.kpi_category_mapping USING btree (semantic_role, digunakan_oleh) WHERE is_active;


--
-- TOC entry 3825 (class 1259 OID 106958)
-- Name: uq_kcm_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_kcm_active ON public.kpi_category_mapping USING btree (semantic_role, option_code, digunakan_oleh) WHERE is_active;


--
-- TOC entry 3843 (class 2606 OID 99584)
-- Name: fact_multi_select fact_multi_select_id_alumni_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_multi_select
    ADD CONSTRAINT fact_multi_select_id_alumni_foreign FOREIGN KEY (id_alumni) REFERENCES public.dim_alumni(id_alumni);


--
-- TOC entry 3844 (class 2606 OID 99589)
-- Name: fact_multi_select fact_multi_select_id_indikator_evaluasi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_multi_select
    ADD CONSTRAINT fact_multi_select_id_indikator_evaluasi_foreign FOREIGN KEY (id_indikator_evaluasi) REFERENCES public.dim_indikator_evaluasi(id_indikator_evaluasi);


--
-- TOC entry 3845 (class 2606 OID 99594)
-- Name: fact_multi_select fact_multi_select_id_waktu_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_multi_select
    ADD CONSTRAINT fact_multi_select_id_waktu_foreign FOREIGN KEY (id_waktu) REFERENCES public.dim_waktu(id_waktu);


--
-- TOC entry 3846 (class 2606 OID 99599)
-- Name: fact_multi_select fact_multi_select_prodi_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_multi_select
    ADD CONSTRAINT fact_multi_select_prodi_sk_foreign FOREIGN KEY (prodi_sk) REFERENCES public.dim_prodi(prodi_sk);


--
-- TOC entry 3847 (class 2606 OID 99604)
-- Name: fact_range_evaluasi fact_range_evaluasi_id_alumni_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_range_evaluasi
    ADD CONSTRAINT fact_range_evaluasi_id_alumni_foreign FOREIGN KEY (id_alumni) REFERENCES public.dim_alumni(id_alumni);


--
-- TOC entry 3848 (class 2606 OID 99609)
-- Name: fact_range_evaluasi fact_range_evaluasi_id_indikator_evaluasi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_range_evaluasi
    ADD CONSTRAINT fact_range_evaluasi_id_indikator_evaluasi_foreign FOREIGN KEY (id_indikator_evaluasi) REFERENCES public.dim_indikator_evaluasi(id_indikator_evaluasi);


--
-- TOC entry 3849 (class 2606 OID 99614)
-- Name: fact_range_evaluasi fact_range_evaluasi_id_waktu_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_range_evaluasi
    ADD CONSTRAINT fact_range_evaluasi_id_waktu_foreign FOREIGN KEY (id_waktu) REFERENCES public.dim_waktu(id_waktu);


--
-- TOC entry 3850 (class 2606 OID 99619)
-- Name: fact_range_evaluasi fact_range_evaluasi_prodi_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_range_evaluasi
    ADD CONSTRAINT fact_range_evaluasi_prodi_sk_foreign FOREIGN KEY (prodi_sk) REFERENCES public.dim_prodi(prodi_sk);


--
-- TOC entry 3851 (class 2606 OID 99624)
-- Name: fact_tracer_study fact_tracer_study_id_alumni_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_id_alumni_foreign FOREIGN KEY (id_alumni) REFERENCES public.dim_alumni(id_alumni);


--
-- TOC entry 3852 (class 2606 OID 99629)
-- Name: fact_tracer_study fact_tracer_study_id_studi_lanjut_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_id_studi_lanjut_foreign FOREIGN KEY (id_studi_lanjut) REFERENCES public.dim_studi_lanjut(id_studi_lanjut);


--
-- TOC entry 3853 (class 2606 OID 99634)
-- Name: fact_tracer_study fact_tracer_study_id_waktu_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_id_waktu_foreign FOREIGN KEY (id_waktu) REFERENCES public.dim_waktu(id_waktu);


--
-- TOC entry 3854 (class 2606 OID 99639)
-- Name: fact_tracer_study fact_tracer_study_kesesuaian_bidang_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_kesesuaian_bidang_sk_foreign FOREIGN KEY (kesesuaian_bidang_sk) REFERENCES public.dim_kesesuaian_bidang(kesesuaian_bidang_sk);


--
-- TOC entry 3855 (class 2606 OID 99644)
-- Name: fact_tracer_study fact_tracer_study_kesesuaian_level_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_kesesuaian_level_sk_foreign FOREIGN KEY (kesesuaian_level_sk) REFERENCES public.dim_kesesuaian_level(kesesuaian_level_sk);


--
-- TOC entry 3856 (class 2606 OID 99649)
-- Name: fact_tracer_study fact_tracer_study_perusahaan_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_perusahaan_sk_foreign FOREIGN KEY (perusahaan_sk) REFERENCES public.dim_perusahaan(perusahaan_sk);


--
-- TOC entry 3857 (class 2606 OID 99654)
-- Name: fact_tracer_study fact_tracer_study_prodi_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_prodi_sk_foreign FOREIGN KEY (prodi_sk) REFERENCES public.dim_prodi(prodi_sk);


--
-- TOC entry 3858 (class 2606 OID 99659)
-- Name: fact_tracer_study fact_tracer_study_status_alumni_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_status_alumni_sk_foreign FOREIGN KEY (status_alumni_sk) REFERENCES public.dim_status_alumni(status_alumni_sk);


--
-- TOC entry 3859 (class 2606 OID 99664)
-- Name: fact_tracer_study fact_tracer_study_ump_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_ump_sk_foreign FOREIGN KEY (ump_sk) REFERENCES public.dim_ump(ump_sk);


--
-- TOC entry 3860 (class 2606 OID 99669)
-- Name: fact_tracer_study fact_tracer_study_wirausaha_sk_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fact_tracer_study
    ADD CONSTRAINT fact_tracer_study_wirausaha_sk_foreign FOREIGN KEY (wirausaha_sk) REFERENCES public.dim_wirausaha(wirausaha_sk);


