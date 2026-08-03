-- Pelengkap schema tracer_oltp: tabel yang tidak punya migration.
-- Diekstrak dari database/dump/init.sql.
SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET client_min_messages = warning;

--
-- TOC entry 331 (class 1259 OID 107990)
-- Name: etl_runs; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.etl_runs (
    id bigint NOT NULL,
    status character varying(20) DEFAULT 'queued'::character varying NOT NULL,
    reason character varying(50) NOT NULL,
    triggered_by bigint,
    id_waktu integer,
    summary jsonb,
    error_message text,
    started_at timestamp without time zone,
    finished_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT etl_runs_status_check CHECK (((status)::text = ANY ((ARRAY['queued'::character varying, 'running'::character varying, 'completed'::character varying, 'failed'::character varying])::text[])))
);


ALTER TABLE tracer_oltp.etl_runs OWNER TO postgres;

--
-- TOC entry 330 (class 1259 OID 107989)
-- Name: etl_runs_id_seq; Type: SEQUENCE; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE tracer_oltp.etl_runs ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME tracer_oltp.etl_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 329 (class 1259 OID 107979)
-- Name: failed_jobs; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE tracer_oltp.failed_jobs OWNER TO postgres;

--
-- TOC entry 328 (class 1259 OID 107978)
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE tracer_oltp.failed_jobs ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME tracer_oltp.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 327 (class 1259 OID 107971)
-- Name: job_batches; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE tracer_oltp.job_batches OWNER TO postgres;

--
-- TOC entry 326 (class 1259 OID 107963)
-- Name: jobs; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE tracer_oltp.jobs OWNER TO postgres;

--
-- TOC entry 325 (class 1259 OID 107962)
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE tracer_oltp.jobs ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME tracer_oltp.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 311 (class 1259 OID 106911)
-- Name: question_semantic_mapping; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.question_semantic_mapping (
    id bigint NOT NULL,
    questionnaire_id bigint NOT NULL,
    question_code character varying(80) NOT NULL,
    question_text_snapshot text,
    semantic_role character varying(50) NOT NULL,
    grain character varying(20) NOT NULL,
    effective_date date DEFAULT CURRENT_DATE NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    mapped_by bigint,
    deactivated_at timestamp without time zone,
    deactivated_by bigint,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT question_semantic_mapping_grain_check CHECK (((grain)::text = ANY ((ARRAY['narrow'::character varying, 'wide'::character varying])::text[])))
);


ALTER TABLE tracer_oltp.question_semantic_mapping OWNER TO postgres;

--
-- TOC entry 310 (class 1259 OID 106910)
-- Name: question_semantic_mapping_id_seq; Type: SEQUENCE; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE tracer_oltp.question_semantic_mapping ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME tracer_oltp.question_semantic_mapping_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 285 (class 1259 OID 99276)
-- Name: ref_ump; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.ref_ump (
    id integer NOT NULL,
    tahun integer NOT NULL,
    province_id integer NOT NULL,
    nilai_ump bigint NOT NULL,
    sumber character varying(20) DEFAULT 'MANUAL'::character varying,
    created_at timestamp without time zone DEFAULT now(),
    nama_provinsi character varying(255),
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE tracer_oltp.ref_ump OWNER TO postgres;

--
-- TOC entry 286 (class 1259 OID 99282)
-- Name: ref_ump_id_seq; Type: SEQUENCE; Schema: tracer_oltp; Owner: postgres
--

CREATE SEQUENCE tracer_oltp.ref_ump_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE tracer_oltp.ref_ump_id_seq OWNER TO postgres;

--
-- TOC entry 4197 (class 0 OID 0)
-- Dependencies: 286
-- Name: ref_ump_id_seq; Type: SEQUENCE OWNED BY; Schema: tracer_oltp; Owner: postgres
--

ALTER SEQUENCE tracer_oltp.ref_ump_id_seq OWNED BY tracer_oltp.ref_ump.id;


--
-- TOC entry 309 (class 1259 OID 106897)
-- Name: semantic_role_registry; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.semantic_role_registry (
    role_key character varying(50) NOT NULL,
    label character varying(100) NOT NULL,
    category character varying(50) NOT NULL,
    description text,
    expected_kind character varying(20) NOT NULL,
    value_min numeric(14,2),
    value_max numeric(14,2),
    sample_valid_answer character varying(200),
    target_table character varying(100),
    target_column character varying(100),
    grain character varying(20) DEFAULT 'narrow'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT semantic_role_registry_expected_kind_check CHECK (((expected_kind)::text = ANY ((ARRAY['integer'::character varying, 'decimal'::character varying, 'categorical'::character varying, 'boolean'::character varying, 'text'::character varying, 'date'::character varying])::text[]))),
    CONSTRAINT semantic_role_registry_grain_check CHECK (((grain)::text = ANY ((ARRAY['narrow'::character varying, 'wide'::character varying])::text[])))
);


ALTER TABLE tracer_oltp.semantic_role_registry OWNER TO postgres;

--
-- TOC entry 304 (class 1259 OID 106847)
-- Name: threshold_configs; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.threshold_configs (
    id bigint NOT NULL,
    lam_version_id bigint NOT NULL,
    indicator_id bigint NOT NULL,
    param_value numeric(12,2) NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE tracer_oltp.threshold_configs OWNER TO postgres;

--
-- TOC entry 303 (class 1259 OID 106846)
-- Name: threshold_configs_id_seq; Type: SEQUENCE; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE tracer_oltp.threshold_configs ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME tracer_oltp.threshold_configs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 306 (class 1259 OID 106872)
-- Name: tracer_response_thresholds; Type: TABLE; Schema: tracer_oltp; Owner: postgres
--

CREATE TABLE tracer_oltp.tracer_response_thresholds (
    id bigint NOT NULL,
    program_id bigint NOT NULL,
    graduated_year integer NOT NULL,
    total_lulusan integer NOT NULL,
    margin_error numeric(5,4) DEFAULT 0.023 NOT NULL,
    min_responden integer NOT NULL,
    threshold_value numeric(5,2) NOT NULL,
    calculated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE tracer_oltp.tracer_response_thresholds OWNER TO postgres;

--
-- TOC entry 305 (class 1259 OID 106871)
-- Name: tracer_response_thresholds_id_seq; Type: SEQUENCE; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE tracer_oltp.tracer_response_thresholds ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME tracer_oltp.tracer_response_thresholds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 3574 (class 2604 OID 99377)
-- Name: ref_ump id; Type: DEFAULT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.ref_ump ALTER COLUMN id SET DEFAULT nextval('tracer_oltp.ref_ump_id_seq'::regclass);


--
-- TOC entry 4147 (class 0 OID 107990)
-- Dependencies: 331
-- Data for Name: etl_runs; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.etl_runs (id, status, reason, triggered_by, id_waktu, summary, error_message, started_at, finished_at, created_at, updated_at) FROM stdin;
1	completed	manual_test	\N	3	{"stages": [{"stage": "dim_waktu", "skipped": 0, "inserted": 1, "processed": 1}, {"stage": "responses (extract)", "skipped": 0, "inserted": 0, "processed": 8367}, {"stage": "dim_prodi (SCD2)", "skipped": 0, "inserted": 0, "processed": 38}, {"stage": "dim_indikator_evaluasi (Type1)", "skipped": 102, "inserted": 0, "processed": 102}, {"stage": "dim_ump (Type1)", "skipped": 306, "inserted": 0, "processed": 306}, {"stage": "dim_alumni (SCD1)", "skipped": 8367, "inserted": 0, "processed": 8367}, {"stage": "dim_status_alumni (Type1)", "skipped": 21, "inserted": 0, "processed": 21}, {"stage": "dim_kesesuaian_level (Type1)", "skipped": 12, "inserted": 0, "processed": 12}, {"stage": "dim_kesesuaian_bidang (Type1)", "skipped": 15, "inserted": 0, "processed": 15}, {"stage": "fact_tracer_study", "skipped": 0, "inserted": 8367, "processed": 8367}, {"stage": "fact_multi_select", "skipped": 0, "inserted": 12612, "processed": 8367}, {"stage": "fact_range_evaluasi", "skipped": 0, "inserted": 120250, "processed": 8367}], "id_waktu": 3}	\N	2026-07-13 19:07:28	2026-07-13 19:15:46	2026-07-13 19:07:11	2026-07-13 19:15:46
\.


--
-- TOC entry 4145 (class 0 OID 107979)
-- Dependencies: 329
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- TOC entry 4143 (class 0 OID 107971)
-- Dependencies: 327
-- Data for Name: job_batches; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- TOC entry 4142 (class 0 OID 107963)
-- Dependencies: 326
-- Data for Name: jobs; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- TOC entry 4136 (class 0 OID 106911)
-- Dependencies: 311
-- Data for Name: question_semantic_mapping; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.question_semantic_mapping (id, questionnaire_id, question_code, question_text_snapshot, semantic_role, grain, effective_date, is_active, mapped_by, deactivated_at, deactivated_by, created_at, updated_at) FROM stdin;
118	3	f1764	Pada saat ini, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
1	1	f8	Jelaskan status Anda saat ini?	status_pekerjaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
2	1	f502	Dalam berapa bulan Anda mendapatkan pekerjaan pertama? / Dalam berapa bulan setelah lulus Anda memulai wiraswasta?	masa_tunggu_bekerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
3	1	f505	Berapa rata-rata pendapatan Anda per bulan? (take home pay)	pendapatan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
4	1	f5a1	Dimana lokasi tempat Anda bekerja? (Provinsi)	provinsi_kerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
5	1	f5a2	Dimana lokasi tempat Anda bekerja? (Kota/Kabupaten)	kota_kerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
6	1	f1101	Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?	jenis_perusahaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
7	1	f5b	Apa nama perusahaan/kantor tempat Anda bekerja?	nama_perusahaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
8	1	f5d	Apa tingkat tempat kerja Anda?	tingkat_instansi	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
9	1	f18a	Sumber biaya untuk studi lanjut?	sumber_biaya_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
10	1	f18b	Perguruan Tinggi tempat studi lanjut?	pt_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
11	1	f18c	Program Studi studi lanjut?	prodi_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
12	1	f1201	Sebutkan sumber dana dalam pembiayaan kuliah Anda? (bukan ketika Studi Lanjut)	sumber_biaya_studi	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
13	1	f14	Seberapa erat hubungan bidang studi dengan pekerjaan Anda?	relevansi_bidang	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
14	1	f15	Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?	kesesuaian_level	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
15	1	f1761	Pada saat lulus, pada tingkat mana kompetensi Etika Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
16	1	f1762	Pada saat ini, pada tingkat mana kompetensi Etika diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
17	1	f1763	Pada saat lulus, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
18	1	f1764	Pada saat ini, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
19	1	f1765	Pada saat lulus, pada tingkat mana kompetensi Bahasa Inggris Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
20	1	f1766	Pada saat ini, pada tingkat mana kompetensi Bahasa Inggris diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
21	1	f1767	Pada saat lulus, pada tingkat mana kompetensi Penggunaan Teknologi Informasi Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
22	1	f1768	Pada saat ini, pada tingkat mana kompetensi Penggunaan Teknologi Informasi diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
23	1	f1769	Pada saat lulus, pada tingkat mana kompetensi Komunikasi Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
24	1	f1770	Pada saat ini, pada tingkat mana kompetensi Komunikasi diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
25	1	f1771	Pada saat lulus, pada tingkat mana kompetensi Kerja sama tim Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
26	1	f1772	Pada saat ini, pada tingkat mana kompetensi Kerja sama tim diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
27	1	f1773	Pada saat lulus, pada tingkat mana kompetensi Pengembangan diri Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
28	1	f1774	Pada saat ini, pada tingkat mana kompetensi Pengembangan diri diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
29	1	f21	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Perkuliahan" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
30	1	f22	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Demonstrasi" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
31	1	f23	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Partisipasi dalam proyek riset" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
32	1	f24	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Magang" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
33	1	f25	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Praktikum" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
34	1	f26	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Kerja Lapangan" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
35	1	f27	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Diskusi" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
36	1	f302	Kira-kira berapa bulan sebelum lulus Anda mulai mencari pekerjaan?	bulan_sebelum_lulus	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
37	1	f1601	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
38	1	f1602	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya belum mendapatkan pekerjaan yang lebih sesuai	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
39	1	f1603	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Di pekerjaan ini saya memperoleh prospek karir yang baik	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
40	1	f1604	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
80	2	f22	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Demonstrasi" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
41	1	f1605	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
42	1	f1606	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
43	1	f1607	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih aman/terjamin/secure	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
44	1	f1608	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih menarik	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
45	1	f1609	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
46	1	f1610	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
47	1	f1611	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
48	1	f1612	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
49	1	f1613	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Lainnya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
50	2	f8	Jelaskan status Anda saat ini?	status_pekerjaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
51	2	f502	Dalam berapa bulan Anda mendapatkan pekerjaan pertama? / Dalam berapa bulan setelah lulus Anda memulai wiraswasta?	masa_tunggu_bekerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
52	2	f505	Berapa rata-rata pendapatan Anda per bulan? (take home pay)	pendapatan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
53	2	f5a1	Dimana lokasi tempat Anda bekerja? (Provinsi)	provinsi_kerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
54	2	f5a2	Dimana lokasi tempat Anda bekerja? (Kota/Kabupaten)	kota_kerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
55	2	f1101	Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?	jenis_perusahaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
56	2	f5b	Apa nama perusahaan/kantor tempat Anda bekerja?	nama_perusahaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
57	2	f5c	Bila berwiraswasta, apa posisi/jabatan Anda saat ini?	jabatan_wirausaha	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
58	2	f5d	Apa tingkat tempat kerja Anda?	tingkat_instansi	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
59	2	f18a	Sumber biaya untuk studi lanjut?	sumber_biaya_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
60	2	f18b	Perguruan Tinggi tempat studi lanjut?	pt_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
61	2	f18c	Program Studi studi lanjut?	prodi_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
62	2	f1201	Sebutkan sumber dana dalam pembiayaan kuliah Anda? (bukan ketika Studi Lanjut)	sumber_biaya_studi	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
63	2	f14	Seberapa erat hubungan bidang studi dengan pekerjaan Anda?	relevansi_bidang	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
64	2	f15	Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?	kesesuaian_level	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
65	2	f1761	Pada saat lulus, pada tingkat mana kompetensi Etika Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
66	2	f1762	Pada saat ini, pada tingkat mana kompetensi Etika diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
67	2	f1763	Pada saat lulus, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
68	2	f1764	Pada saat ini, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
69	2	f1765	Pada saat lulus, pada tingkat mana kompetensi Bahasa Inggris Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
70	2	f1766	Pada saat ini, pada tingkat mana kompetensi Bahasa Inggris diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
71	2	f1767	Pada saat lulus, pada tingkat mana kompetensi Penggunaan Teknologi Informasi Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
72	2	f1768	Pada saat ini, pada tingkat mana kompetensi Penggunaan Teknologi Informasi diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
73	2	f1769	Pada saat lulus, pada tingkat mana kompetensi Komunikasi Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
74	2	f1770	Pada saat ini, pada tingkat mana kompetensi Komunikasi diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
75	2	f1771	Pada saat lulus, pada tingkat mana kompetensi Kerja sama tim Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
76	2	f1772	Pada saat ini, pada tingkat mana kompetensi Kerja sama tim diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
77	2	f1773	Pada saat lulus, pada tingkat mana kompetensi Pengembangan diri Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
78	2	f1774	Pada saat ini, pada tingkat mana kompetensi Pengembangan diri diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
79	2	f21	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Perkuliahan" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
81	3	f1201	Sebutkan sumber dana dalam pembiayaan kuliah Anda? (bukan ketika Studi Lanjut)	sumber_biaya_studi	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
82	2	f23	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Partisipasi dalam proyek riset" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
83	2	f24	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Magang" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
84	2	f25	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Praktikum" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
85	2	f26	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Kerja Lapangan" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
86	2	f27	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Diskusi" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
87	2	f302	Kira-kira berapa bulan sebelum lulus Anda mulai mencari pekerjaan?	bulan_sebelum_lulus	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
88	2	f1601	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
89	2	f1602	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya belum mendapatkan pekerjaan yang lebih sesuai	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
90	2	f1603	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Di pekerjaan ini saya memperoleh prospek karir yang baik	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
91	2	f1604	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
92	2	f1605	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
93	2	f1606	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
94	2	f1607	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih aman/terjamin/secure	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
95	2	f1608	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih menarik	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
96	2	f1609	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
97	2	f1610	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
98	2	f1611	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
99	2	f1612	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
100	2	f1613	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Lainnya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
101	3	f8	Jelaskan status Anda saat ini?	status_pekerjaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
102	3	f502	Dalam berapa bulan Anda mendapatkan pekerjaan pertama? / Dalam berapa bulan setelah lulus Anda memulai wiraswasta?	masa_tunggu_bekerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
103	3	f505	Berapa rata-rata pendapatan Anda per bulan? (take home pay)	pendapatan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
104	3	f5a1	Dimana lokasi tempat Anda bekerja? (Provinsi)	provinsi_kerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
105	3	f5a2	Dimana lokasi tempat Anda bekerja? (Kota/Kabupaten)	kota_kerja	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
106	3	f1101	Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?	jenis_perusahaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
107	3	f5b	Apa nama perusahaan/kantor tempat Anda bekerja?	nama_perusahaan	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
108	3	f5c	Bila berwiraswasta, apa posisi/jabatan Anda saat ini?	jabatan_wirausaha	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
109	3	f5d	Apa tingkat tempat kerja Anda?	tingkat_instansi	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
110	3	f18a	Sumber biaya untuk studi lanjut?	sumber_biaya_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
111	3	f18b	Perguruan Tinggi tempat studi lanjut?	pt_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
112	3	f18c	Program Studi studi lanjut?	prodi_lanjut	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
113	3	f14	Seberapa erat hubungan bidang studi dengan pekerjaan Anda?	relevansi_bidang	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
114	3	f15	Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?	kesesuaian_level	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
115	3	f1761	Pada saat lulus, pada tingkat mana kompetensi Etika Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
116	3	f1762	Pada saat ini, pada tingkat mana kompetensi Etika diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
117	3	f1763	Pada saat lulus, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
119	3	f1765	Pada saat lulus, pada tingkat mana kompetensi Bahasa Inggris Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
120	3	f1766	Pada saat ini, pada tingkat mana kompetensi Bahasa Inggris diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
121	3	f1767	Pada saat lulus, pada tingkat mana kompetensi Penggunaan Teknologi Informasi Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
122	3	f1768	Pada saat ini, pada tingkat mana kompetensi Penggunaan Teknologi Informasi diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
123	3	f1769	Pada saat lulus, pada tingkat mana kompetensi Komunikasi Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
124	3	f1770	Pada saat ini, pada tingkat mana kompetensi Komunikasi diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
125	3	f1771	Pada saat lulus, pada tingkat mana kompetensi Kerja sama tim Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
126	3	f1772	Pada saat ini, pada tingkat mana kompetensi Kerja sama tim diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
127	3	f1773	Pada saat lulus, pada tingkat mana kompetensi Pengembangan diri Anda kuasai? (A)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
128	3	f1774	Pada saat ini, pada tingkat mana kompetensi Pengembangan diri diperlukan dalam pekerjaan? (B)	kompetensi_evaluasi	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
129	3	f21	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Perkuliahan" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
130	3	f22	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Demonstrasi" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
131	3	f23	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Partisipasi dalam proyek riset" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
132	3	f24	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Magang" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
133	3	f25	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Praktikum" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
134	3	f26	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Kerja Lapangan" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
135	3	f27	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Diskusi" di program studi Anda?	metode_pembelajaran	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
136	3	f302	Kira-kira berapa bulan sebelum lulus Anda mulai mencari pekerjaan?	bulan_sebelum_lulus	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
137	3	f1601	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
138	3	f1602	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya belum mendapatkan pekerjaan yang lebih sesuai	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
139	3	f1603	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Di pekerjaan ini saya memperoleh prospek karir yang baik	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
140	3	f1604	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
141	3	f1605	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
142	3	f1606	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
143	3	f1607	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih aman/terjamin/secure	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
144	3	f1608	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih menarik	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
145	3	f1609	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
146	3	f1610	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
147	3	f1611	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
148	3	f1612	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
149	3	f1613	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Lainnya	alasan_kerja_tidak_sesuai	wide	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
150	1	f5c	Bila berwiraswasta, apa posisi/jabatan Anda saat ini?	jabatan_wirausaha	narrow	2026-07-12	t	1	\N	\N	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
\.


--
-- TOC entry 4114 (class 0 OID 99276)
-- Dependencies: 285
-- Data for Name: ref_ump; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.ref_ump (id, tahun, province_id, nilai_ump, sumber, created_at, nama_provinsi, updated_at) FROM stdin;
131	2023	9	3385145	BPS_API	2026-06-11 11:49:07	Prov. Sulawesi Selatan	2026-06-11 12:26:17
132	2023	2	2758985	BPS_API	2026-06-11 11:49:07	Prov. Sulawesi Tenggara	2026-06-11 12:26:17
133	2023	7	2989350	BPS_API	2026-06-11 11:49:07	Prov. Gorontalo	2026-06-11 12:26:17
134	2023	5	2871795	BPS_API	2026-06-11 11:49:07	Prov. Sulawesi Barat	2026-06-11 12:26:17
135	2023	3	2812828	BPS_API	2026-06-11 11:49:07	Prov. Maluku	2026-06-11 12:26:17
136	2023	10	2976720	BPS_API	2026-06-11 11:49:07	Prov. Maluku Utara	2026-06-11 12:26:17
137	2023	4	3282000	BPS_API	2026-06-11 11:49:07	Prov. Papua Barat	2026-06-11 12:26:17
138	2023	13	3864696	BPS_API	2026-06-11 11:49:07	Prov. Papua	2026-06-11 12:26:17
393	2021	11	2460997	BPS_API	2026-06-11 12:27:03	Prov. Banten	2026-06-11 12:27:03
394	2021	26	2494000	BPS_API	2026-06-11 12:27:03	Prov. Bali	2026-06-11 12:27:03
395	2021	23	2183883	BPS_API	2026-06-11 12:27:03	Prov. Nusa Tenggara Barat	2026-06-11 12:27:03
396	2021	15	1950000	BPS_API	2026-06-11 12:27:03	Prov. Nusa Tenggara Timur	2026-06-11 12:27:03
397	2021	19	2399699	BPS_API	2026-06-11 12:27:03	Prov. Kalimantan Barat	2026-06-11 12:27:03
398	2021	24	2903145	BPS_API	2026-06-11 12:27:03	Prov. Kalimantan Tengah	2026-06-11 12:27:03
399	2021	25	2877449	BPS_API	2026-06-11 12:27:03	Prov. Kalimantan Selatan	2026-06-11 12:27:03
400	2021	22	2981379	BPS_API	2026-06-11 12:27:03	Prov. Kalimantan Timur	2026-06-11 12:27:03
401	2021	20	3000804	BPS_API	2026-06-11 12:27:03	Prov. Kalimantan Utara	2026-06-11 12:27:03
402	2021	12	3310723	BPS_API	2026-06-11 12:27:03	Prov. Sulawesi Utara	2026-06-11 12:27:03
403	2021	8	2303711	BPS_API	2026-06-11 12:27:03	Prov. Sulawesi Tengah	2026-06-11 12:27:03
404	2021	9	3165876	BPS_API	2026-06-11 12:27:03	Prov. Sulawesi Selatan	2026-06-11 12:27:03
1	2025	29	3685616	BPS_API	2026-06-11 10:14:52	Prov. Aceh	2026-06-11 12:23:43
2	2025	35	2992559	BPS_API	2026-06-11 10:14:52	Prov. Sumatera Utara	2026-06-11 12:23:43
3	2025	34	2994193	BPS_API	2026-06-11 10:14:52	Prov. Sumatera Barat	2026-06-11 12:23:43
4	2025	31	3508776	BPS_API	2026-06-11 10:14:52	Prov. Riau	2026-06-11 12:23:43
5	2025	1	3234535	BPS_API	2026-06-11 10:14:52	Prov. Jambi	2026-06-11 12:23:43
6	2025	16	3681571	BPS_API	2026-06-11 10:14:52	Prov. Sumatera Selatan	2026-06-11 12:23:43
7	2025	14	2670039	BPS_API	2026-06-11 10:14:52	Prov. Bengkulu	2026-06-11 12:23:43
8	2025	18	2893070	BPS_API	2026-06-11 10:14:52	Prov. Lampung	2026-06-11 12:23:43
9	2025	17	3876600	BPS_API	2026-06-11 10:14:52	Prov. Kepulauan Bangka Belitung	2026-06-11 12:23:43
10	2025	21	3623654	BPS_API	2026-06-11 10:14:52	Prov. Kepulauan Riau	2026-06-11 12:23:43
11	2025	27	5396761	BPS_API	2026-06-11 10:14:52	Prov. D.K.I. Jakarta	2026-06-11 12:23:43
12	2025	30	2191232	BPS_API	2026-06-11 10:14:52	Prov. Jawa Barat	2026-06-11 12:23:43
13	2025	32	2169349	BPS_API	2026-06-11 10:14:52	Prov. Jawa Tengah	2026-06-11 12:23:43
14	2025	28	2264081	BPS_API	2026-06-11 10:14:52	Prov. D.I. Yogyakarta	2026-06-11 12:23:43
16	2025	11	2905120	BPS_API	2026-06-11 10:14:52	Prov. Banten	2026-06-11 12:23:43
405	2021	2	2552015	BPS_API	2026-06-11 12:27:03	Prov. Sulawesi Tenggara	2026-06-11 12:27:03
406	2021	7	2788826	BPS_API	2026-06-11 12:27:03	Prov. Gorontalo	2026-06-11 12:27:03
407	2021	5	2678863	BPS_API	2026-06-11 12:27:03	Prov. Sulawesi Barat	2026-06-11 12:27:03
408	2021	3	2604961	BPS_API	2026-06-11 12:27:03	Prov. Maluku	2026-06-11 12:27:03
409	2021	10	2721530	BPS_API	2026-06-11 12:27:03	Prov. Maluku Utara	2026-06-11 12:27:03
410	2021	4	3134600	BPS_API	2026-06-11 12:27:03	Prov. Papua Barat	2026-06-11 12:27:03
411	2021	13	3516700	BPS_API	2026-06-11 12:27:03	Prov. Papua	2026-06-11 12:27:03
412	2026	29	3932552	BPS_API	2026-06-11 12:29:23	Prov. Aceh	2026-06-11 12:29:23
413	2026	35	3228949	BPS_API	2026-06-11 12:29:23	Prov. Sumatera Utara	2026-06-11 12:29:23
414	2026	34	3182955	BPS_API	2026-06-11 12:29:23	Prov. Sumatera Barat	2026-06-11 12:29:23
415	2026	31	3780495	BPS_API	2026-06-11 12:29:23	Prov. Riau	2026-06-11 12:29:23
416	2026	1	3471497	BPS_API	2026-06-11 12:29:23	Prov. Jambi	2026-06-11 12:29:23
417	2026	16	3942963	BPS_API	2026-06-11 12:29:23	Prov. Sumatera Selatan	2026-06-11 12:29:23
418	2026	14	2827250	BPS_API	2026-06-11 12:29:23	Prov. Bengkulu	2026-06-11 12:29:23
419	2026	18	3047734	BPS_API	2026-06-11 12:29:23	Prov. Lampung	2026-06-11 12:29:23
420	2026	17	4035000	BPS_API	2026-06-11 12:29:23	Prov. Kepulauan Bangka Belitung	2026-06-11 12:29:23
421	2026	21	3879520	BPS_API	2026-06-11 12:29:23	Prov. Kepulauan Riau	2026-06-11 12:29:23
422	2026	27	5729876	BPS_API	2026-06-11 12:29:23	Prov. D.K.I. Jakarta	2026-06-11 12:29:23
423	2026	30	2317601	BPS_API	2026-06-11 12:29:23	Prov. Jawa Barat	2026-06-11 12:29:23
424	2026	32	2327386	BPS_API	2026-06-11 12:29:23	Prov. Jawa Tengah	2026-06-11 12:29:23
425	2026	28	2417495	BPS_API	2026-06-11 12:29:23	Prov. D.I. Yogyakarta	2026-06-11 12:29:23
426	2026	33	2446880	BPS_API	2026-06-11 12:29:23	Prov. Jawa Timur	2026-06-11 12:29:23
427	2026	11	3100881	BPS_API	2026-06-11 12:29:23	Prov. Banten	2026-06-11 12:29:23
428	2026	26	3207459	BPS_API	2026-06-11 12:29:23	Prov. Bali	2026-06-11 12:29:23
429	2026	23	2673861	BPS_API	2026-06-11 12:29:23	Prov. Nusa Tenggara Barat	2026-06-11 12:29:23
430	2026	15	2455898	BPS_API	2026-06-11 12:29:23	Prov. Nusa Tenggara Timur	2026-06-11 12:29:23
431	2026	19	3054552	BPS_API	2026-06-11 12:29:23	Prov. Kalimantan Barat	2026-06-11 12:29:23
432	2026	24	3686138	BPS_API	2026-06-11 12:29:23	Prov. Kalimantan Tengah	2026-06-11 12:29:23
433	2026	25	3725000	BPS_API	2026-06-11 12:29:23	Prov. Kalimantan Selatan	2026-06-11 12:29:23
434	2026	22	3762431	BPS_API	2026-06-11 12:29:23	Prov. Kalimantan Timur	2026-06-11 12:29:23
435	2026	20	3775243	BPS_API	2026-06-11 12:29:23	Prov. Kalimantan Utara	2026-06-11 12:29:23
436	2026	12	4002630	BPS_API	2026-06-11 12:29:23	Prov. Sulawesi Utara	2026-06-11 12:29:23
437	2026	8	3179565	BPS_API	2026-06-11 12:29:23	Prov. Sulawesi Tengah	2026-06-11 12:29:23
438	2026	9	3921088	BPS_API	2026-06-11 12:29:23	Prov. Sulawesi Selatan	2026-06-11 12:29:23
439	2026	2	3306496	BPS_API	2026-06-11 12:29:23	Prov. Sulawesi Tenggara	2026-06-11 12:29:23
440	2026	7	3405144	BPS_API	2026-06-11 12:29:23	Prov. Gorontalo	2026-06-11 12:29:23
441	2026	5	3315934	BPS_API	2026-06-11 12:29:23	Prov. Sulawesi Barat	2026-06-11 12:29:23
442	2026	3	3334490	BPS_API	2026-06-11 12:29:23	Prov. Maluku	2026-06-11 12:29:23
443	2026	10	3510240	BPS_API	2026-06-11 12:29:23	Prov. Maluku Utara	2026-06-11 12:29:23
444	2026	4	3841000	BPS_API	2026-06-11 12:29:23	Prov. Papua Barat	2026-06-11 12:29:23
445	2026	13	4436283	BPS_API	2026-06-11 12:29:23	Prov. Papua	2026-06-11 12:29:23
446	2019	29	2916810	IMPORT	2026-06-11 12:32:31	Prov. Aceh	2026-06-11 12:32:31
447	2019	26	2297969	IMPORT	2026-06-11 12:32:31	Prov. Bali	2026-06-11 12:32:31
448	2019	11	2267990	IMPORT	2026-06-11 12:32:31	Prov. Banten	2026-06-11 12:32:31
449	2019	14	2040407	IMPORT	2026-06-11 12:32:31	Prov. Bengkulu	2026-06-11 12:32:31
450	2019	28	1570923	IMPORT	2026-06-11 12:32:31	Prov. D.I. Yogyakarta	2026-06-11 12:32:31
451	2019	27	3940973	IMPORT	2026-06-11 12:32:31	Prov. D.K.I. Jakarta	2026-06-11 12:32:31
139	2024	29	3460672	IMPORT	2026-06-11 11:51:41	Prov. Aceh	2026-06-11 12:25:01
105	2023	29	3413666	BPS_API	2026-06-11 11:49:07	Prov. Aceh	2026-06-11 12:26:17
106	2023	35	2710494	BPS_API	2026-06-11 11:49:07	Prov. Sumatera Utara	2026-06-11 12:26:17
107	2023	34	2742476	BPS_API	2026-06-11 11:49:07	Prov. Sumatera Barat	2026-06-11 12:26:17
108	2023	31	3191663	BPS_API	2026-06-11 11:49:07	Prov. Riau	2026-06-11 12:26:17
109	2023	1	2943033	BPS_API	2026-06-11 11:49:07	Prov. Jambi	2026-06-11 12:26:17
110	2023	16	3404177	BPS_API	2026-06-11 11:49:07	Prov. Sumatera Selatan	2026-06-11 12:26:17
111	2023	14	2418280	BPS_API	2026-06-11 11:49:07	Prov. Bengkulu	2026-06-11 12:26:17
112	2023	18	2633285	BPS_API	2026-06-11 11:49:07	Prov. Lampung	2026-06-11 12:26:17
113	2023	17	3498479	BPS_API	2026-06-11 11:49:07	Prov. Kepulauan Bangka Belitung	2026-06-11 12:26:17
114	2023	21	3279194	BPS_API	2026-06-11 11:49:07	Prov. Kepulauan Riau	2026-06-11 12:26:17
115	2023	27	4901798	BPS_API	2026-06-11 11:49:07	Prov. D.K.I. Jakarta	2026-06-11 12:26:17
116	2023	30	1986670	BPS_API	2026-06-11 11:49:07	Prov. Jawa Barat	2026-06-11 12:26:17
117	2023	32	1958170	BPS_API	2026-06-11 11:49:07	Prov. Jawa Tengah	2026-06-11 12:26:17
118	2023	28	1981782	BPS_API	2026-06-11 11:49:07	Prov. D.I. Yogyakarta	2026-06-11 12:26:17
119	2023	33	2040244	BPS_API	2026-06-11 11:49:07	Prov. Jawa Timur	2026-06-11 12:26:17
120	2023	11	2661280	BPS_API	2026-06-11 11:49:07	Prov. Banten	2026-06-11 12:26:17
121	2023	26	2713672	BPS_API	2026-06-11 11:49:07	Prov. Bali	2026-06-11 12:26:17
122	2023	23	2371407	BPS_API	2026-06-11 11:49:07	Prov. Nusa Tenggara Barat	2026-06-11 12:26:17
123	2023	15	2123994	BPS_API	2026-06-11 11:49:07	Prov. Nusa Tenggara Timur	2026-06-11 12:26:17
124	2023	19	2608602	BPS_API	2026-06-11 11:49:07	Prov. Kalimantan Barat	2026-06-11 12:26:17
125	2023	24	3181013	BPS_API	2026-06-11 11:49:07	Prov. Kalimantan Tengah	2026-06-11 12:26:17
126	2023	25	3149978	BPS_API	2026-06-11 11:49:07	Prov. Kalimantan Selatan	2026-06-11 12:26:17
127	2023	22	3201396	BPS_API	2026-06-11 11:49:07	Prov. Kalimantan Timur	2026-06-11 12:26:17
128	2023	20	3251703	BPS_API	2026-06-11 11:49:07	Prov. Kalimantan Utara	2026-06-11 12:26:17
129	2023	12	3485000	BPS_API	2026-06-11 11:49:07	Prov. Sulawesi Utara	2026-06-11 12:26:17
130	2023	8	2599546	BPS_API	2026-06-11 11:49:07	Prov. Sulawesi Tengah	2026-06-11 12:26:17
344	2022	29	3166460	BPS_API	2026-06-11 12:26:43	Prov. Aceh	2026-06-11 12:26:43
345	2022	35	2522610	BPS_API	2026-06-11 12:26:43	Prov. Sumatera Utara	2026-06-11 12:26:43
15	2025	33	2305985	BPS_API	2026-06-11 10:14:52	Prov. Jawa Timur	2026-06-11 12:23:43
17	2025	26	2996561	BPS_API	2026-06-11 10:14:52	Prov. Bali	2026-06-11 12:23:43
18	2025	23	2602931	BPS_API	2026-06-11 10:14:52	Prov. Nusa Tenggara Barat	2026-06-11 12:23:43
19	2025	15	2328970	BPS_API	2026-06-11 10:14:52	Prov. Nusa Tenggara Timur	2026-06-11 12:23:43
20	2025	19	2878286	BPS_API	2026-06-11 10:14:52	Prov. Kalimantan Barat	2026-06-11 12:23:43
21	2025	24	3473621	BPS_API	2026-06-11 10:14:52	Prov. Kalimantan Tengah	2026-06-11 12:23:43
22	2025	25	3496195	BPS_API	2026-06-11 10:14:52	Prov. Kalimantan Selatan	2026-06-11 12:23:43
23	2025	22	3579314	BPS_API	2026-06-11 10:14:52	Prov. Kalimantan Timur	2026-06-11 12:23:43
24	2025	20	3580160	BPS_API	2026-06-11 10:14:52	Prov. Kalimantan Utara	2026-06-11 12:23:43
25	2025	12	3775425	BPS_API	2026-06-11 10:14:52	Prov. Sulawesi Utara	2026-06-11 12:23:43
26	2025	8	2915000	BPS_API	2026-06-11 10:14:52	Prov. Sulawesi Tengah	2026-06-11 12:23:43
27	2025	9	3657527	BPS_API	2026-06-11 10:14:52	Prov. Sulawesi Selatan	2026-06-11 12:23:43
28	2025	2	3073552	BPS_API	2026-06-11 10:14:52	Prov. Sulawesi Tenggara	2026-06-11 12:23:43
29	2025	7	3221731	BPS_API	2026-06-11 10:14:52	Prov. Gorontalo	2026-06-11 12:23:43
30	2025	5	3104430	BPS_API	2026-06-11 10:14:52	Prov. Sulawesi Barat	2026-06-11 12:23:43
31	2025	3	3141700	BPS_API	2026-06-11 10:14:52	Prov. Maluku	2026-06-11 12:23:43
32	2025	10	3408000	BPS_API	2026-06-11 10:14:52	Prov. Maluku Utara	2026-06-11 12:23:43
33	2025	4	3615000	BPS_API	2026-06-11 10:14:52	Prov. Papua Barat	2026-06-11 12:23:43
34	2025	13	4285850	BPS_API	2026-06-11 10:14:52	Prov. Papua	2026-06-11 12:23:43
346	2022	34	2512539	BPS_API	2026-06-11 12:26:43	Prov. Sumatera Barat	2026-06-11 12:26:43
347	2022	31	2938564	BPS_API	2026-06-11 12:26:43	Prov. Riau	2026-06-11 12:26:43
348	2022	1	2698941	BPS_API	2026-06-11 12:26:43	Prov. Jambi	2026-06-11 12:26:43
349	2022	16	3144446	BPS_API	2026-06-11 12:26:43	Prov. Sumatera Selatan	2026-06-11 12:26:43
350	2022	14	2238094	BPS_API	2026-06-11 12:26:43	Prov. Bengkulu	2026-06-11 12:26:43
351	2022	18	2440486	BPS_API	2026-06-11 12:26:43	Prov. Lampung	2026-06-11 12:26:43
352	2022	17	3264884	BPS_API	2026-06-11 12:26:43	Prov. Kepulauan Bangka Belitung	2026-06-11 12:26:43
353	2022	21	3050172	BPS_API	2026-06-11 12:26:43	Prov. Kepulauan Riau	2026-06-11 12:26:43
354	2022	27	4641854	BPS_API	2026-06-11 12:26:43	Prov. D.K.I. Jakarta	2026-06-11 12:26:43
355	2022	30	1841487	BPS_API	2026-06-11 12:26:43	Prov. Jawa Barat	2026-06-11 12:26:43
356	2022	32	1812935	BPS_API	2026-06-11 12:26:43	Prov. Jawa Tengah	2026-06-11 12:26:43
357	2022	28	1840916	BPS_API	2026-06-11 12:26:43	Prov. D.I. Yogyakarta	2026-06-11 12:26:43
358	2022	33	1891567	BPS_API	2026-06-11 12:26:43	Prov. Jawa Timur	2026-06-11 12:26:43
359	2022	11	2501203	BPS_API	2026-06-11 12:26:43	Prov. Banten	2026-06-11 12:26:43
360	2022	26	2516971	BPS_API	2026-06-11 12:26:43	Prov. Bali	2026-06-11 12:26:43
361	2022	23	2207212	BPS_API	2026-06-11 12:26:43	Prov. Nusa Tenggara Barat	2026-06-11 12:26:43
362	2022	15	1975000	BPS_API	2026-06-11 12:26:43	Prov. Nusa Tenggara Timur	2026-06-11 12:26:43
363	2022	19	2434328	BPS_API	2026-06-11 12:26:43	Prov. Kalimantan Barat	2026-06-11 12:26:43
364	2022	24	2922516	BPS_API	2026-06-11 12:26:43	Prov. Kalimantan Tengah	2026-06-11 12:26:43
365	2022	25	2906473	BPS_API	2026-06-11 12:26:43	Prov. Kalimantan Selatan	2026-06-11 12:26:43
366	2022	22	3014497	BPS_API	2026-06-11 12:26:43	Prov. Kalimantan Timur	2026-06-11 12:26:43
367	2022	20	3016738	BPS_API	2026-06-11 12:26:43	Prov. Kalimantan Utara	2026-06-11 12:26:43
368	2022	12	3310723	BPS_API	2026-06-11 12:26:43	Prov. Sulawesi Utara	2026-06-11 12:26:43
369	2022	8	2390739	BPS_API	2026-06-11 12:26:43	Prov. Sulawesi Tengah	2026-06-11 12:26:43
370	2022	9	3165876	BPS_API	2026-06-11 12:26:43	Prov. Sulawesi Selatan	2026-06-11 12:26:43
371	2022	2	2576017	BPS_API	2026-06-11 12:26:43	Prov. Sulawesi Tenggara	2026-06-11 12:26:43
372	2022	7	2800580	BPS_API	2026-06-11 12:26:43	Prov. Gorontalo	2026-06-11 12:26:43
373	2022	5	2678863	BPS_API	2026-06-11 12:26:43	Prov. Sulawesi Barat	2026-06-11 12:26:43
374	2022	3	2619313	BPS_API	2026-06-11 12:26:43	Prov. Maluku	2026-06-11 12:26:43
375	2022	10	2862231	BPS_API	2026-06-11 12:26:43	Prov. Maluku Utara	2026-06-11 12:26:43
376	2022	4	3200000	BPS_API	2026-06-11 12:26:43	Prov. Papua Barat	2026-06-11 12:26:43
377	2022	13	3561932	BPS_API	2026-06-11 12:26:43	Prov. Papua	2026-06-11 12:26:43
140	2024	26	2813672	IMPORT	2026-06-11 11:51:41	Prov. Bali	2026-06-11 12:25:01
141	2024	11	2727812	IMPORT	2026-06-11 11:51:41	Prov. Banten	2026-06-11 12:25:01
142	2024	14	2507079	IMPORT	2026-06-11 11:51:41	Prov. Bengkulu	2026-06-11 12:25:01
143	2024	28	2125898	IMPORT	2026-06-11 11:51:41	Prov. D.I. Yogyakarta	2026-06-11 12:25:01
144	2024	27	5067381	IMPORT	2026-06-11 11:51:41	Prov. D.K.I. Jakarta	2026-06-11 12:25:01
145	2024	7	3025100	IMPORT	2026-06-11 11:51:41	Prov. Gorontalo	2026-06-11 12:25:01
146	2024	1	3037121	IMPORT	2026-06-11 11:51:41	Prov. Jambi	2026-06-11 12:25:01
147	2024	30	2057495	IMPORT	2026-06-11 11:51:41	Prov. Jawa Barat	2026-06-11 12:25:01
148	2024	32	2036947	IMPORT	2026-06-11 11:51:41	Prov. Jawa Tengah	2026-06-11 12:25:01
149	2024	33	2165244	IMPORT	2026-06-11 11:51:41	Prov. Jawa Timur	2026-06-11 12:25:01
150	2024	19	2702616	IMPORT	2026-06-11 11:51:41	Prov. Kalimantan Barat	2026-06-11 12:25:01
151	2024	25	3282812	IMPORT	2026-06-11 11:51:41	Prov. Kalimantan Selatan	2026-06-11 12:25:01
152	2024	24	3261616	IMPORT	2026-06-11 11:51:41	Prov. Kalimantan Tengah	2026-06-11 12:25:01
153	2024	22	3360858	IMPORT	2026-06-11 11:51:41	Prov. Kalimantan Timur	2026-06-11 12:25:01
154	2024	20	3361653	IMPORT	2026-06-11 11:51:41	Prov. Kalimantan Utara	2026-06-11 12:25:01
155	2024	17	3640000	IMPORT	2026-06-11 11:51:41	Prov. Kepulauan Bangka Belitung	2026-06-11 12:25:01
156	2024	21	3402492	IMPORT	2026-06-11 11:51:41	Prov. Kepulauan Riau	2026-06-11 12:25:01
157	2024	18	2444067	IMPORT	2026-06-11 11:51:41	Prov. Lampung	2026-06-11 12:25:01
158	2024	3	2949953	IMPORT	2026-06-11 11:51:41	Prov. Maluku	2026-06-11 12:25:01
159	2024	10	3200000	IMPORT	2026-06-11 11:51:41	Prov. Maluku Utara	2026-06-11 12:25:01
160	2024	23	2186826	IMPORT	2026-06-11 11:51:41	Prov. Nusa Tenggara Barat	2026-06-11 12:25:01
161	2024	15	2186826	IMPORT	2026-06-11 11:51:41	Prov. Nusa Tenggara Timur	2026-06-11 12:25:01
162	2024	13	4024270	IMPORT	2026-06-11 11:51:41	Prov. Papua	2026-06-11 12:25:01
163	2024	4	3393000	IMPORT	2026-06-11 11:51:41	Prov. Papua Barat	2026-06-11 12:25:01
164	2024	31	3294625	IMPORT	2026-06-11 11:51:41	Prov. Riau	2026-06-11 12:25:01
165	2024	5	2914958	IMPORT	2026-06-11 11:51:41	Prov. Sulawesi Barat	2026-06-11 12:25:01
166	2024	9	3434298	IMPORT	2026-06-11 11:51:41	Prov. Sulawesi Selatan	2026-06-11 12:25:01
167	2024	8	2736698	IMPORT	2026-06-11 11:51:41	Prov. Sulawesi Tengah	2026-06-11 12:25:01
168	2024	2	2885964	IMPORT	2026-06-11 11:51:41	Prov. Sulawesi Tenggara	2026-06-11 12:25:01
169	2024	12	3545000	IMPORT	2026-06-11 11:51:41	Prov. Sulawesi Utara	2026-06-11 12:25:01
170	2024	34	2811449	IMPORT	2026-06-11 11:51:41	Prov. Sumatera Barat	2026-06-11 12:25:01
171	2024	16	3456874	IMPORT	2026-06-11 11:51:41	Prov. Sumatera Selatan	2026-06-11 12:25:01
172	2024	35	2809915	IMPORT	2026-06-11 11:51:41	Prov. Sumatera Utara	2026-06-11 12:25:01
378	2021	29	3165031	BPS_API	2026-06-11 12:27:03	Prov. Aceh	2026-06-11 12:27:03
379	2021	35	2499423	BPS_API	2026-06-11 12:27:03	Prov. Sumatera Utara	2026-06-11 12:27:03
380	2021	34	2484041	BPS_API	2026-06-11 12:27:03	Prov. Sumatera Barat	2026-06-11 12:27:03
381	2021	31	2888564	BPS_API	2026-06-11 12:27:03	Prov. Riau	2026-06-11 12:27:03
382	2021	1	2630162	BPS_API	2026-06-11 12:27:03	Prov. Jambi	2026-06-11 12:27:03
383	2021	16	3144446	BPS_API	2026-06-11 12:27:03	Prov. Sumatera Selatan	2026-06-11 12:27:03
384	2021	14	2215000	BPS_API	2026-06-11 12:27:03	Prov. Bengkulu	2026-06-11 12:27:03
385	2021	18	2432002	BPS_API	2026-06-11 12:27:03	Prov. Lampung	2026-06-11 12:27:03
386	2021	17	3230024	BPS_API	2026-06-11 12:27:03	Prov. Kepulauan Bangka Belitung	2026-06-11 12:27:03
387	2021	21	3005460	BPS_API	2026-06-11 12:27:03	Prov. Kepulauan Riau	2026-06-11 12:27:03
388	2021	27	4416187	BPS_API	2026-06-11 12:27:03	Prov. D.K.I. Jakarta	2026-06-11 12:27:03
389	2021	30	1810351	BPS_API	2026-06-11 12:27:03	Prov. Jawa Barat	2026-06-11 12:27:03
390	2021	32	1798979	BPS_API	2026-06-11 12:27:03	Prov. Jawa Tengah	2026-06-11 12:27:03
391	2021	28	1765000	BPS_API	2026-06-11 12:27:03	Prov. D.I. Yogyakarta	2026-06-11 12:27:03
392	2021	33	1868777	BPS_API	2026-06-11 12:27:03	Prov. Jawa Timur	2026-06-11 12:27:03
452	2019	7	2384020	IMPORT	2026-06-11 12:32:31	Prov. Gorontalo	2026-06-11 12:32:31
453	2019	1	2423889	IMPORT	2026-06-11 12:32:31	Prov. Jambi	2026-06-11 12:32:31
454	2019	30	1668373	IMPORT	2026-06-11 12:32:31	Prov. Jawa Barat	2026-06-11 12:32:31
455	2019	32	1605396	IMPORT	2026-06-11 12:32:31	Prov. Jawa Tengah	2026-06-11 12:32:31
456	2019	33	1630059	IMPORT	2026-06-11 12:32:31	Prov. Jawa Timur	2026-06-11 12:32:31
457	2019	19	2211500	IMPORT	2026-06-11 12:32:31	Prov. Kalimantan Barat	2026-06-11 12:32:31
458	2019	25	2651782	IMPORT	2026-06-11 12:32:31	Prov. Kalimantan Selatan	2026-06-11 12:32:31
459	2019	24	2663435	IMPORT	2026-06-11 12:32:31	Prov. Kalimantan Tengah	2026-06-11 12:32:31
460	2019	22	2747561	IMPORT	2026-06-11 12:32:31	Prov. Kalimantan Timur	2026-06-11 12:32:31
461	2019	20	2765463	IMPORT	2026-06-11 12:32:31	Prov. Kalimantan Utara	2026-06-11 12:32:31
462	2019	17	2976706	IMPORT	2026-06-11 12:32:31	Prov. Kepulauan Bangka Belitung	2026-06-11 12:32:31
463	2019	21	2769754	IMPORT	2026-06-11 12:32:31	Prov. Kepulauan Riau	2026-06-11 12:32:31
464	2019	18	2241270	IMPORT	2026-06-11 12:32:31	Prov. Lampung	2026-06-11 12:32:31
465	2019	3	2400664	IMPORT	2026-06-11 12:32:31	Prov. Maluku	2026-06-11 12:32:31
466	2019	10	2508091	IMPORT	2026-06-11 12:32:31	Prov. Maluku Utara	2026-06-11 12:32:31
467	2019	23	2012610	IMPORT	2026-06-11 12:32:31	Prov. Nusa Tenggara Barat	2026-06-11 12:32:31
468	2019	15	1795000	IMPORT	2026-06-11 12:32:31	Prov. Nusa Tenggara Timur	2026-06-11 12:32:31
469	2019	13	3240900	IMPORT	2026-06-11 12:32:31	Prov. Papua	2026-06-11 12:32:31
470	2019	4	2934500	IMPORT	2026-06-11 12:32:31	Prov. Papua Barat	2026-06-11 12:32:31
471	2019	31	2662026	IMPORT	2026-06-11 12:32:31	Prov. Riau	2026-06-11 12:32:31
472	2019	5	2381000	IMPORT	2026-06-11 12:32:31	Prov. Sulawesi Barat	2026-06-11 12:32:31
473	2019	9	2860382	IMPORT	2026-06-11 12:32:31	Prov. Sulawesi Selatan	2026-06-11 12:32:31
474	2019	8	2123040	IMPORT	2026-06-11 12:32:31	Prov. Sulawesi Tengah	2026-06-11 12:32:31
475	2019	2	2351870	IMPORT	2026-06-11 12:32:31	Prov. Sulawesi Tenggara	2026-06-11 12:32:31
476	2019	12	3051076	IMPORT	2026-06-11 12:32:31	Prov. Sulawesi Utara	2026-06-11 12:32:31
477	2019	34	2289220	IMPORT	2026-06-11 12:32:31	Prov. Sumatera Barat	2026-06-11 12:32:31
478	2019	16	2804453	IMPORT	2026-06-11 12:32:31	Prov. Sumatera Selatan	2026-06-11 12:32:31
479	2019	35	2303403	IMPORT	2026-06-11 12:32:31	Prov. Sumatera Utara	2026-06-11 12:32:31
480	2018	29	2700000	IMPORT	2026-06-11 12:32:53	Prov. Aceh	2026-06-11 12:32:53
481	2018	26	2127157	IMPORT	2026-06-11 12:32:53	Prov. Bali	2026-06-11 12:32:53
482	2018	11	2099385	IMPORT	2026-06-11 12:32:53	Prov. Banten	2026-06-11 12:32:53
483	2018	14	1888741	IMPORT	2026-06-11 12:32:53	Prov. Bengkulu	2026-06-11 12:32:53
484	2018	28	1454154	IMPORT	2026-06-11 12:32:53	Prov. D.I. Yogyakarta	2026-06-11 12:32:53
485	2018	27	3648036	IMPORT	2026-06-11 12:32:53	Prov. D.K.I. Jakarta	2026-06-11 12:32:53
486	2018	7	2206813	IMPORT	2026-06-11 12:32:53	Prov. Gorontalo	2026-06-11 12:32:53
487	2018	1	2243719	IMPORT	2026-06-11 12:32:53	Prov. Jambi	2026-06-11 12:32:53
488	2018	30	1544361	IMPORT	2026-06-11 12:32:53	Prov. Jawa Barat	2026-06-11 12:32:53
489	2018	32	1486065	IMPORT	2026-06-11 12:32:53	Prov. Jawa Tengah	2026-06-11 12:32:53
490	2018	33	1508895	IMPORT	2026-06-11 12:32:53	Prov. Jawa Timur	2026-06-11 12:32:53
491	2018	19	2046900	IMPORT	2026-06-11 12:32:53	Prov. Kalimantan Barat	2026-06-11 12:32:53
492	2018	25	2454671	IMPORT	2026-06-11 12:32:53	Prov. Kalimantan Selatan	2026-06-11 12:32:53
493	2018	24	2421305	IMPORT	2026-06-11 12:32:53	Prov. Kalimantan Tengah	2026-06-11 12:32:53
494	2018	22	2543332	IMPORT	2026-06-11 12:32:53	Prov. Kalimantan Timur	2026-06-11 12:32:53
495	2018	20	2559903	IMPORT	2026-06-11 12:32:53	Prov. Kalimantan Utara	2026-06-11 12:32:53
496	2018	17	2755444	IMPORT	2026-06-11 12:32:53	Prov. Kepulauan Bangka Belitung	2026-06-11 12:32:53
497	2018	21	2563875	IMPORT	2026-06-11 12:32:53	Prov. Kepulauan Riau	2026-06-11 12:32:53
498	2018	18	2074673	IMPORT	2026-06-11 12:32:53	Prov. Lampung	2026-06-11 12:32:53
499	2018	3	2222220	IMPORT	2026-06-11 12:32:53	Prov. Maluku	2026-06-11 12:32:53
500	2018	10	2320803	IMPORT	2026-06-11 12:32:53	Prov. Maluku Utara	2026-06-11 12:32:53
501	2018	23	1825000	IMPORT	2026-06-11 12:32:53	Prov. Nusa Tenggara Barat	2026-06-11 12:32:53
502	2018	15	1660000	IMPORT	2026-06-11 12:32:53	Prov. Nusa Tenggara Timur	2026-06-11 12:32:53
503	2018	13	3000000	IMPORT	2026-06-11 12:32:53	Prov. Papua	2026-06-11 12:32:53
504	2018	4	2667000	IMPORT	2026-06-11 12:32:53	Prov. Papua Barat	2026-06-11 12:32:53
505	2018	31	2464154	IMPORT	2026-06-11 12:32:53	Prov. Riau	2026-06-11 12:32:53
506	2018	5	2193530	IMPORT	2026-06-11 12:32:53	Prov. Sulawesi Barat	2026-06-11 12:32:53
507	2018	9	2647767	IMPORT	2026-06-11 12:32:53	Prov. Sulawesi Selatan	2026-06-11 12:32:53
508	2018	8	1965232	IMPORT	2026-06-11 12:32:53	Prov. Sulawesi Tengah	2026-06-11 12:32:53
509	2018	2	2177052	IMPORT	2026-06-11 12:32:53	Prov. Sulawesi Tenggara	2026-06-11 12:32:53
510	2018	12	2824286	IMPORT	2026-06-11 12:32:53	Prov. Sulawesi Utara	2026-06-11 12:32:53
511	2018	34	2119067	IMPORT	2026-06-11 12:32:53	Prov. Sumatera Barat	2026-06-11 12:32:53
512	2018	16	2595995	IMPORT	2026-06-11 12:32:53	Prov. Sumatera Selatan	2026-06-11 12:32:53
513	2018	35	2132189	IMPORT	2026-06-11 12:32:53	Prov. Sumatera Utara	2026-06-11 12:32:53
514	2020	29	3165031	IMPORT	2026-06-11 22:27:21.57489	Prov. Aceh	2026-06-11 22:31:30.113362
515	2020	35	2499423	IMPORT	2026-06-11 22:27:21.57489	Prov. Sumatera Utara	2026-06-11 22:31:30.113362
516	2020	34	2484041	IMPORT	2026-06-11 22:27:21.57489	Prov. Sumatera Barat	2026-06-11 22:31:30.113362
517	2020	31	2888564	IMPORT	2026-06-11 22:27:21.57489	Prov. Riau	2026-06-11 22:31:30.113362
518	2020	1	2630162	IMPORT	2026-06-11 22:27:21.57489	Prov. Jambi	2026-06-11 22:31:30.113362
519	2020	16	3043111	IMPORT	2026-06-11 22:27:21.57489	Prov. Sumatera Selatan	2026-06-11 22:31:30.113362
520	2020	14	2213604	IMPORT	2026-06-11 22:27:21.57489	Prov. Bengkulu	2026-06-11 22:31:30.113362
521	2020	18	2432002	IMPORT	2026-06-11 22:27:21.57489	Prov. Lampung	2026-06-11 22:31:30.113362
522	2020	17	3230024	IMPORT	2026-06-11 22:27:21.57489	Prov. Kepulauan Bangka Belitung	2026-06-11 22:31:30.113362
523	2020	21	3005460	IMPORT	2026-06-11 22:27:21.57489	Prov. Kepulauan Riau	2026-06-11 22:31:30.113362
524	2020	27	4276350	IMPORT	2026-06-11 22:27:21.57489	Prov. D.K.I. Jakarta	2026-06-11 22:31:30.113362
525	2020	30	1810351	IMPORT	2026-06-11 22:27:21.57489	Prov. Jawa Barat	2026-06-11 22:31:30.113362
526	2020	32	1742015	IMPORT	2026-06-11 22:27:21.57489	Prov. Jawa Tengah	2026-06-11 22:31:30.113362
527	2020	28	1704608	IMPORT	2026-06-11 22:27:21.57489	Prov. D.I. Yogyakarta	2026-06-11 22:31:30.113362
528	2020	33	1768777	IMPORT	2026-06-11 22:27:21.57489	Prov. Jawa Timur	2026-06-11 22:31:30.113362
529	2020	11	2460997	IMPORT	2026-06-11 22:27:21.57489	Prov. Banten	2026-06-11 22:31:30.113362
530	2020	26	2494000	IMPORT	2026-06-11 22:27:21.57489	Prov. Bali	2026-06-11 22:31:30.113362
531	2020	23	2183883	IMPORT	2026-06-11 22:27:21.57489	Prov. Nusa Tenggara Barat	2026-06-11 22:31:30.113362
532	2020	15	1950000	IMPORT	2026-06-11 22:27:21.57489	Prov. Nusa Tenggara Timur	2026-06-11 22:31:30.113362
533	2020	19	2399699	IMPORT	2026-06-11 22:27:21.57489	Prov. Kalimantan Barat	2026-06-11 22:31:30.113362
534	2020	24	2903145	IMPORT	2026-06-11 22:27:21.57489	Prov. Kalimantan Tengah	2026-06-11 22:31:30.113362
535	2020	25	2877449	IMPORT	2026-06-11 22:27:21.57489	Prov. Kalimantan Selatan	2026-06-11 22:31:30.113362
536	2020	22	2981379	IMPORT	2026-06-11 22:27:21.57489	Prov. Kalimantan Timur	2026-06-11 22:31:30.113362
537	2020	20	3000804	IMPORT	2026-06-11 22:27:21.57489	Prov. Kalimantan Utara	2026-06-11 22:31:30.113362
538	2020	12	3310723	IMPORT	2026-06-11 22:27:21.57489	Prov. Sulawesi Utara	2026-06-11 22:31:30.113362
539	2020	8	2303711	IMPORT	2026-06-11 22:27:21.57489	Prov. Sulawesi Tengah	2026-06-11 22:31:30.113362
540	2020	9	3103800	IMPORT	2026-06-11 22:27:21.57489	Prov. Sulawesi Selatan	2026-06-11 22:31:30.113362
541	2020	2	2552015	IMPORT	2026-06-11 22:27:21.57489	Prov. Sulawesi Tenggara	2026-06-11 22:31:30.113362
542	2020	7	2788826	IMPORT	2026-06-11 22:27:21.57489	Prov. Gorontalo	2026-06-11 22:31:30.113362
543	2020	5	2678863	IMPORT	2026-06-11 22:27:21.57489	Prov. Sulawesi Barat	2026-06-11 22:31:30.113362
544	2020	3	2604961	IMPORT	2026-06-11 22:27:21.57489	Prov. Maluku	2026-06-11 22:31:30.113362
545	2020	10	2721530	IMPORT	2026-06-11 22:27:21.57489	Prov. Maluku Utara	2026-06-11 22:31:30.113362
546	2020	4	3134600	IMPORT	2026-06-11 22:27:21.57489	Prov. Papua Barat	2026-06-11 22:31:30.113362
547	2020	13	3516700	IMPORT	2026-06-11 22:27:21.57489	Prov. Papua	2026-06-11 22:31:30.113362
\.


--
-- TOC entry 4134 (class 0 OID 106897)
-- Dependencies: 309
-- Data for Name: semantic_role_registry; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.semantic_role_registry (role_key, label, category, description, expected_kind, value_min, value_max, sample_valid_answer, target_table, target_column, grain, is_active, created_at, updated_at) FROM stdin;
status_pekerjaan	Status Pekerjaan	keterserapan	Status utama pekerjaan lulusan saat ini (bekerja/wirausaha/melanjutkan studi/dst)	categorical	\N	\N	Bekerja (full time / part time)	fact_tracer_study	\N	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
masa_tunggu_bekerja	Masa Tunggu Kerja	waktu_tunggu	Jumlah bulan sejak lulus hingga mendapat pekerjaan pertama	integer	0.00	120.00	3	fact_tracer_study	masa_tunggu_bekerja	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
bulan_sebelum_lulus	Bulan Sebelum Lulus Mulai Cari Kerja	waktu_tunggu	Jumlah bulan sebelum lulus alumni mulai mencari kerja	integer	0.00	60.00	2	fact_tracer_study	bulan_sebelum_lulus	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
pendapatan	Pendapatan / Take Home Pay	pendapatan	Take home pay bulanan dalam Rupiah	integer	100000.00	999999999.00	4500000	fact_tracer_study	take_home_pay	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
relevansi_bidang	Kesesuaian Bidang Studi	kesesuaian_bidang	Kesesuaian BIDANG studi dengan pekerjaan (Sangat Erat..Tidak Sama Sekali)	categorical	\N	\N	Erat	dim_kesesuaian_bidang	\N	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
kesesuaian_level	Kesesuaian Level Pendidikan	kesesuaian_level	Kesesuaian LEVEL/tingkat pendidikan dengan pekerjaan -- independen dari relevansi_bidang	categorical	\N	\N	Tingkat yang Sama	dim_kesesuaian_level	\N	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
sumber_biaya_lanjut	Sumber Biaya Studi Lanjut	studi_lanjut	Sumber pembiayaan studi lanjut (jika melanjutkan pendidikan)	categorical	\N	\N	Beasiswa	dim_studi_lanjut	sumber_biaya	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
pt_lanjut	Perguruan Tinggi Studi Lanjut	studi_lanjut	Nama perguruan tinggi tempat studi lanjut	text	\N	\N	Institut Teknologi Bandung	dim_studi_lanjut	perguruan_tinggi	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
prodi_lanjut	Program Studi Lanjut	studi_lanjut	Nama program studi tempat studi lanjut	text	\N	\N	Magister Teknik Sipil	dim_studi_lanjut	program_studi	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
provinsi_kerja	Provinsi Tempat Kerja	lokasi_kerja	Provinsi tempat alumni bekerja (disimpan sebagai FK id provinsi)	categorical	\N	\N	32	dim_perusahaan/dim_wirausaha	\N	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
kota_kerja	Kota Tempat Kerja	lokasi_kerja	Kota tempat alumni bekerja (disimpan sebagai FK id kota)	categorical	\N	\N	3273	dim_perusahaan/dim_wirausaha	\N	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
nama_perusahaan	Nama Perusahaan	perusahaan	Nama perusahaan/instansi tempat bekerja	text	\N	\N	PT Telekomunikasi Indonesia	dim_perusahaan	nama_perusahaan	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
jabatan_wirausaha	Jabatan Wirausaha	perusahaan	Jabatan/posisi pada usaha sendiri (jika wirausaha)	text	\N	\N	Pemilik	dim_wirausaha	jabatan	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
tingkat_instansi	Tingkat Instansi	perusahaan	Tingkat instansi tempat bekerja/berwirausaha (lokal/nasional/multinasional)	categorical	\N	\N	Nasional	dim_perusahaan/dim_wirausaha	tingkat_instansi	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
jenis_perusahaan	Jenis Perusahaan	perusahaan	Jenis/kategori perusahaan tempat bekerja	categorical	\N	\N	BUMN	dim_perusahaan	jenis_perusahaan	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
sumber_biaya_studi	Sumber Biaya Studi (S1/Diploma)	biaya_studi	Sumber pembiayaan kuliah S1/Diploma alumni	categorical	\N	\N	Biaya Sendiri	dim_alumni	label_sumber_biaya_dipolban	narrow	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
kompetensi_evaluasi	Evaluasi Kompetensi	kompetensi	Battery evaluasi kompetensi lulusan (f1761-f1774), satu item per question_code via dim_indikator_evaluasi	integer	1.00	5.00	4	fact_range_evaluasi	\N	wide	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
metode_pembelajaran	Metode Pembelajaran	metode_pembelajaran	Evaluasi metode pembelajaran yang berkontribusi pada kompetensi (f21-f27)	integer	1.00	5.00	4	fact_range_evaluasi	\N	wide	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
alasan_kerja_tidak_sesuai	Alasan Kerja Tidak Sesuai Bidang	ketidaksesuaian_kerja	Alasan multi-pilihan kenapa pekerjaan tidak sesuai bidang studi (f1601-f1613)	boolean	\N	\N	true	fact_multi_select	\N	wide	t	2026-07-12 23:23:38.609238	2026-07-12 23:23:38.609238
\.


--
-- TOC entry 4131 (class 0 OID 106847)
-- Dependencies: 304
-- Data for Name: threshold_configs; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.threshold_configs (id, lam_version_id, indicator_id, param_value, created_at, updated_at) FROM stdin;
3	8	1	2.00	2026-07-14 04:36:41	2026-07-14 04:36:41
4	8	5	1.40	2026-07-14 04:36:41	2026-07-14 04:36:41
1	7	1	3.00	2026-07-14 05:56:36	2026-07-14 05:56:36
2	7	5	1.20	2026-07-14 05:56:36	2026-07-14 05:56:36
\.


--
-- TOC entry 4133 (class 0 OID 106872)
-- Dependencies: 306
-- Data for Name: tracer_response_thresholds; Type: TABLE DATA; Schema: tracer_oltp; Owner: postgres
--

COPY tracer_oltp.tracer_response_thresholds (id, program_id, graduated_year, total_lulusan, margin_error, min_responden, threshold_value, calculated_at) FROM stdin;
2	2	2019	60	0.0230	58	96.92	2026-07-12 14:19:37
3	5	2022	61	0.0230	59	96.87	2026-07-12 14:19:37
4	3	2022	27	0.0230	27	98.59	2026-07-12 14:19:37
5	3	2021	26	0.0230	26	98.64	2026-07-12 14:19:37
6	23	2020	39	0.0230	38	97.98	2026-07-12 14:19:37
7	23	2022	24	0.0230	24	98.75	2026-07-12 14:19:37
8	5	2021	51	0.0230	50	97.37	2026-07-12 14:19:37
9	4	2022	23	0.0230	23	98.80	2026-07-12 14:19:37
10	3	2019	31	0.0230	30	98.39	2026-07-12 14:19:37
11	1	2022	52	0.0230	51	97.32	2026-07-12 14:19:37
12	1	2024	60	0.0230	58	96.92	2026-07-12 14:19:37
13	23	2023	52	0.0230	51	97.32	2026-07-12 14:19:37
14	5	2023	62	0.0230	60	96.82	2026-07-12 14:19:37
15	2	2020	59	0.0230	57	96.97	2026-07-12 14:19:37
16	23	2024	61	0.0230	59	96.87	2026-07-12 14:19:37
17	1	2021	58	0.0230	56	97.02	2026-07-12 14:19:37
18	3	2024	54	0.0230	53	97.22	2026-07-12 14:19:37
19	1	2019	51	0.0230	50	97.37	2026-07-12 14:19:37
20	1	2023	69	0.0230	67	96.48	2026-07-12 14:19:37
21	1	2020	65	0.0230	63	96.68	2026-07-12 14:19:37
22	2	2023	56	0.0230	54	97.12	2026-07-12 14:19:37
23	5	2020	59	0.0230	57	96.97	2026-07-12 14:19:37
24	23	2021	34	0.0230	33	98.23	2026-07-12 14:19:37
25	4	2024	51	0.0230	50	97.37	2026-07-12 14:19:37
26	2	2024	61	0.0230	59	96.87	2026-07-12 14:19:37
27	2	2022	51	0.0230	50	97.37	2026-07-12 14:19:37
28	5	2024	61	0.0230	59	96.87	2026-07-12 14:19:37
29	4	2020	32	0.0230	31	98.34	2026-07-12 14:19:37
30	5	2019	58	0.0230	56	97.02	2026-07-12 14:19:37
31	3	2023	37	0.0230	36	98.08	2026-07-12 14:19:37
32	23	2019	25	0.0230	25	98.69	2026-07-12 14:19:37
33	4	2021	30	0.0230	30	98.44	2026-07-12 14:19:37
34	4	2023	29	0.0230	29	98.49	2026-07-12 14:19:37
35	3	2020	25	0.0230	25	98.69	2026-07-12 14:19:37
36	2	2021	55	0.0230	53	97.17	2026-07-12 14:19:37
1	4	2019	28	0.0230	28	98.54	2026-07-12 14:19:53
\.


--
-- TOC entry 4231 (class 0 OID 0)
-- Dependencies: 330
-- Name: etl_runs_id_seq; Type: SEQUENCE SET; Schema: tracer_oltp; Owner: postgres
--

SELECT pg_catalog.setval('tracer_oltp.etl_runs_id_seq', 1, true);


--
-- TOC entry 4232 (class 0 OID 0)
-- Dependencies: 328
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: tracer_oltp; Owner: postgres
--

SELECT pg_catalog.setval('tracer_oltp.failed_jobs_id_seq', 1, false);


--
-- TOC entry 4233 (class 0 OID 0)
-- Dependencies: 325
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: tracer_oltp; Owner: postgres
--

SELECT pg_catalog.setval('tracer_oltp.jobs_id_seq', 1, true);


--
-- TOC entry 4242 (class 0 OID 0)
-- Dependencies: 310
-- Name: question_semantic_mapping_id_seq; Type: SEQUENCE SET; Schema: tracer_oltp; Owner: postgres
--

SELECT pg_catalog.setval('tracer_oltp.question_semantic_mapping_id_seq', 153, true);


--
-- TOC entry 4248 (class 0 OID 0)
-- Dependencies: 286
-- Name: ref_ump_id_seq; Type: SEQUENCE SET; Schema: tracer_oltp; Owner: postgres
--

SELECT pg_catalog.setval('tracer_oltp.ref_ump_id_seq', 581, true);


--
-- TOC entry 4253 (class 0 OID 0)
-- Dependencies: 303
-- Name: threshold_configs_id_seq; Type: SEQUENCE SET; Schema: tracer_oltp; Owner: postgres
--

SELECT pg_catalog.setval('tracer_oltp.threshold_configs_id_seq', 4, true);


--
-- TOC entry 4256 (class 0 OID 0)
-- Dependencies: 305
-- Name: tracer_response_thresholds_id_seq; Type: SEQUENCE SET; Schema: tracer_oltp; Owner: postgres
--

SELECT pg_catalog.setval('tracer_oltp.tracer_response_thresholds_id_seq', 36, true);


--
-- TOC entry 3841 (class 2606 OID 108000)
-- Name: etl_runs etl_runs_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.etl_runs
    ADD CONSTRAINT etl_runs_pkey PRIMARY KEY (id);


--
-- TOC entry 3837 (class 2606 OID 107986)
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- TOC entry 3839 (class 2606 OID 107988)
-- Name: failed_jobs failed_jobs_uuid_key; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_key UNIQUE (uuid);


--
-- TOC entry 3835 (class 2606 OID 107977)
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- TOC entry 3833 (class 2606 OID 107969)
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- TOC entry 3819 (class 2606 OID 106922)
-- Name: question_semantic_mapping question_semantic_mapping_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.question_semantic_mapping
    ADD CONSTRAINT question_semantic_mapping_pkey PRIMARY KEY (id);


--
-- TOC entry 3767 (class 2606 OID 99494)
-- Name: ref_ump ref_ump_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.ref_ump
    ADD CONSTRAINT ref_ump_pkey PRIMARY KEY (id);


--
-- TOC entry 3769 (class 2606 OID 99496)
-- Name: ref_ump ref_ump_tahun_province_id_key; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.ref_ump
    ADD CONSTRAINT ref_ump_tahun_province_id_key UNIQUE (tahun, province_id);


--
-- TOC entry 3816 (class 2606 OID 106909)
-- Name: semantic_role_registry semantic_role_registry_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.semantic_role_registry
    ADD CONSTRAINT semantic_role_registry_pkey PRIMARY KEY (role_key);


--
-- TOC entry 3807 (class 2606 OID 106855)
-- Name: threshold_configs threshold_configs_lam_version_id_indicator_id_key; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.threshold_configs
    ADD CONSTRAINT threshold_configs_lam_version_id_indicator_id_key UNIQUE (lam_version_id, indicator_id);


--
-- TOC entry 3809 (class 2606 OID 106853)
-- Name: threshold_configs threshold_configs_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.threshold_configs
    ADD CONSTRAINT threshold_configs_pkey PRIMARY KEY (id);


--
-- TOC entry 3812 (class 2606 OID 106878)
-- Name: tracer_response_thresholds tracer_response_thresholds_pkey; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.tracer_response_thresholds
    ADD CONSTRAINT tracer_response_thresholds_pkey PRIMARY KEY (id);


--
-- TOC entry 3814 (class 2606 OID 106880)
-- Name: tracer_response_thresholds tracer_response_thresholds_program_id_graduated_year_key; Type: CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.tracer_response_thresholds
    ADD CONSTRAINT tracer_response_thresholds_program_id_graduated_year_key UNIQUE (program_id, graduated_year);


--
-- TOC entry 3810 (class 1259 OID 106886)
-- Name: idx_tracer_response_thresholds_year; Type: INDEX; Schema: tracer_oltp; Owner: postgres
--

CREATE INDEX idx_tracer_response_thresholds_year ON tracer_oltp.tracer_response_thresholds USING btree (graduated_year);


--
-- TOC entry 3842 (class 1259 OID 108006)
-- Name: ix_etl_runs_status; Type: INDEX; Schema: tracer_oltp; Owner: postgres
--

CREATE INDEX ix_etl_runs_status ON tracer_oltp.etl_runs USING btree (status);


--
-- TOC entry 3831 (class 1259 OID 107970)
-- Name: ix_jobs_queue; Type: INDEX; Schema: tracer_oltp; Owner: postgres
--

CREATE INDEX ix_jobs_queue ON tracer_oltp.jobs USING btree (queue);


--
-- TOC entry 3817 (class 1259 OID 106945)
-- Name: ix_qsm_role; Type: INDEX; Schema: tracer_oltp; Owner: postgres
--

CREATE INDEX ix_qsm_role ON tracer_oltp.question_semantic_mapping USING btree (semantic_role) WHERE is_active;


--
-- TOC entry 3820 (class 1259 OID 106943)
-- Name: uq_qsm_active_code; Type: INDEX; Schema: tracer_oltp; Owner: postgres
--

CREATE UNIQUE INDEX uq_qsm_active_code ON tracer_oltp.question_semantic_mapping USING btree (questionnaire_id, question_code) WHERE is_active;


--
-- TOC entry 3821 (class 1259 OID 106944)
-- Name: uq_qsm_active_narrow_role; Type: INDEX; Schema: tracer_oltp; Owner: postgres
--

CREATE UNIQUE INDEX uq_qsm_active_narrow_role ON tracer_oltp.question_semantic_mapping USING btree (questionnaire_id, semantic_role) WHERE (is_active AND ((grain)::text = 'narrow'::text));


--
-- TOC entry 3899 (class 2606 OID 108001)
-- Name: etl_runs etl_runs_triggered_by_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.etl_runs
    ADD CONSTRAINT etl_runs_triggered_by_fkey FOREIGN KEY (triggered_by) REFERENCES tracer_oltp.users(id);


--
-- TOC entry 3895 (class 2606 OID 106938)
-- Name: question_semantic_mapping question_semantic_mapping_deactivated_by_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.question_semantic_mapping
    ADD CONSTRAINT question_semantic_mapping_deactivated_by_fkey FOREIGN KEY (deactivated_by) REFERENCES tracer_oltp.users(id);


--
-- TOC entry 3896 (class 2606 OID 106933)
-- Name: question_semantic_mapping question_semantic_mapping_mapped_by_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.question_semantic_mapping
    ADD CONSTRAINT question_semantic_mapping_mapped_by_fkey FOREIGN KEY (mapped_by) REFERENCES tracer_oltp.users(id);


--
-- TOC entry 3897 (class 2606 OID 106923)
-- Name: question_semantic_mapping question_semantic_mapping_questionnaire_id_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.question_semantic_mapping
    ADD CONSTRAINT question_semantic_mapping_questionnaire_id_fkey FOREIGN KEY (questionnaire_id) REFERENCES tracer_oltp.questionnaires(id);


--
-- TOC entry 3898 (class 2606 OID 106928)
-- Name: question_semantic_mapping question_semantic_mapping_semantic_role_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.question_semantic_mapping
    ADD CONSTRAINT question_semantic_mapping_semantic_role_fkey FOREIGN KEY (semantic_role) REFERENCES tracer_oltp.semantic_role_registry(role_key);


--
-- TOC entry 3883 (class 2606 OID 99784)
-- Name: ref_ump ref_ump_province_id_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.ref_ump
    ADD CONSTRAINT ref_ump_province_id_fkey FOREIGN KEY (province_id) REFERENCES tracer_oltp.provinces(id);


--
-- TOC entry 3892 (class 2606 OID 106861)
-- Name: threshold_configs threshold_configs_indicator_id_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.threshold_configs
    ADD CONSTRAINT threshold_configs_indicator_id_fkey FOREIGN KEY (indicator_id) REFERENCES tracer_oltp.threshold_indicators(id) ON DELETE RESTRICT;


--
-- TOC entry 3893 (class 2606 OID 106856)
-- Name: threshold_configs threshold_configs_lam_version_id_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.threshold_configs
    ADD CONSTRAINT threshold_configs_lam_version_id_fkey FOREIGN KEY (lam_version_id) REFERENCES tracer_oltp.lam_versions(id) ON DELETE CASCADE;


--
-- TOC entry 3894 (class 2606 OID 106881)
-- Name: tracer_response_thresholds tracer_response_thresholds_program_id_fkey; Type: FK CONSTRAINT; Schema: tracer_oltp; Owner: postgres
--

ALTER TABLE ONLY tracer_oltp.tracer_response_thresholds
    ADD CONSTRAINT tracer_response_thresholds_program_id_fkey FOREIGN KEY (program_id) REFERENCES tracer_oltp.programs(id) ON DELETE CASCADE;


