-- Data master tracer_oltp: tabel yang dibuat migration tapi tidak punya seeder.
-- Diekstrak dari database/dump/init.sql. Urutan mengikuti dependensi FK.
SET client_encoding = 'UTF8';
SET client_min_messages = warning;

--
-- Data: lams
--

COPY tracer_oltp.lams (id, name, code, created_at, updated_at) FROM stdin;
3	LAM EMBA	EMBA	2026-05-13 22:15:12	2026-07-14 04:38:14
\.

SELECT pg_catalog.setval('tracer_oltp.lams_id_seq', 2, true);

--
-- Data: lam_versions
--

COPY tracer_oltp.lam_versions (id, lam_id, year, version_name, is_active, created_at, updated_at) FROM stdin;
8	3	2021	2021	t	2026-07-11 03:13:19	2026-07-11 03:13:19
7	3	2020	\N	t	2026-06-21 18:44:23	2026-07-12 14:20:25
\.

SELECT pg_catalog.setval('tracer_oltp.lam_versions_id_seq', 8, true);

--
-- Data: lam_programs
--

COPY tracer_oltp.lam_programs (id, lam_id, program_id, created_at) FROM stdin;
13	3	5	2026-05-17 11:18:21
14	3	3	2026-05-17 11:18:21
15	3	2	2026-05-17 11:18:21
16	3	4	2026-05-17 11:18:21
17	3	1	2026-05-17 11:18:21
4	3	23	2026-06-21 18:36:37
5	3	24	2026-07-14 04:37:34
\.

SELECT pg_catalog.setval('tracer_oltp.lam_programs_id_seq', 5, true);

--
-- Data: threshold_indicators
--

COPY tracer_oltp.threshold_indicators (id, key, name, unit, operator, description, dynamic_param_unit, is_system_calculated) FROM stdin;
2	entrepreneurship	Lulusan Berwirausaha	%	>=	Persentase lulusan yang berwirausaha	\N	f
4	field_relevance	Kesesuaian Bidang Kerja	%	>=	Persentase lulusan yang bekerja sesuai bidang studi	\N	f
6	graduate_absorption	Keterserapan Alumni	%	>=	Persentase lulusan yang bekerja, berwirausaha, atau melanjutkan studi	\N	f
1	employment_time	Lulusan Bekerja ≤ {value} Bulan	%	>=	Persentase lulusan yang mendapat pekerjaan dalam 6 bulan setelah lulus	bulan	f
5	salary_above_ump	Penghasilan ≥ {value}x UMP/UMK	%	>=	Persentase lulusan dengan penghasilan di atas UMP/UMK	x_ump	f
3	tracer_response	Respon Tracer Study Alumni	%	>=	Tingkat respon alumni terhadap tracer study	\N	t
\.

SELECT pg_catalog.setval('tracer_oltp.threshold_indicators_id_seq', 1, true);

--
-- Data: thresholds
--

COPY tracer_oltp.thresholds (id, name, value, created_by, created_at, updated_at, lam_version_id, indicator_id, level) FROM stdin;
3	Lulusan Bekerja ≤ 6 Bulan	60.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	1	baik
7	Respon Tracer Study Alumni	87.14	1	2026-06-21 18:44:24	2026-07-11 03:10:44	7	3	baik
8	Respon Tracer Study Alumni	87.14	1	2026-06-21 18:44:24	2026-07-11 03:10:44	7	3	unggul
4	Lulusan Bekerja ≤ 6 Bulan	70.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	1	unggul
5	Lulusan Berwirausaha	5.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	2	baik
6	Lulusan Berwirausaha	10.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	2	unggul
9	Kesesuaian Bidang Kerja	77.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	4	baik
10	Kesesuaian Bidang Kerja	92.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	4	unggul
11	Penghasilan ≥ UMP/UMK	70.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	5	baik
19	Respon Tracer Study Alumni	88.15	1	2026-07-11 03:13:20	2026-07-11 03:13:20	8	3	baik
20	Respon Tracer Study Alumni	88.15	1	2026-07-11 03:13:20	2026-07-11 03:13:20	8	3	unggul
12	Penghasilan ≥ UMP/UMK	85.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	5	unggul
13	Keterserapan Alumni	75.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	6	baik
14	Keterserapan Alumni	80.00	1	2026-06-21 18:44:24	2026-07-14 05:36:33	7	6	unggul
15	Lulusan Bekerja ≤ {value} Bulan	50.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	1	baik
16	Lulusan Bekerja ≤ {value} Bulan	55.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	1	unggul
17	Lulusan Berwirausaha	55.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	2	baik
18	Lulusan Berwirausaha	55.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	2	unggul
21	Kesesuaian Bidang Kerja	60.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	4	baik
22	Kesesuaian Bidang Kerja	70.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	4	unggul
23	Penghasilan ≥ {value}x UMP/UMK	60.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	5	baik
24	Penghasilan ≥ {value}x UMP/UMK	70.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	5	unggul
25	Keterserapan Alumni	60.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	6	baik
26	Keterserapan Alumni	85.00	1	2026-07-11 03:13:20	2026-07-14 05:56:36	8	6	unggul
\.

SELECT pg_catalog.setval('tracer_oltp.thresholds_id_seq', 26, true);

