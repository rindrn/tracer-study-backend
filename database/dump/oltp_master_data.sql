-- Data master OLTP: referensi, konfigurasi, akun staf, dan template kuesioner.
--
-- Diekstrak dari tracer_recon: init.sql yang sudah di-restore, direkonsiliasi
-- (005_reconcile_init.sql), lalu dimigrasi penuh. Karena itu kolomnya sudah
-- cocok dengan skema hasil `php artisan migrate` terbaru.
--
-- HANYA DATA -- tidak ada CREATE TABLE sama sekali. Skema dimiliki migrasi.
-- Urutan tabel mengikuti dependensi foreign key.
--
-- Jangan disunting tangan. Bangun ulang lewat scripts/rebuild-data-dumps.sh
-- kalau isinya perlu diperbarui.

SET client_encoding = 'UTF8';
SET client_min_messages = warning;






COPY tracer_oltp.provinces (id, code, name) FROM stdin;
1	100000	Prov. Jambi
2	200000	Prov. Sulawesi Tenggara
3	210000	Prov. Maluku
4	320000	Prov. Papua Barat
5	330000	Prov. Sulawesi Barat
6	350000	Luar Negeri
7	300000	Prov. Gorontalo
8	180000	Prov. Sulawesi Tengah
9	190000	Prov. Sulawesi Selatan
10	270000	Prov. Maluku Utara
11	280000	Prov. Banten
12	170000	Prov. Sulawesi Utara
13	250000	Prov. Papua
14	260000	Prov. Bengkulu
15	240000	Prov. Nusa Tenggara Timur
16	110000	Prov. Sumatera Selatan
17	290000	Prov. Kepulauan Bangka Belitung
18	120000	Prov. Lampung
19	130000	Prov. Kalimantan Barat
20	340000	Prov. Kalimantan Utara
21	310000	Prov. Kepulauan Riau
22	160000	Prov. Kalimantan Timur
23	230000	Prov. Nusa Tenggara Barat
24	140000	Prov. Kalimantan Tengah
25	150000	Prov. Kalimantan Selatan
26	220000	Prov. Bali
27	10000	Prov. D.K.I. Jakarta
28	40000	Prov. D.I. Yogyakarta
29	60000	Prov. Aceh
30	20000	Prov. Jawa Barat
31	90000	Prov. Riau
32	30000	Prov. Jawa Tengah
33	50000	Prov. Jawa Timur
34	80000	Prov. Sumatera Barat
35	70000	Prov. Sumatera Utara
\.



SELECT pg_catalog.setval('tracer_oltp.provinces_id_seq', 35, true);









COPY tracer_oltp.cities (id, province_code, code, name) FROM stdin;
1	10000	10100	Kab. Kepulauan Seribu
2	10000	16200	Kota Jakarta Barat
3	10000	16000	Kota Jakarta Pusat
4	10000	16300	Kota Jakarta Selatan
5	10000	16400	Kota Jakarta Timur
6	10000	16100	Kota Jakarta Utara
7	100000	100100	Kab. Batang Hari
8	100000	100200	Kab. Bungo
9	100000	100500	Kab. Kerinci
10	100000	100900	Kab. Merangin
11	100000	100700	Kab. Muaro Jambi
12	100000	100300	Kab. Sarolangun
13	100000	100400	Kab. Tanjung Jabung Barat
14	100000	100800	Kab. Tanjung Jabung Timur
15	100000	100600	Kab. Tebo
16	100000	106000	Kota Jambi
17	100000	106100	Kota Sungai Penuh
18	110000	110700	Kab. Banyuasin
19	110000	111100	Kab. Empat Lawang
20	110000	110500	Kab. Lahat
21	110000	110400	Kab. Muara Enim
22	110000	110100	Kab. Musi Banyuasin
23	110000	110600	Kab. Musi Rawas
24	110000	111300	Kab. Musi Rawas Utara
25	110000	111000	Kab. Ogan Ilir
26	110000	110200	Kab. Ogan Komering Ilir
27	110000	110300	Kab. Ogan Komering Ulu
28	110000	110900	Kab. Ogan Komering Ulu Selatan
29	110000	110800	Kab. Ogan Komering Ulu Timur
30	110000	111200	Kab. Penukal Abab Lematang Ilir
31	110000	116200	Kota Lubuk Linggau
32	110000	116300	Kota Pagar Alam
33	110000	116000	Kota Palembang
34	110000	116100	Kota Prabumulih
35	120000	120400	Kab. Lampung Barat
36	120000	120100	Kab. Lampung Selatan
37	120000	120200	Kab. Lampung Tengah
38	120000	120700	Kab. Lampung Timur
39	120000	120300	Kab. Lampung Utara
40	120000	121100	Kab. Mesuji
41	120000	120900	Kab. Pesawaran
42	120000	121300	Kab. Pesisir Barat
43	120000	121000	Kab. Pringsewu
44	120000	120600	Kab. Tanggamus
45	120000	120500	Kab. Tulang Bawang
46	120000	121200	Kab. Tulang Bawang Barat
47	120000	120800	Kab. Way Kanan
48	120000	126000	Kota Bandar Lampung
49	120000	126100	Kota Metro
50	130000	130800	Kab. Bengkayang
51	130000	130500	Kab. Kapuas Hulu
52	130000	131200	Kab. Kayong Utara
53	130000	130600	Kab. Ketapang
54	130000	131300	Kab. Kuburaya
55	130000	130900	Kab. Landak
56	130000	131100	Kab. Melawi
57	130000	130200	Kab. Mempawah
58	130000	130100	Kab. Sambas
59	130000	130300	Kab. Sanggau
60	130000	131000	Kab. Sekadau
61	130000	130400	Kab. Sintang
62	130000	136000	Kota Pontianak
63	130000	136100	Kota Singkawang
64	140000	140200	Kab. Barito Selatan
65	140000	141300	Kab. Barito Timur
66	140000	140300	Kab. Barito Utara
67	140000	141000	Kab. Gunung Mas
68	140000	140100	Kab. Kapuas
69	140000	140600	Kab. Katingan
70	140000	140500	Kab. Kotawaringin Barat
71	140000	140400	Kab. Kotawaringin Timur
72	140000	140900	Kab. Lamandau
73	140000	141200	Kab. Murung Raya
74	140000	141100	Kab. Pulang Pisau
75	140000	140700	Kab. Seruyan
76	140000	140800	Kab. Sukamara
77	140000	146000	Kota Palangka Raya
78	150000	151000	Kab. Balangan
79	150000	150100	Kab. Banjar
80	150000	150300	Kab. Barito Kuala
81	150000	150500	Kab. Hulu Sungai Selatan
82	150000	150600	Kab. Hulu Sungai Tengah
83	150000	150700	Kab. Hulu Sungai Utara
84	150000	150900	Kab. Kotabaru
85	150000	150800	Kab. Tabalong
86	150000	151100	Kab. Tanah Bumbu
87	150000	150200	Kab. Tanah Laut
88	150000	150400	Kab. Tapin
89	150000	156100	Kota Banjarbaru
90	150000	156000	Kota Banjarmasin
91	160000	160300	Kab. Berau
92	160000	160900	Kab. Kutai Barat
93	160000	160200	Kab. Kutai Kartanegara
94	160000	161000	Kab. Kutai Timur
95	160000	161200	Kab. Mahakam Ulu
96	160000	160100	Kab. Paser
97	160000	161100	Kab. Penajam Paser Utara
98	160000	166100	Kota Balikpapan
99	160000	166300	Kota Bontang
100	160000	166000	Kota Samarinda
101	170000	170100	Kab. Bolaang Mongondow
102	170000	171200	Kab. Bolaang Mongondow Selatan
103	170000	171100	Kab. Bolaang Mongondow Timur
104	170000	170800	Kab. Bolaang Mongondow Utara
105	170000	170300	Kab. Kep. Sangihe
106	170000	170900	Kab. Kepulauan Siau Tagulandang Biaro
107	170000	170400	Kab. Kepulauan Talaud
108	170000	170200	Kab. Minahasa
109	170000	170500	Kab. Minahasa Selatan
110	170000	171000	Kab. Minahasa Tenggara
111	170000	170600	Kab. Minahasa Utara
112	170000	176100	Kota Bitung
113	170000	176300	Kota Kotamobagu
114	170000	176000	Kota Manado
115	170000	176200	Kota Tomohon
116	180000	180400	Kab. Banggai
117	180000	180100	Kab. Banggai Kepulauan
118	180000	181100	Kab. Banggai Laut
119	180000	180500	Kab. Buol
120	180000	180200	Kab. Donggala
121	180000	180700	Kab. Morowali
122	180000	181200	Kab. Morowali Utara
123	180000	180800	Kab. Parigi Moutong
124	180000	180300	Kab. Poso
125	180000	181000	Kab. Sigi
126	180000	180900	Kab. Tojo Una-Una
127	180000	180600	Kab. Tolitoli
128	180000	186000	Kota Palu
129	190000	191000	Kab. Bantaeng
130	190000	190600	Kab. Barru
131	190000	190700	Kab. Bone
132	190000	191100	Kab. Bulukumba
133	190000	191600	Kab. Enrekang
134	190000	190300	Kab. Gowa
135	190000	190500	Kab. Jeneponto
136	190000	191300	Kab. Kepulauan Selayar
137	190000	191700	Kab. Luwu
138	190000	192600	Kab. Luwu Timur
139	190000	192400	Kab. Luwu Utara
140	190000	190100	Kab. Maros
141	190000	190200	Kab. Pangkajene Kepulauan
142	190000	191400	Kab. Pinrang
143	190000	191500	Kab. Sidenreng Rappang
144	190000	191200	Kab. Sinjai
145	190000	190900	Kab. Soppeng
146	190000	190400	Kab. Takalar
147	190000	191800	Kab. Tana Toraja
148	190000	192700	Kab. Toraja Utara
149	190000	190800	Kab. Wajo
150	190000	196000	Kota Makassar
151	190000	196200	Kota Palopo
152	190000	196100	Kota Parepare
153	20000	20800	Kab. Bandung
154	20000	22300	Kab. Bandung Barat
155	20000	22200	Kab. Bekasi
156	20000	20500	Kab. Bogor
157	20000	21400	Kab. Ciamis
158	20000	20700	Kab. Cianjur
159	20000	21700	Kab. Cirebon
160	20000	21100	Kab. Garut
161	20000	21800	Kab. Indramayu
162	20000	22100	Kab. Karawang
163	20000	21500	Kab. Kuningan
164	20000	21600	Kab. Majalengka
165	20000	22500	Kab. Pangandaran
166	20000	22000	Kab. Purwakarta
167	20000	21900	Kab. Subang
168	20000	20600	Kab. Sukabumi
169	20000	21000	Kab. Sumedang
170	20000	21200	Kab. Tasikmalaya
171	20000	26000	Kota Bandung
172	20000	26900	Kota Banjar
173	20000	26500	Kota Bekasi
174	20000	26100	Kota Bogor
175	20000	26700	Kota Cimahi
176	20000	26300	Kota Cirebon
177	20000	26600	Kota Depok
178	20000	26200	Kota Sukabumi
179	20000	26800	Kota Tasikmalaya
180	200000	200700	Kab. Bombana
181	200000	200300	Kab. Buton
182	200000	201400	Kab. Buton Selatan
183	200000	201600	Kab. Buton Tengah
184	200000	201000	Kab. Buton Utara
185	200000	200400	Kab. Kolaka
186	200000	201100	Kab. Kolaka Timur
187	200000	200800	Kab. Kolaka Utara
188	200000	200100	Kab. Konawe
189	200000	201200	Kab. Konawe Kepulauan
190	200000	200500	Kab. Konawe Selatan
191	200000	200900	Kab. Konawe Utara
192	200000	200200	Kab. Muna
193	200000	201300	Kab. Muna Barat
194	200000	200600	Kab. Wakatobi
195	200000	206100	Kota Baubau
196	200000	206000	Kota Kendari
197	210000	210300	Kab. Buru
198	210000	210900	Kab. Buru Selatan
199	210000	210700	Kab. Kepulauan Aru
200	210000	210400	Kab. Kepulauan Tanimbar
201	210000	210800	Kab. Maluku Barat Daya
202	210000	210100	Kab. Maluku Tengah
203	210000	210200	Kab. Maluku Tenggara
204	210000	210500	Kab. Seram Bagian Barat
205	210000	210600	Kab. Seram Bagian Timur
206	210000	216000	Kota Ambon
207	210000	216100	Kota Tual
208	220000	220400	Kab. Badung
209	220000	220700	Kab. Bangli
210	220000	220100	Kab. Buleleng
211	220000	220500	Kab. Gianyar
212	220000	220200	Kab. Jembrana
213	220000	220800	Kab. Karang Asem
214	220000	220600	Kab. Klungkung
215	220000	220300	Kab. Tabanan
216	220000	226000	Kota Denpasar
217	230000	230600	Kab. Bima
218	230000	230500	Kab. Dompu
219	230000	230100	Kab. Lombok Barat
220	230000	230200	Kab. Lombok Tengah
221	230000	230300	Kab. Lombok Timur
222	230000	230800	Kab. Lombok Utara
223	230000	230400	Kab. Sumbawa
224	230000	230700	Kab. Sumbawa Barat
225	230000	236100	Kota Bima
226	230000	236000	Kota Mataram
227	240000	240600	Kab. Alor
228	240000	240500	Kab. Belu
229	240000	240900	Kab. Ende
230	240000	240700	Kab. Flores Timur
231	240000	240100	Kab. Kupang
232	240000	241400	Kab. Lembata
233	240000	242200	Kab. Malaka
234	240000	241100	Kab. Manggarai
353	300000	306000	Kota Gorontalo
235	240000	241600	Kab. Manggarai Barat
236	240000	242000	Kab. Manggarai Timur
237	240000	241700	Kab. Nagakeo
238	240000	241000	Kab. Ngada
239	240000	241500	Kab. Rote-Ndao
240	240000	242100	Kab. Sabu Raijua
241	240000	240800	Kab. Sikka
242	240000	241300	Kab. Sumba Barat
243	240000	241900	Kab. Sumba Barat Daya
244	240000	241800	Kab. Sumba Tengah
245	240000	241200	Kab. Sumba Timur
246	240000	240300	Kab. Timor Tengah Selatan
247	240000	240400	Kab. Timor Tengah Utara
248	240000	246000	Kota Kupang
249	250000	251500	Kab. Asmat
250	250000	250200	Kab. Biak Numfor
251	250000	251300	Kab. Boven Digoel
252	250000	253500	Kab. Deiyai
253	250000	253400	Kab. Dogiyai
254	250000	253600	Kab. Intan Jaya
255	250000	250100	Kab. Jayapura
256	250000	250800	Kab. Jaya Wijaya
257	250000	252000	Kab. Keerom
258	250000	250300	Kab. Kepulauan Yapen
259	250000	253000	Kab. Lanny Jaya
260	250000	251400	Kab. Mappi
261	250000	252800	Kab. Memberamo Raya
262	250000	253100	Kab. Membramo Tengah
263	250000	250700	Kab. Merauke
264	250000	251200	Kab. Mimika
265	250000	250900	Kab. Nabire
266	250000	252900	Kab. Nduga
267	250000	251000	Kab. Paniai
268	250000	251700	Kab. Pegunungan Bintang
269	250000	253300	kab. Puncak
270	250000	251100	Kab. Puncak Jaya
271	250000	251900	Kab. Sarmi
272	250000	252700	Kab. Supiori
273	250000	251800	Kab. Tolikara
274	250000	252600	Kab. Waropen
275	250000	251600	Kab. Yahukimo
276	250000	253200	Kab. Yalimo
277	250000	256000	Kota Jayapura
278	260000	260300	Kab. Bengkulu Selatan
279	260000	260900	Kab. Bengkulu Tengah
280	260000	260100	Kab. Bengkulu Utara
281	260000	260700	Kab. Kaur
282	260000	260500	Kab. Kepahiang
283	260000	260600	Kab. Lebong
284	260000	260400	Kab. Muko-muko
285	260000	260200	Kab. Rejang Lebong
286	260000	260800	Kab. Seluma
287	260000	266000	Kota Bengkulu
288	270000	270300	Kab. Halmahera Barat
289	270000	270500	Kab. Halmahera Selatan
290	270000	270200	Kab. Halmahera Tengah
291	270000	270600	Kab. Halmahera Timur
292	270000	270400	Kab. halmahera Utara
293	270000	270800	Kab. Kepulauan Morotai
294	270000	270700	Kab. Kepulauan Sula
295	270000	270100	Kab. Pulau Taliabu
296	270000	276000	Kota Ternate
297	270000	276100	Kota Tidore Kepulauan
298	280000	280200	Kab. Lebak
299	280000	280100	Kab. Pandeglang
300	280000	280400	Kab. Serang
301	280000	280300	Kab. Tangerang
302	280000	286000	Kota Cilegon
303	280000	286200	Kota Serang
304	280000	286100	Kota Tangerang
305	280000	286300	Kota Tangerang Selatan
306	290000	290100	Kab. Bangka
307	290000	290400	Kab. Bangka Barat
308	290000	290500	Kab. Bangka Selatan
309	290000	290300	Kab. Bangka Tengah
310	290000	290200	Kab. Belitung
311	290000	290600	Kab. Belitung Timur
312	290000	296000	Kota Pangkalpinang
313	30000	30400	Kab. Banjarnegara
314	30000	30200	Kab. Banyumas
315	30000	32500	Kab. Batang
316	30000	31600	Kab. Blora
317	30000	30900	Kab. Boyolali
318	30000	32900	Kab. Brebes
319	30000	30100	Kab. Cilacap
320	30000	32100	Kab. Demak
321	30000	31500	Kab. Grobogan
322	30000	32000	Kab. Jepara
323	30000	31300	Kab. Karanganyar
324	30000	30500	Kab. Kebumen
325	30000	32400	Kab. Kendal
326	30000	31000	Kab. Klaten
327	30000	31900	Kab. Kudus
328	30000	30800	Kab. Magelang
329	30000	31800	Kab. Pati
330	30000	32600	Kab. Pekalongan
331	30000	32700	Kab. Pemalang
332	30000	30300	Kab. Purbalingga
333	30000	30600	Kab. Purworejo
334	30000	31700	Kab. Rembang
335	30000	32200	Kab. Semarang
336	30000	31400	Kab. Sragen
337	30000	31100	Kab. Sukoharjo
338	30000	32800	Kab. Tegal
339	30000	32300	Kab. Temanggung
340	30000	31200	Kab. Wonogiri
341	30000	30700	Kab. Wonosobo
342	30000	36000	Kota Magelang
343	30000	36400	Kota Pekalongan
344	30000	36200	Kota Salatiga
345	30000	36300	Kota Semarang
346	30000	36100	Kota Surakarta
347	30000	36500	Kota Tegal
348	300000	300100	Kab. Boalemo
349	300000	300400	Kab. Bone Bolango
350	300000	300200	Kab. Gorontalo
351	300000	300500	Kab. Gorontalo Utara
352	300000	300300	Kab. Pohuwato
354	310000	310100	Kab. Bintan
355	310000	310200	Kab. Karimun
356	310000	310500	Kab. Kepulauan Anambas
357	310000	310400	Kab. Lingga
358	310000	310300	Kab. Natuna
359	310000	316000	Kota Batam
360	310000	316100	Kota Tanjungpinang
361	320000	320100	Kab. Fak-Fak
362	320000	320200	Kab. Kaimana
363	320000	320500	Kab. Manokwari
364	320000	321200	Kab. Manokwari Selatan
365	320000	321000	Kab. Maybrat
366	320000	321100	Kab. Pegunungan Arfak
367	320000	320800	Kab. Raja Ampat
368	320000	320700	Kab. Sorong
369	320000	320600	Kab. Sorong Selatan
370	320000	320900	Kab. Tambrauw
371	320000	320400	Kab. Teluk Bintuni
372	320000	320300	Kab. Teluk Wondama
373	320000	326000	Kota Sorong
374	330000	330500	Kab. Majene
375	330000	330400	Kab. Mamasa
376	330000	330100	Kab. Mamuju
377	330000	330600	Kab. Mamuju Tengah
378	330000	330200	Kab. Pasangkayu
379	330000	330300	Kab. Polewali Mandar
380	340000	340200	Kab. Bulungan
381	340000	340100	Kab. Malinau
382	340000	340500	Kab. Nunukan
383	340000	340300	Kab. Tana Tidung
384	340000	346000	Kota Tarakan
385	350000	350800	Arab Saudi
386	350000	350100	Belanda
387	350000	351500	Brunei Darussalam
388	350000	351400	Cina
389	350000	350600	Filipina
390	350000	350200	Japan
391	350000	350400	Malaysia
392	350000	350300	Mesir
393	350000	350500	Myanmar
394	350000	350700	Rusia
395	350000	351000	Singapura
396	350000	351300	Taiwan
397	350000	351200	Thailand
398	40000	40100	Kab. Bantul
399	40000	40300	Kab. Gunung Kidul
400	40000	40400	Kab. Kulon Progo
401	40000	40200	Kab. Sleman
402	40000	46000	Kota Yogyakarta
403	50000	52900	Kab. Bangkalan
404	50000	52500	Kab. Banyuwangi
405	50000	51500	Kab. Blitar
406	50000	50500	Kab. Bojonegoro
407	50000	52200	Kab. Bondowoso
408	50000	50100	Kab. Gresik
409	50000	52400	Kab. Jember
410	50000	50400	Kab. Jombang
411	50000	51300	Kab. Kediri
412	50000	50700	Kab. Lamongan
413	50000	52100	Kab. Lumajang
414	50000	50800	Kab. Madiun
415	50000	51000	Kab. Magetan
416	50000	51800	Kab. Malang
417	50000	50300	Kab. Mojokerto
418	50000	51400	Kab. Nganjuk
419	50000	50900	Kab. Ngawi
420	50000	51200	Kab. Pacitan
421	50000	52600	Kab. Pamekasan
422	50000	51900	Kab. Pasuruan
423	50000	51100	Kab. Ponorogo
424	50000	52000	Kab. Probolinggo
425	50000	52700	Kab. Sampang
426	50000	50200	Kab. Sidoarjo
427	50000	52300	Kab. Situbondo
428	50000	52800	Kab. Sumenep
429	50000	51700	Kab. Trenggalek
430	50000	50600	Kab. Tuban
431	50000	51600	Kab. Tulungagung
432	50000	56800	Kota Batu
433	50000	56500	Kota Blitar
434	50000	56300	Kota Kediri
435	50000	56200	Kota Madiun
436	50000	56100	Kota Malang
437	50000	56400	Kota Mojokerto
438	50000	56600	Kota Pasuruan
439	50000	56700	Kota Probolinggo
440	50000	56000	Kota Surabaya
441	60000	60600	Kab. Aceh Barat
442	60000	61700	Kab. Aceh Barat Daya
443	60000	60100	Kab. Aceh Besar
444	60000	61600	Kab. Aceh Jaya
445	60000	60700	Kab. Aceh Selatan
446	60000	61300	Kab. Aceh Singkil
447	60000	61400	Kab. Aceh Tamiang
448	60000	60500	Kab. Aceh Tengah
449	60000	60800	Kab. Aceh Tenggara
450	60000	60400	Kab. Aceh Timur
451	60000	60300	Kab. Aceh Utara
452	60000	61900	Kab. Bener Meriah
453	60000	61200	Kab. Bireuen
454	60000	61800	Kab. Gayo Lues
455	60000	61500	Kab. Nagan Raya
456	60000	60200	Kab. Pidie
457	60000	62000	Kab. Pidie Jaya
458	60000	61100	Kab. Simeulue
459	60000	66100	Kota Banda Aceh
460	60000	66300	Kota Langsa
461	60000	66200	Kota Lhokseumawe
462	60000	66000	Kota Sabang
463	60000	66400	Kota Subulussalam
464	70000	70600	Kab. Asahan
465	70000	72200	Kab. Batubara
466	70000	70500	Kab. Dairi
467	70000	70100	Kab. Deli Serdang
468	70000	71900	Kab. Humbang Hasudutan
469	70000	70300	Kab. Karo
470	70000	70700	Kab. Labuhan Batu
471	70000	72600	Kab. Labuhan Batu Selatan
472	70000	72500	Kab. Labuhan Batu Utara
473	70000	70200	Kab. Langkat
474	70000	71500	Kab. Mandailing Natal
475	70000	71100	Kab. Nias
476	70000	72700	Kab. Nias Barat
477	70000	71700	Kab. Nias Selatan
478	70000	72800	Kab. Nias Utara
479	70000	72400	Kab. Padang Lawas
480	70000	72300	Kab. Padang Lawas utara
481	70000	71800	Kab. Pakpak Bharat
482	70000	72000	Kab. Samosir
483	70000	72100	Kab. Serdang Bedagai
484	70000	70400	Kab. Simalungun
485	70000	71000	Kab. Tapanuli Selatan
486	70000	70900	Kab. Tapanuli Tengah
487	70000	70800	Kab. Tapanuli Utara
488	70000	71600	Kab. Toba Samosir
489	70000	76100	Kota Binjai
490	70000	76700	Kota Gunungsitoli
491	70000	76000	Kota Medan
492	70000	76600	Kota Padang Sidimpuan
493	70000	76300	Kota Pematangsiantar
494	70000	76500	Kota Sibolga
495	70000	76400	Kota Tanjung Balai
496	70000	76200	Kota Tebing Tinggi
497	80000	80100	Kab. Agam
498	80000	81200	Kab. Dharmasraya
499	80000	81000	Kab. Kepulauan Mentawai
500	80000	80300	Kab. Lima Puluh Koto
501	80000	80500	Kab. Padang Pariaman
502	80000	80200	Kab. Pasaman
503	80000	81300	Kab. Pasaman Barat
504	80000	80600	Kab. Pesisir Selatan
505	80000	80800	Kab. Sijunjung
506	80000	80400	Kab. Solok
507	80000	81100	Kab. Solok Selatan
508	80000	80700	Kab. Tanah Datar
509	80000	86000	Kota Bukittinggi
510	80000	86100	Kota Padang
511	80000	86200	Kota Padang Panjang
512	80000	86600	Kota Pariaman
513	80000	86500	Kota Payakumbuh
514	80000	86300	Kota Sawah Lunto
515	80000	86400	Kota Solok
516	90000	90200	Kab. Bengkalis
517	90000	90500	Kab. Indragiri Hilir
518	90000	90400	Kab. Indragiri Hulu
519	90000	90100	Kab. Kampar
520	90000	91500	Kab. Kepulauan Meranti
521	90000	91400	Kab. Kuantan Singingi
522	90000	90800	Kab. Pelalawan
523	90000	91000	Kab. Rokan Hilir
524	90000	90900	Kab. Rokan Hulu
525	90000	91100	Kab. Siak
526	90000	96200	Kota Dumai
527	90000	96000	Kota Pekanbaru
528	999999	999999	Luar Negeri
\.



SELECT pg_catalog.setval('tracer_oltp.cities_id_seq', 528, true);









COPY tracer_oltp.programs (id, name, code, degree, is_active, created_at, updated_at, jurusan, dikti_code) FROM stdin;
1	D-3 Teknik Konstruksi Gedung	TKG	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Sipil	22403
2	D-3 Teknik Konstruksi Sipil	TKS	D3	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Teknik Sipil	22402
3	D-4 Teknik Perancangan Jalan & Jembatan	TPJJ	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Sipil	22301
4	D-4 Teknik Perawatan & Perbaikan Gedung	TPPG	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Sipil	22303
5	D-3 Teknik Mesin	TM	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Mesin	21401
6	D-4 Teknik Perancangan & Konstruksi Mesin	TPKM	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Mesin	36301
7	D-4 Proses Manufaktur	PM	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Mesin	21307
36	D-3 Teknik Aeronautika	TA	D3	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Teknik Mesin	40402
8	D-3 Teknik Pendingin & Tata Udara	TPTU3	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Refrigerasi & Tata Udara	21405
9	D-4 Teknik Pendingin & Tata Udara	TPTU4	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Refrigerasi & Tata Udara	21305
10	D-3 Teknik Konversi Energi	TKE3	D3	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Teknik Konversi Energi	21406
11	D-4 Teknik Konservasi Energi	TKE4	D4	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Teknik Konversi Energi	21308
18	D-4 Teknologi Pembangkit Tenaga Listrik	TPTL	D4	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Teknik Konversi Energi	20303
12	D-3 Teknik Elektronika	TEL3	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Elektro	20401
13	D-4 Teknik Elektronika	TEL4	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Elektro	20301
14	D-3 Teknik Listrik	TL	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Elektro	20403
15	D-3 Teknik Telekomunikasi	TELKOM3	D3	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Teknik Elektro	20402
16	D-4 Teknik Telekomunikasi	TELKOM4	D4	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Teknik Elektro	20304
17	D-4 Teknik Otomasi Industri	TOI	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Elektro	36304
19	D-3 Teknik Kimia	TK3	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Kimia	24401
20	D-3 Analis Kimia	AK3	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Kimia	24402
21	D-4 Teknik Kimia Produksi Bersih	TKPB	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Kimia	24301
22	D-3 Teknik Informatika	TI3	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Komputer & Informatika	56401
23	D-4 Teknik Informatika	TI	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Teknik Komputer & Informatika	55301
24	D-3 Akuntansi	AKT3	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Akuntansi	62401
25	D-3 Keuangan & Perbankan	KP	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Akuntansi	61406
26	D-4 Akuntansi	AKT4	D4	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Akuntansi	62306
27	D-4 Akuntansi Manajemen Pemerintahan	AMP	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Akuntansi	62301
28	D-4 Keuangan Syariah	KS	D4	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Akuntansi	61306
37	S-2 Keuangan & Perbankan Syariah	KPS2	S2	t	2026-06-04 04:34:56	2026-06-04 04:34:56	Akuntansi	60104
38	S-2 Rekayasa Infrastruktur	RIS2	S2	t	2026-06-04 04:34:56	2026-06-04 04:34:56	Teknik Sipil	31104
29	D-4 Manajemen Aset	MA	D4	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Administrasi Niaga	61307
30	D-3 Administrasi Bisnis	AB3	D3	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Administrasi Niaga	63411
31	D-3 Manajemen Pemasaran	MP3	D3	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Administrasi Niaga	61404
32	D-3 Usaha Perjalanan Wisata	UPW	D3	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Administrasi Niaga	93401
33	D-4 Administrasi Bisnis	AB4	D4	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Administrasi Niaga	63311
34	D-4 Manajemen Pemasaran	MP4	D4	t	2026-06-01 10:16:25	2026-06-01 10:16:25	Administrasi Niaga	61304
35	D-3 Bahasa Inggris	BIG	D3	t	2026-06-01 10:16:25	2026-06-09 22:25:39	Bahasa Inggris	79402
\.



SELECT pg_catalog.setval('tracer_oltp.programs_id_seq', 42, true);









COPY tracer_oltp.jurusans (id, name, is_active, created_at, updated_at) FROM stdin;
1	Administrasi Niaga	t	2026-08-11 16:39:40	2026-08-11 16:39:40
2	Akuntansi	t	2026-08-11 16:39:40	2026-08-11 16:39:40
3	Bahasa Inggris	t	2026-08-11 16:39:40	2026-08-11 16:39:40
4	Teknik Elektro	t	2026-08-11 16:39:40	2026-08-11 16:39:40
5	Teknik Kimia	t	2026-08-11 16:39:40	2026-08-11 16:39:40
6	Teknik Komputer & Informatika	t	2026-08-11 16:39:40	2026-08-11 16:39:40
7	Teknik Konversi Energi	t	2026-08-11 16:39:40	2026-08-11 16:39:40
8	Teknik Mesin	t	2026-08-11 16:39:40	2026-08-11 16:39:40
9	Teknik Refrigerasi & Tata Udara	t	2026-08-11 16:39:40	2026-08-11 16:39:40
10	Teknik Sipil	t	2026-08-11 16:39:40	2026-08-11 16:39:40
\.



SELECT pg_catalog.setval('tracer_oltp.jurusans_id_seq', 10, true);









COPY tracer_oltp.users (id, name, email, password, role, program_id, created_at, updated_at, jurusan, status) FROM stdin;
3	Tim Tracer 2	tracer2@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	tracer_team	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	\N	t
4	Wakil Direktur 1	wakil.direktur.1@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	wadir	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	\N	t
5	Wakil Direktur 2	wakil.direktur.2@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	wadir	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	\N	t
6	Kajur Teknik Elektro	kajur.teknik.elektro@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Elektro	t
7	Kajur Teknik Refrigerasi & Tata Udara	kajur.teknik.refrigerasi.tata.udara@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Refrigerasi & Tata Udara	t
8	Kajur Teknik Komputer & Informatika	kajur.teknik.komputer.informatika@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Komputer & Informatika	t
9	Kajur Administrasi Niaga	kajur.administrasi.niaga@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Administrasi Niaga	t
10	Kajur Teknik Mesin	kajur.teknik.mesin@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Mesin	t
11	Kajur Bahasa Inggris	kajur.bahasa.inggris@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Bahasa Inggris	t
12	Kajur Teknik Kimia	kajur.teknik.kimia@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Kimia	t
13	Kajur Teknik Konversi Energi	kajur.teknik.konversi.energi@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Konversi Energi	t
14	Kajur Teknik Sipil	kajur.teknik.sipil@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Sipil	t
15	Kajur Teknik Aeronautika	kajur.teknik.aeronautika@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Teknik Aeronautika	t
16	Kajur Akuntansi	kajur.akuntansi@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kajur	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	Akuntansi	t
55	Kaprodi D-3 Teknik Konstruksi Gedung	kaprodi.d3.teknik.konstruksi.gedung@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	1	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
56	Kaprodi D-3 Teknik Konstruksi Sipil	kaprodi.d3.teknik.konstruksi.sipil@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	2	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
57	Kaprodi D-4 Teknik Perancangan Jalan & Jembatan	kaprodi.d4.teknik.perancangan.jalan.dan.jembatan@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	3	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
58	Kaprodi D-4 Teknik Perawatan & Perbaikan Gedung	kaprodi.d4.teknik.perawatan.dan.perbaikan.gedung@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	4	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
59	Kaprodi D-3 Teknik Mesin	kaprodi.d3.teknik.mesin@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	5	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
60	Kaprodi D-4 Teknik Perancangan & Konstruksi Mesin	kaprodi.d4.teknik.perancangan.dan.konstruksi.mesin@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	6	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
61	Kaprodi D-4 Proses Manufaktur	kaprodi.d4.proses.manufaktur@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	7	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
62	Kaprodi D-3 Teknik Pendingin & Tata Udara	kaprodi.d3.teknik.pendingin.dan.tata.udara@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	8	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
63	Kaprodi D-4 Teknik Pendingin & Tata Udara	kaprodi.d4.teknik.pendingin.dan.tata.udara@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	9	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
64	Kaprodi D-3 Teknik Konversi Energi	kaprodi.d3.teknik.konversi.energi@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	10	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
65	Kaprodi D-4 Teknik Konservasi Energi	kaprodi.d4.teknik.konservasi.energi@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	11	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
66	Kaprodi D-3 Teknik Elektronika	kaprodi.d3.teknik.elektronika@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	12	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
67	Kaprodi D-4 Teknik Elektronika	kaprodi.d4.teknik.elektronika@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	13	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
68	Kaprodi D-3 Teknik Listrik	kaprodi.d3.teknik.listrik@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	14	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
69	Kaprodi D-3 Teknik Telekomunikasi	kaprodi.d3.teknik.telekomunikasi@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	15	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
70	Kaprodi D-4 Teknik Telekomunikasi	kaprodi.d4.teknik.telekomunikasi@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	16	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
71	Kaprodi D-4 Teknik Otomasi Industri	kaprodi.d4.teknik.otomasi.industri@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	17	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
72	Kaprodi D-4 Teknologi Pembangkit Tenaga Listrik	kaprodi.d4.teknologi.pembangkit.tenaga.listrik@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	18	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
73	Kaprodi D-3 Teknik Kimia	kaprodi.d3.teknik.kimia@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	19	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
74	Kaprodi D-3 Analis Kimia	kaprodi.d3.analis.kimia@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	20	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
75	Kaprodi D-4 Teknik Kimia Produksi Bersih	kaprodi.d4.teknik.kimia.produksi.bersih@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	21	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
76	Kaprodi D-3 Teknik Informatika	kaprodi.d3.teknik.informatika@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	22	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
77	Kaprodi D-4 Teknik Informatika	kaprodi.d4.teknik.informatika@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	23	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
78	Kaprodi D-3 Akuntansi	kaprodi.d3.akuntansi@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	24	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
79	Kaprodi D-3 Keuangan & Perbankan	kaprodi.d3.keuangan.dan.perbankan@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	25	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
80	Kaprodi D-4 Akuntansi	kaprodi.d4.akuntansi@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	26	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
81	Kaprodi D-4 Akuntansi Manajemen Pemerintahan	kaprodi.d4.akuntansi.manajemen.pemerintahan@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	27	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
82	Kaprodi D-4 Keuangan Syariah	kaprodi.d4.keuangan.syariah@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	28	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
83	Kaprodi D-4 Manajemen Aset	kaprodi.d4.manajemen.aset@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	29	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
84	Kaprodi D-3 Administrasi Bisnis	kaprodi.d3.administrasi.bisnis@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	30	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
85	Kaprodi D-3 Manajemen Pemasaran	kaprodi.d3.manajemen.pemasaran@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	31	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
86	Kaprodi D-3 Usaha Perjalanan Wisata	kaprodi.d3.usaha.perjalanan.wisata@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	32	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
87	Kaprodi D-4 Administrasi Bisnis	kaprodi.d4.administrasi.bisnis@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	33	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
88	Kaprodi D-4 Manajemen Pemasaran	kaprodi.d4.manajemen.pemasaran@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	34	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
89	Kaprodi D-3 Bahasa Inggris	kaprodi.d3.bahasa.inggris@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	35	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
90	Kaprodi D-3 Teknik Aeronautika	kaprodi.d3.teknik.aeronautika@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	36	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
91	Kaprodi S-2 Keuangan & Perbankan Syariah	kaprodi.s2.keuangan.dan.perbankan.syariah@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	37	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
92	Kaprodi S-2 Rekayasa Infrastruktur	kaprodi.s2.rekayasa.infrastruktur@polban.ac.id	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	kaprodi	38	2026-06-17 22:20:08	2026-06-17 22:20:08	\N	t
1	Kepala Tracer Study	head.tracer@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	head_tracer	\N	2026-06-01 10:16:26	2026-08-12 09:32:00	\N	t
2	SPMI	spmi@test.com	$2y$12$/4v8rj/8Nkjxr7InfTyCHu1SaCnmAE6oEgTbA97lcMfel7DYRe5sm	wadir	\N	2026-06-01 10:16:26	2026-08-12 09:32:00	\N	t
\.



SELECT pg_catalog.setval('tracer_oltp.users_id_seq', 92, true);









COPY tracer_oltp.permissions (id, name, description, created_at, updated_at) FROM stdin;
1	user.manage	CRUD semua user	2026-06-01 10:16:26	2026-06-01 10:16:26
2	user.view	Lihat daftar user	2026-06-01 10:16:26	2026-06-01 10:16:26
3	questionnaire.manage	CRUD kuesioner langsung	2026-06-01 10:16:26	2026-06-01 10:16:26
4	questionnaire.edit	Edit kuesioner	2026-06-01 10:16:26	2026-06-01 10:16:26
5	questionnaire.request	Ajukan tambah/hapus kuesioner (perlu approval)	2026-06-01 10:16:26	2026-06-01 10:16:26
6	questionnaire.toggle	Aktifkan/nonaktifkan kuesioner	2026-06-01 10:16:26	2026-06-01 10:16:26
7	questionnaire.status	Update status pengisian	2026-06-01 10:16:26	2026-06-01 10:16:26
8	approval.manage	Approve/reject request dari admin	2026-06-01 10:16:26	2026-06-01 10:16:26
9	master.prodi	Kelola kode prodi	2026-06-01 10:16:26	2026-06-01 10:16:26
10	master.region	Kelola provinsi & kota	2026-06-01 10:16:26	2026-06-01 10:16:26
11	report.status	Kelola status laporan tracer study	2026-06-01 10:16:26	2026-06-01 10:16:26
12	data.view_all	Viewer semua data institusi	2026-06-01 10:16:26	2026-06-01 10:16:26
13	data.view_jurusan	Viewer data tingkat jurusan	2026-06-01 10:16:26	2026-06-01 10:16:26
14	data.view_prodi	Viewer data prodi sendiri	2026-06-01 10:16:26	2026-06-01 10:16:26
15	data.download	Download data	2026-06-01 10:16:26	2026-06-01 10:16:26
\.



SELECT pg_catalog.setval('tracer_oltp.permissions_id_seq', 15, true);









COPY tracer_oltp.role_permissions (id, role, permission_id) FROM stdin;
1	head_tracer	1
2	head_tracer	2
3	head_tracer	3
4	head_tracer	4
5	head_tracer	6
6	head_tracer	7
7	head_tracer	8
8	head_tracer	9
9	head_tracer	10
10	head_tracer	11
11	head_tracer	12
12	head_tracer	15
13	tracer_team	2
14	tracer_team	4
15	tracer_team	5
16	tracer_team	7
17	wadir	12
18	wadir	15
19	kajur	13
20	kajur	15
21	kaprodi	14
22	kaprodi	15
\.



SELECT pg_catalog.setval('tracer_oltp.role_permissions_id_seq', 22, true);









COPY tracer_oltp.lams (id, name, code, created_at, updated_at) FROM stdin;
3	LAM EMBA	EMBA	2026-05-13 22:15:12	2026-07-14 04:38:14
\.



SELECT pg_catalog.setval('tracer_oltp.lams_id_seq', 2, true);









COPY tracer_oltp.lam_versions (id, lam_id, year, version_name, is_active, created_at, updated_at) FROM stdin;
8	3	2021	2021	t	2026-07-11 03:13:19	2026-07-11 03:13:19
7	3	2020	\N	t	2026-06-21 18:44:23	2026-07-12 14:20:25
\.



SELECT pg_catalog.setval('tracer_oltp.lam_versions_id_seq', 8, true);









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









COPY tracer_oltp.threshold_indicators (id, key, name, unit, operator, description, dynamic_param_unit, is_system_calculated) FROM stdin;
2	entrepreneurship	Lulusan Berwirausaha	%	>=	Persentase lulusan yang berwirausaha	\N	f
4	field_relevance	Kesesuaian Bidang Kerja	%	>=	Persentase lulusan yang bekerja sesuai bidang studi	\N	f
6	graduate_absorption	Keterserapan Alumni	%	>=	Persentase lulusan yang bekerja, berwirausaha, atau melanjutkan studi	\N	f
1	employment_time	Lulusan Bekerja ≤ {value} Bulan	%	>=	Persentase lulusan yang mendapat pekerjaan dalam 6 bulan setelah lulus	bulan	f
5	salary_above_ump	Penghasilan ≥ {value}x UMP/UMK	%	>=	Persentase lulusan dengan penghasilan di atas UMP/UMK	x_ump	f
3	tracer_response	Respon Tracer Study Alumni	%	>=	Tingkat respon alumni terhadap tracer study	\N	t
\.



SELECT pg_catalog.setval('tracer_oltp.threshold_indicators_id_seq', 1, true);









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









COPY tracer_oltp.threshold_configs (id, lam_version_id, indicator_id, param_value, created_at, updated_at) FROM stdin;
3	8	1	2.00	2026-07-14 04:36:41	2026-07-14 04:36:41
4	8	5	1.40	2026-07-14 04:36:41	2026-07-14 04:36:41
1	7	1	3.00	2026-07-14 05:56:36	2026-07-14 05:56:36
2	7	5	1.20	2026-07-14 05:56:36	2026-07-14 05:56:36
\.



SELECT pg_catalog.setval('tracer_oltp.threshold_configs_id_seq', 4, true);









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



SELECT pg_catalog.setval('tracer_oltp.ref_ump_id_seq', 581, true);









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
bulan_sesudah_lulus	Bulan Sesudah Lulus Mulai Cari Kerja	waktu_tunggu	Jumlah bulan sesudah lulus alumni mulai mencari kerja	integer	0.00	60.00	2	fact_tracer_study	bulan_sesudah_lulus	narrow	t	2026-08-11 16:39:40	2026-08-11 16:39:40
\.









COPY tracer_oltp.questionnaires (id, code, title, period_year, version, status, published_at, created_by, created_at, updated_at, program_id, description, target, sample_respondents, target_graduation_years) FROM stdin;
1	DIKTI_2026_v1	Kuesioner Tracer Study Nasional 2026 — Lulusan 2022	2026	1	published	2026-06-01 10:16:26	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	\N	Kuesioner wajib dari Kementerian Pendidikan untuk seluruh lulusan perguruan tinggi.	\N	\N	[2022]
2	DIKTI_2026_v2	Kuesioner Tracer Study Nasional 2026 — Lulusan 2023	2026	2	published	2026-06-01 10:16:26	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	\N	Kuesioner wajib dari Kementerian Pendidikan untuk seluruh lulusan perguruan tinggi.	\N	\N	[2023]
3	DIKTI_2026_v3	Kuesioner Tracer Study Nasional 2026 — Lulusan 2024	2026	3	published	2026-06-01 10:16:26	\N	2026-06-01 10:16:26	2026-06-01 10:16:26	\N	Kuesioner wajib dari Kementerian Pendidikan untuk seluruh lulusan perguruan tinggi.	\N	\N	[2024]
\.



SELECT pg_catalog.setval('tracer_oltp.questionnaires_id_seq', 111, true);









COPY tracer_oltp.questionnaire_sections (id, questionnaire_id, title, description, order_no, is_active, created_at, updated_at) FROM stdin;
1	1	Identitas	\N	0	t	2026-06-01 10:16:26	2026-06-01 10:16:26
2	1	Status & Pekerjaan	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26
3	1	Studi Lanjut	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26
4	1	Sumber Dana Kuliah	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26
5	1	Kesesuaian Pekerjaan & Pendidikan	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26
6	1	Kompetensi	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26
7	1	Metode Pembelajaran	\N	6	t	2026-06-01 10:16:26	2026-06-01 10:16:26
8	1	Pencarian Kerja	\N	7	t	2026-06-01 10:16:26	2026-06-01 10:16:26
9	1	Statistik Lamaran	\N	8	t	2026-06-01 10:16:26	2026-06-01 10:16:26
10	1	Aktivitas & Alasan	\N	9	t	2026-06-01 10:16:26	2026-06-01 10:16:26
11	2	Identitas	\N	0	t	2026-06-01 10:16:26	2026-06-01 10:16:26
12	2	Status & Pekerjaan	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26
13	2	Studi Lanjut	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26
14	2	Sumber Dana Kuliah	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26
15	2	Kesesuaian Pekerjaan & Pendidikan	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26
16	2	Kompetensi	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26
17	2	Metode Pembelajaran	\N	6	t	2026-06-01 10:16:26	2026-06-01 10:16:26
18	2	Pencarian Kerja	\N	7	t	2026-06-01 10:16:26	2026-06-01 10:16:26
19	2	Statistik Lamaran	\N	8	t	2026-06-01 10:16:26	2026-06-01 10:16:26
20	2	Aktivitas & Alasan	\N	9	t	2026-06-01 10:16:26	2026-06-01 10:16:26
21	3	Identitas	\N	0	t	2026-06-01 10:16:26	2026-06-01 10:16:26
22	3	Status & Pekerjaan	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26
23	3	Studi Lanjut	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26
24	3	Sumber Dana Kuliah	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26
25	3	Kesesuaian Pekerjaan & Pendidikan	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26
26	3	Kompetensi	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26
27	3	Metode Pembelajaran	\N	6	t	2026-06-01 10:16:26	2026-06-01 10:16:26
28	3	Pencarian Kerja	\N	7	t	2026-06-01 10:16:26	2026-06-01 10:16:26
29	3	Statistik Lamaran	\N	8	t	2026-06-01 10:16:26	2026-06-01 10:16:26
30	3	Aktivitas & Alasan	\N	9	t	2026-06-01 10:16:26	2026-06-01 10:16:26
31	1	Kontak Penilai	Tracer study juga meminta penilaian dari orang-orang yang mengenal kinerja Anda. Tuliskan tiga nama beserta alamat surel yang bisa kami hubungi. Mereka hanya akan menerima satu kuesioner singkat mengenai kompetensi Anda.	10	t	2026-08-11 16:39:40	2026-08-11 16:39:40
32	2	Kontak Penilai	Tracer study juga meminta penilaian dari orang-orang yang mengenal kinerja Anda. Tuliskan tiga nama beserta alamat surel yang bisa kami hubungi. Mereka hanya akan menerima satu kuesioner singkat mengenai kompetensi Anda.	10	t	2026-08-11 16:39:40	2026-08-11 16:39:40
33	3	Kontak Penilai	Tracer study juga meminta penilaian dari orang-orang yang mengenal kinerja Anda. Tuliskan tiga nama beserta alamat surel yang bisa kami hubungi. Mereka hanya akan menerima satu kuesioner singkat mengenai kompetensi Anda.	10	t	2026-08-11 16:39:40	2026-08-11 16:39:40
\.



SELECT pg_catalog.setval('tracer_oltp.questionnaire_sections_id_seq', 33, true);









COPY tracer_oltp.questionnaire_questions (id, questionnaire_id, section_id, code, question_text, question_type, is_required, order_no, metadata, created_at, updated_at) FROM stdin;
1	1	1	nimhsmsmh	NIM	short_text	t	1	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
2	1	1	kdptimsmh	Kode PT	short_text	t	2	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
3	1	1	tahun_lulus	Tahun Lulus	short_text	t	3	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
5	1	1	nmmhsmsmh	Nama	short_text	t	5	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
6	1	1	telpomsmh	Nomor Telepon/HP	short_text	t	6	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
7	1	1	emailmsmh	Alamat Email	short_text	t	7	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
8	1	1	nik	NIK	short_text	t	8	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
9	1	1	npwp	NPWP	short_text	f	9	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
10	1	2	f8	Jelaskan status Anda saat ini?	single_choice	t	10	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
11	1	2	f502	Dalam berapa bulan Anda mendapatkan pekerjaan pertama? / Dalam berapa bulan setelah lulus Anda memulai wiraswasta?	number	f	11	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
12	1	2	f505	Berapa rata-rata pendapatan Anda per bulan? (take home pay)	number	f	12	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
15	1	2	f1101	Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?	single_choice	f	15	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
16	1	2	f1102	Sebutkan jenis perusahaan/instansi lainnya	short_text	f	16	{"show_if":{"f1101":[5]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
17	1	2	f5b	Apa nama perusahaan/kantor tempat Anda bekerja?	short_text	f	17	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
19	1	2	f5d	Apa tingkat tempat kerja Anda?	single_choice	f	19	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
20	1	3	f18a	Sumber biaya untuk studi lanjut?	single_choice	f	20	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
21	1	3	f18b	Perguruan Tinggi tempat studi lanjut?	short_text	f	21	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
22	1	3	f18c	Program Studi studi lanjut?	short_text	f	22	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
23	1	3	f18d	Tanggal Masuk studi lanjut? (dd/mm/yyyy)	date	f	23	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
24	1	4	f1201	Sebutkan sumber dana dalam pembiayaan kuliah Anda? (bukan ketika Studi Lanjut)	single_choice	t	24	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
25	1	4	f1202	Sebutkan sumber dana pembiayaan kuliah lainnya	short_text	f	25	{"show_if":{"f1201":[7]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
26	1	5	f14	Seberapa erat hubungan bidang studi dengan pekerjaan Anda?	single_choice	f	26	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
27	1	5	f15	Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?	single_choice	f	27	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
28	1	6	f1761	Pada saat lulus, pada tingkat mana kompetensi Etika Anda kuasai? (A)	number	t	28	{"scale_min":1,"scale_max":5,"competency":"Etika","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
29	1	6	f1762	Pada saat ini, pada tingkat mana kompetensi Etika diperlukan dalam pekerjaan? (B)	number	t	29	{"scale_min":1,"scale_max":5,"competency":"Etika","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
30	1	6	f1763	Pada saat lulus, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu Anda kuasai? (A)	number	t	30	{"scale_min":1,"scale_max":5,"competency":"Keahlian berdasarkan bidang ilmu","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
31	1	6	f1764	Pada saat ini, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu diperlukan dalam pekerjaan? (B)	number	t	31	{"scale_min":1,"scale_max":5,"competency":"Keahlian berdasarkan bidang ilmu","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
32	1	6	f1765	Pada saat lulus, pada tingkat mana kompetensi Bahasa Inggris Anda kuasai? (A)	number	t	32	{"scale_min":1,"scale_max":5,"competency":"Bahasa Inggris","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
33	1	6	f1766	Pada saat ini, pada tingkat mana kompetensi Bahasa Inggris diperlukan dalam pekerjaan? (B)	number	t	33	{"scale_min":1,"scale_max":5,"competency":"Bahasa Inggris","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
34	1	6	f1767	Pada saat lulus, pada tingkat mana kompetensi Penggunaan Teknologi Informasi Anda kuasai? (A)	number	t	34	{"scale_min":1,"scale_max":5,"competency":"Penggunaan Teknologi Informasi","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
35	1	6	f1768	Pada saat ini, pada tingkat mana kompetensi Penggunaan Teknologi Informasi diperlukan dalam pekerjaan? (B)	number	t	35	{"scale_min":1,"scale_max":5,"competency":"Penggunaan Teknologi Informasi","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
36	1	6	f1769	Pada saat lulus, pada tingkat mana kompetensi Komunikasi Anda kuasai? (A)	number	t	36	{"scale_min":1,"scale_max":5,"competency":"Komunikasi","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
37	1	6	f1770	Pada saat ini, pada tingkat mana kompetensi Komunikasi diperlukan dalam pekerjaan? (B)	number	t	37	{"scale_min":1,"scale_max":5,"competency":"Komunikasi","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
38	1	6	f1771	Pada saat lulus, pada tingkat mana kompetensi Kerja sama tim Anda kuasai? (A)	number	t	38	{"scale_min":1,"scale_max":5,"competency":"Kerja sama tim","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
66	1	8	f415	Bagaimana Anda mencari pekerjaan tersebut? — Lainnya	boolean	f	66	{"group_code":"q16_cara_cari_kerja","group_label":"Lainnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
68	1	9	f6	Berapa perusahaan/instansi yang sudah Anda lamar (lewat surat atau e-mail) sebelum Anda memperoleh pekerjaan pertama?	number	f	68	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
39	1	6	f1772	Pada saat ini, pada tingkat mana kompetensi Kerja sama tim diperlukan dalam pekerjaan? (B)	number	t	39	{"scale_min":1,"scale_max":5,"competency":"Kerja sama tim","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
40	1	6	f1773	Pada saat lulus, pada tingkat mana kompetensi Pengembangan diri Anda kuasai? (A)	number	t	40	{"scale_min":1,"scale_max":5,"competency":"Pengembangan diri","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
41	1	6	f1774	Pada saat ini, pada tingkat mana kompetensi Pengembangan diri diperlukan dalam pekerjaan? (B)	number	t	41	{"scale_min":1,"scale_max":5,"competency":"Pengembangan diri","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
42	1	7	f21	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Perkuliahan" di program studi Anda?	number	t	42	{"scale_min":1,"scale_max":5,"method":"Perkuliahan","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
43	1	7	f22	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Demonstrasi" di program studi Anda?	number	t	43	{"scale_min":1,"scale_max":5,"method":"Demonstrasi","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
44	1	7	f23	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Partisipasi dalam proyek riset" di program studi Anda?	number	t	44	{"scale_min":1,"scale_max":5,"method":"Partisipasi dalam proyek riset","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
45	1	7	f24	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Magang" di program studi Anda?	number	t	45	{"scale_min":1,"scale_max":5,"method":"Magang","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
46	1	7	f25	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Praktikum" di program studi Anda?	number	t	46	{"scale_min":1,"scale_max":5,"method":"Praktikum","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
47	1	7	f26	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Kerja Lapangan" di program studi Anda?	number	t	47	{"scale_min":1,"scale_max":5,"method":"Kerja Lapangan","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
48	1	7	f27	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Diskusi" di program studi Anda?	number	t	48	{"scale_min":1,"scale_max":5,"method":"Diskusi","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
49	1	8	f301	Kapan Anda mulai mencari pekerjaan? Mohon pekerjaan sambilan tidak dimasukkan.	single_choice	t	49	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
50	1	8	f302	Kira-kira berapa bulan sebelum lulus Anda mulai mencari pekerjaan?	number	f	50	{"show_if":{"f301":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
51	1	8	f303	Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?	number	f	51	{"show_if":{"f301":[2]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
52	1	8	f401	Bagaimana Anda mencari pekerjaan tersebut? — Melalui iklan di koran/majalah, brosur	boolean	f	52	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui iklan di koran\\/majalah, brosur","group_title":"Bagaimana Anda mencari pekerjaan tersebut? Jawaban bisa lebih dari satu."}	2026-06-01 10:16:26	2026-06-01 10:16:26
53	1	8	f402	Bagaimana Anda mencari pekerjaan tersebut? — Melamar ke perusahaan tanpa mengetahui lowongan yang ada	boolean	f	53	{"group_code":"q16_cara_cari_kerja","group_label":"Melamar ke perusahaan tanpa mengetahui lowongan yang ada"}	2026-06-01 10:16:26	2026-06-01 10:16:26
54	1	8	f403	Bagaimana Anda mencari pekerjaan tersebut? — Pergi ke bursa/pameran kerja	boolean	f	54	{"group_code":"q16_cara_cari_kerja","group_label":"Pergi ke bursa\\/pameran kerja"}	2026-06-01 10:16:26	2026-06-01 10:16:26
55	1	8	f404	Bagaimana Anda mencari pekerjaan tersebut? — Mencari lewat internet/iklan online/milis	boolean	f	55	{"group_code":"q16_cara_cari_kerja","group_label":"Mencari lewat internet\\/iklan online\\/milis"}	2026-06-01 10:16:26	2026-06-01 10:16:26
56	1	8	f405	Bagaimana Anda mencari pekerjaan tersebut? — Dihubungi oleh perusahaan	boolean	f	56	{"group_code":"q16_cara_cari_kerja","group_label":"Dihubungi oleh perusahaan"}	2026-06-01 10:16:26	2026-06-01 10:16:26
57	1	8	f406	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi Kemenakertrans	boolean	f	57	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi Kemenakertrans"}	2026-06-01 10:16:26	2026-06-01 10:16:26
58	1	8	f407	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi agen tenaga kerja komersial/swasta	boolean	f	58	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi agen tenaga kerja komersial\\/swasta"}	2026-06-01 10:16:26	2026-06-01 10:16:26
59	1	8	f408	Bagaimana Anda mencari pekerjaan tersebut? — Memperoleh informasi dari pusat/kantor pengembangan karir fakultas/universitas	boolean	f	59	{"group_code":"q16_cara_cari_kerja","group_label":"Memperoleh informasi dari pusat\\/kantor pengembangan karir fakultas\\/universitas"}	2026-06-01 10:16:26	2026-06-01 10:16:26
60	1	8	f409	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi kantor kemahasiswaan/hubungan alumni	boolean	f	60	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi kantor kemahasiswaan\\/hubungan alumni"}	2026-06-01 10:16:26	2026-06-01 10:16:26
61	1	8	f410	Bagaimana Anda mencari pekerjaan tersebut? — Membangun jejaring (network) sejak masih kuliah	boolean	f	61	{"group_code":"q16_cara_cari_kerja","group_label":"Membangun jejaring (network) sejak masih kuliah"}	2026-06-01 10:16:26	2026-06-01 10:16:26
62	1	8	f411	Bagaimana Anda mencari pekerjaan tersebut? — Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll.)	boolean	f	62	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll.)"}	2026-06-01 10:16:26	2026-06-01 10:16:26
63	1	8	f412	Bagaimana Anda mencari pekerjaan tersebut? — Membangun bisnis sendiri	boolean	f	63	{"group_code":"q16_cara_cari_kerja","group_label":"Membangun bisnis sendiri"}	2026-06-01 10:16:26	2026-06-01 10:16:26
64	1	8	f413	Bagaimana Anda mencari pekerjaan tersebut? — Melalui penempatan kerja atau magang	boolean	f	64	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui penempatan kerja atau magang"}	2026-06-01 10:16:26	2026-06-01 10:16:26
65	1	8	f414	Bagaimana Anda mencari pekerjaan tersebut? — Bekerja di tempat yang sama dengan tempat kerja semasa kuliah	boolean	f	65	{"group_code":"q16_cara_cari_kerja","group_label":"Bekerja di tempat yang sama dengan tempat kerja semasa kuliah"}	2026-06-01 10:16:26	2026-06-01 10:16:26
67	1	8	f416	Sebutkan cara lainnya dalam mencari pekerjaan	short_text	f	67	{"show_if":{"f415":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
69	1	9	f7	Berapa banyak perusahaan/instansi yang merespons lamaran Anda?	number	f	69	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
70	1	9	f7a	Berapa banyak perusahaan/instansi yang mengundang Anda untuk wawancara?	number	f	70	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
71	1	10	f1001	Apakah Anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilih satu jawaban.	single_choice	t	71	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
72	1	10	f1002	Sebutkan aktivitas lainnya dalam mencari pekerjaan	short_text	f	72	{"show_if":{"f1001":[5]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
73	1	10	f1601	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya	boolean	f	73	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya","group_title":"Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? Jawaban bisa lebih dari satu."}	2026-06-01 10:16:26	2026-06-01 10:16:26
74	1	10	f1602	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya belum mendapatkan pekerjaan yang lebih sesuai	boolean	f	74	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya belum mendapatkan pekerjaan yang lebih sesuai"}	2026-06-01 10:16:26	2026-06-01 10:16:26
75	1	10	f1603	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Di pekerjaan ini saya memperoleh prospek karir yang baik	boolean	f	75	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Di pekerjaan ini saya memperoleh prospek karir yang baik"}	2026-06-01 10:16:26	2026-06-01 10:16:26
76	1	10	f1604	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya	boolean	f	76	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
77	1	10	f1605	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya	boolean	f	77	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
78	1	10	f1606	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	boolean	f	78	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini"}	2026-06-01 10:16:26	2026-06-01 10:16:26
79	1	10	f1607	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih aman/terjamin/secure	boolean	f	79	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih aman\\/terjamin\\/secure"}	2026-06-01 10:16:26	2026-06-01 10:16:26
80	1	10	f1608	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih menarik	boolean	f	80	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih menarik"}	2026-06-01 10:16:26	2026-06-01 10:16:26
81	1	10	f1609	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.	boolean	f	81	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan\\/jadwal yang fleksibel, dll."}	2026-06-01 10:16:26	2026-06-01 10:16:26
82	1	10	f1610	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya	boolean	f	82	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
83	1	10	f1611	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya	boolean	f	83	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
84	1	10	f1612	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya	boolean	f	84	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
85	1	10	f1613	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Lainnya	boolean	f	85	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Lainnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
86	1	10	f1614	Sebutkan alasan lainnya mengambil pekerjaan yang tidak sesuai pendidikan	short_text	f	86	{"show_if":{"f1613":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
87	2	11	nimhsmsmh	NIM	short_text	t	1	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
88	2	11	kdptimsmh	Kode PT	short_text	t	2	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
89	2	11	tahun_lulus	Tahun Lulus	short_text	t	3	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
91	2	11	nmmhsmsmh	Nama	short_text	t	5	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
92	2	11	telpomsmh	Nomor Telepon/HP	short_text	t	6	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
93	2	11	emailmsmh	Alamat Email	short_text	t	7	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
94	2	11	nik	NIK	short_text	t	8	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
95	2	11	npwp	NPWP	short_text	f	9	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
96	2	12	f8	Jelaskan status Anda saat ini?	single_choice	t	10	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
97	2	12	f502	Dalam berapa bulan Anda mendapatkan pekerjaan pertama? / Dalam berapa bulan setelah lulus Anda memulai wiraswasta?	number	f	11	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
98	2	12	f505	Berapa rata-rata pendapatan Anda per bulan? (take home pay)	number	f	12	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
90	2	11	kdpstmsmh	Kode Prodi	short_text	t	4	{"lookup":"program","lookup_value":"code"}	2026-06-01 10:16:26	2026-08-11 16:39:40
101	2	12	f1101	Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?	single_choice	f	15	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
102	2	12	f1102	Sebutkan jenis perusahaan/instansi lainnya	short_text	f	16	{"show_if":{"f1101":[5]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
103	2	12	f5b	Apa nama perusahaan/kantor tempat Anda bekerja?	short_text	f	17	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
105	2	12	f5d	Apa tingkat tempat kerja Anda?	single_choice	f	19	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
106	2	13	f18a	Sumber biaya untuk studi lanjut?	single_choice	f	20	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
107	2	13	f18b	Perguruan Tinggi tempat studi lanjut?	short_text	f	21	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
108	2	13	f18c	Program Studi studi lanjut?	short_text	f	22	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
109	2	13	f18d	Tanggal Masuk studi lanjut? (dd/mm/yyyy)	date	f	23	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
110	2	14	f1201	Sebutkan sumber dana dalam pembiayaan kuliah Anda? (bukan ketika Studi Lanjut)	single_choice	t	24	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
111	2	14	f1202	Sebutkan sumber dana pembiayaan kuliah lainnya	short_text	f	25	{"show_if":{"f1201":[7]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
112	2	15	f14	Seberapa erat hubungan bidang studi dengan pekerjaan Anda?	single_choice	f	26	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
113	2	15	f15	Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?	single_choice	f	27	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
114	2	16	f1761	Pada saat lulus, pada tingkat mana kompetensi Etika Anda kuasai? (A)	number	t	28	{"scale_min":1,"scale_max":5,"competency":"Etika","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
115	2	16	f1762	Pada saat ini, pada tingkat mana kompetensi Etika diperlukan dalam pekerjaan? (B)	number	t	29	{"scale_min":1,"scale_max":5,"competency":"Etika","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
116	2	16	f1763	Pada saat lulus, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu Anda kuasai? (A)	number	t	30	{"scale_min":1,"scale_max":5,"competency":"Keahlian berdasarkan bidang ilmu","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
117	2	16	f1764	Pada saat ini, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu diperlukan dalam pekerjaan? (B)	number	t	31	{"scale_min":1,"scale_max":5,"competency":"Keahlian berdasarkan bidang ilmu","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
118	2	16	f1765	Pada saat lulus, pada tingkat mana kompetensi Bahasa Inggris Anda kuasai? (A)	number	t	32	{"scale_min":1,"scale_max":5,"competency":"Bahasa Inggris","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
119	2	16	f1766	Pada saat ini, pada tingkat mana kompetensi Bahasa Inggris diperlukan dalam pekerjaan? (B)	number	t	33	{"scale_min":1,"scale_max":5,"competency":"Bahasa Inggris","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
120	2	16	f1767	Pada saat lulus, pada tingkat mana kompetensi Penggunaan Teknologi Informasi Anda kuasai? (A)	number	t	34	{"scale_min":1,"scale_max":5,"competency":"Penggunaan Teknologi Informasi","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
121	2	16	f1768	Pada saat ini, pada tingkat mana kompetensi Penggunaan Teknologi Informasi diperlukan dalam pekerjaan? (B)	number	t	35	{"scale_min":1,"scale_max":5,"competency":"Penggunaan Teknologi Informasi","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
122	2	16	f1769	Pada saat lulus, pada tingkat mana kompetensi Komunikasi Anda kuasai? (A)	number	t	36	{"scale_min":1,"scale_max":5,"competency":"Komunikasi","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
123	2	16	f1770	Pada saat ini, pada tingkat mana kompetensi Komunikasi diperlukan dalam pekerjaan? (B)	number	t	37	{"scale_min":1,"scale_max":5,"competency":"Komunikasi","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
124	2	16	f1771	Pada saat lulus, pada tingkat mana kompetensi Kerja sama tim Anda kuasai? (A)	number	t	38	{"scale_min":1,"scale_max":5,"competency":"Kerja sama tim","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
125	2	16	f1772	Pada saat ini, pada tingkat mana kompetensi Kerja sama tim diperlukan dalam pekerjaan? (B)	number	t	39	{"scale_min":1,"scale_max":5,"competency":"Kerja sama tim","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
126	2	16	f1773	Pada saat lulus, pada tingkat mana kompetensi Pengembangan diri Anda kuasai? (A)	number	t	40	{"scale_min":1,"scale_max":5,"competency":"Pengembangan diri","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
127	2	16	f1774	Pada saat ini, pada tingkat mana kompetensi Pengembangan diri diperlukan dalam pekerjaan? (B)	number	t	41	{"scale_min":1,"scale_max":5,"competency":"Pengembangan diri","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
128	2	17	f21	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Perkuliahan" di program studi Anda?	number	t	42	{"scale_min":1,"scale_max":5,"method":"Perkuliahan","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
129	2	17	f22	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Demonstrasi" di program studi Anda?	number	t	43	{"scale_min":1,"scale_max":5,"method":"Demonstrasi","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
195	3	23	f18d	Tanggal Masuk studi lanjut? (dd/mm/yyyy)	date	f	23	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
196	3	24	f1201	Sebutkan sumber dana dalam pembiayaan kuliah Anda? (bukan ketika Studi Lanjut)	single_choice	t	24	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
130	2	17	f23	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Partisipasi dalam proyek riset" di program studi Anda?	number	t	44	{"scale_min":1,"scale_max":5,"method":"Partisipasi dalam proyek riset","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
131	2	17	f24	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Magang" di program studi Anda?	number	t	45	{"scale_min":1,"scale_max":5,"method":"Magang","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
132	2	17	f25	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Praktikum" di program studi Anda?	number	t	46	{"scale_min":1,"scale_max":5,"method":"Praktikum","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
133	2	17	f26	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Kerja Lapangan" di program studi Anda?	number	t	47	{"scale_min":1,"scale_max":5,"method":"Kerja Lapangan","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
134	2	17	f27	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Diskusi" di program studi Anda?	number	t	48	{"scale_min":1,"scale_max":5,"method":"Diskusi","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
135	2	18	f301	Kapan Anda mulai mencari pekerjaan? Mohon pekerjaan sambilan tidak dimasukkan.	single_choice	t	49	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
136	2	18	f302	Kira-kira berapa bulan sebelum lulus Anda mulai mencari pekerjaan?	number	f	50	{"show_if":{"f301":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
137	2	18	f303	Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?	number	f	51	{"show_if":{"f301":[2]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
138	2	18	f401	Bagaimana Anda mencari pekerjaan tersebut? — Melalui iklan di koran/majalah, brosur	boolean	f	52	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui iklan di koran\\/majalah, brosur","group_title":"Bagaimana Anda mencari pekerjaan tersebut? Jawaban bisa lebih dari satu."}	2026-06-01 10:16:26	2026-06-01 10:16:26
139	2	18	f402	Bagaimana Anda mencari pekerjaan tersebut? — Melamar ke perusahaan tanpa mengetahui lowongan yang ada	boolean	f	53	{"group_code":"q16_cara_cari_kerja","group_label":"Melamar ke perusahaan tanpa mengetahui lowongan yang ada"}	2026-06-01 10:16:26	2026-06-01 10:16:26
140	2	18	f403	Bagaimana Anda mencari pekerjaan tersebut? — Pergi ke bursa/pameran kerja	boolean	f	54	{"group_code":"q16_cara_cari_kerja","group_label":"Pergi ke bursa\\/pameran kerja"}	2026-06-01 10:16:26	2026-06-01 10:16:26
141	2	18	f404	Bagaimana Anda mencari pekerjaan tersebut? — Mencari lewat internet/iklan online/milis	boolean	f	55	{"group_code":"q16_cara_cari_kerja","group_label":"Mencari lewat internet\\/iklan online\\/milis"}	2026-06-01 10:16:26	2026-06-01 10:16:26
142	2	18	f405	Bagaimana Anda mencari pekerjaan tersebut? — Dihubungi oleh perusahaan	boolean	f	56	{"group_code":"q16_cara_cari_kerja","group_label":"Dihubungi oleh perusahaan"}	2026-06-01 10:16:26	2026-06-01 10:16:26
143	2	18	f406	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi Kemenakertrans	boolean	f	57	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi Kemenakertrans"}	2026-06-01 10:16:26	2026-06-01 10:16:26
144	2	18	f407	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi agen tenaga kerja komersial/swasta	boolean	f	58	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi agen tenaga kerja komersial\\/swasta"}	2026-06-01 10:16:26	2026-06-01 10:16:26
145	2	18	f408	Bagaimana Anda mencari pekerjaan tersebut? — Memperoleh informasi dari pusat/kantor pengembangan karir fakultas/universitas	boolean	f	59	{"group_code":"q16_cara_cari_kerja","group_label":"Memperoleh informasi dari pusat\\/kantor pengembangan karir fakultas\\/universitas"}	2026-06-01 10:16:26	2026-06-01 10:16:26
146	2	18	f409	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi kantor kemahasiswaan/hubungan alumni	boolean	f	60	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi kantor kemahasiswaan\\/hubungan alumni"}	2026-06-01 10:16:26	2026-06-01 10:16:26
147	2	18	f410	Bagaimana Anda mencari pekerjaan tersebut? — Membangun jejaring (network) sejak masih kuliah	boolean	f	61	{"group_code":"q16_cara_cari_kerja","group_label":"Membangun jejaring (network) sejak masih kuliah"}	2026-06-01 10:16:26	2026-06-01 10:16:26
148	2	18	f411	Bagaimana Anda mencari pekerjaan tersebut? — Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll.)	boolean	f	62	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll.)"}	2026-06-01 10:16:26	2026-06-01 10:16:26
149	2	18	f412	Bagaimana Anda mencari pekerjaan tersebut? — Membangun bisnis sendiri	boolean	f	63	{"group_code":"q16_cara_cari_kerja","group_label":"Membangun bisnis sendiri"}	2026-06-01 10:16:26	2026-06-01 10:16:26
150	2	18	f413	Bagaimana Anda mencari pekerjaan tersebut? — Melalui penempatan kerja atau magang	boolean	f	64	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui penempatan kerja atau magang"}	2026-06-01 10:16:26	2026-06-01 10:16:26
151	2	18	f414	Bagaimana Anda mencari pekerjaan tersebut? — Bekerja di tempat yang sama dengan tempat kerja semasa kuliah	boolean	f	65	{"group_code":"q16_cara_cari_kerja","group_label":"Bekerja di tempat yang sama dengan tempat kerja semasa kuliah"}	2026-06-01 10:16:26	2026-06-01 10:16:26
152	2	18	f415	Bagaimana Anda mencari pekerjaan tersebut? — Lainnya	boolean	f	66	{"group_code":"q16_cara_cari_kerja","group_label":"Lainnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
153	2	18	f416	Sebutkan cara lainnya dalam mencari pekerjaan	short_text	f	67	{"show_if":{"f415":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
154	2	19	f6	Berapa perusahaan/instansi yang sudah Anda lamar (lewat surat atau e-mail) sebelum Anda memperoleh pekerjaan pertama?	number	f	68	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
155	2	19	f7	Berapa banyak perusahaan/instansi yang merespons lamaran Anda?	number	f	69	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
156	2	19	f7a	Berapa banyak perusahaan/instansi yang mengundang Anda untuk wawancara?	number	f	70	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
157	2	20	f1001	Apakah Anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilih satu jawaban.	single_choice	t	71	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
158	2	20	f1002	Sebutkan aktivitas lainnya dalam mencari pekerjaan	short_text	f	72	{"show_if":{"f1001":[5]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
159	2	20	f1601	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya	boolean	f	73	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya","group_title":"Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? Jawaban bisa lebih dari satu."}	2026-06-01 10:16:26	2026-06-01 10:16:26
160	2	20	f1602	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya belum mendapatkan pekerjaan yang lebih sesuai	boolean	f	74	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya belum mendapatkan pekerjaan yang lebih sesuai"}	2026-06-01 10:16:26	2026-06-01 10:16:26
161	2	20	f1603	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Di pekerjaan ini saya memperoleh prospek karir yang baik	boolean	f	75	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Di pekerjaan ini saya memperoleh prospek karir yang baik"}	2026-06-01 10:16:26	2026-06-01 10:16:26
162	2	20	f1604	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya	boolean	f	76	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
163	2	20	f1605	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya	boolean	f	77	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
164	2	20	f1606	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	boolean	f	78	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini"}	2026-06-01 10:16:26	2026-06-01 10:16:26
165	2	20	f1607	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih aman/terjamin/secure	boolean	f	79	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih aman\\/terjamin\\/secure"}	2026-06-01 10:16:26	2026-06-01 10:16:26
166	2	20	f1608	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih menarik	boolean	f	80	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih menarik"}	2026-06-01 10:16:26	2026-06-01 10:16:26
167	2	20	f1609	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.	boolean	f	81	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan\\/jadwal yang fleksibel, dll."}	2026-06-01 10:16:26	2026-06-01 10:16:26
168	2	20	f1610	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya	boolean	f	82	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
169	2	20	f1611	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya	boolean	f	83	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
170	2	20	f1612	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya	boolean	f	84	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
171	2	20	f1613	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Lainnya	boolean	f	85	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Lainnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
172	2	20	f1614	Sebutkan alasan lainnya mengambil pekerjaan yang tidak sesuai pendidikan	short_text	f	86	{"show_if":{"f1613":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
173	3	21	nimhsmsmh	NIM	short_text	t	1	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
174	3	21	kdptimsmh	Kode PT	short_text	t	2	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
175	3	21	tahun_lulus	Tahun Lulus	short_text	t	3	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
177	3	21	nmmhsmsmh	Nama	short_text	t	5	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
178	3	21	telpomsmh	Nomor Telepon/HP	short_text	t	6	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
179	3	21	emailmsmh	Alamat Email	short_text	t	7	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
180	3	21	nik	NIK	short_text	t	8	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
181	3	21	npwp	NPWP	short_text	f	9	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
182	3	22	f8	Jelaskan status Anda saat ini?	single_choice	t	10	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
183	3	22	f502	Dalam berapa bulan Anda mendapatkan pekerjaan pertama? / Dalam berapa bulan setelah lulus Anda memulai wiraswasta?	number	f	11	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
184	3	22	f505	Berapa rata-rata pendapatan Anda per bulan? (take home pay)	number	f	12	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
187	3	22	f1101	Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?	single_choice	f	15	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
188	3	22	f1102	Sebutkan jenis perusahaan/instansi lainnya	short_text	f	16	{"show_if":{"f1101":[5]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
189	3	22	f5b	Apa nama perusahaan/kantor tempat Anda bekerja?	short_text	f	17	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
191	3	22	f5d	Apa tingkat tempat kerja Anda?	single_choice	f	19	{"show_if":{"f8":[1,3]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
192	3	23	f18a	Sumber biaya untuk studi lanjut?	single_choice	f	20	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
193	3	23	f18b	Perguruan Tinggi tempat studi lanjut?	short_text	f	21	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
194	3	23	f18c	Program Studi studi lanjut?	short_text	f	22	{"show_if":{"f8":[4]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
185	3	22	f5a1	Dimana lokasi tempat Anda bekerja? (Provinsi)	short_text	f	13	{"show_if":{"f8":[1,3]},"lookup":"province"}	2026-06-01 10:16:26	2026-08-11 16:39:40
197	3	24	f1202	Sebutkan sumber dana pembiayaan kuliah lainnya	short_text	f	25	{"show_if":{"f1201":[7]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
198	3	25	f14	Seberapa erat hubungan bidang studi dengan pekerjaan Anda?	single_choice	f	26	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
199	3	25	f15	Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?	single_choice	f	27	{"show_if":{"f8":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
200	3	26	f1761	Pada saat lulus, pada tingkat mana kompetensi Etika Anda kuasai? (A)	number	t	28	{"scale_min":1,"scale_max":5,"competency":"Etika","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
201	3	26	f1762	Pada saat ini, pada tingkat mana kompetensi Etika diperlukan dalam pekerjaan? (B)	number	t	29	{"scale_min":1,"scale_max":5,"competency":"Etika","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
202	3	26	f1763	Pada saat lulus, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu Anda kuasai? (A)	number	t	30	{"scale_min":1,"scale_max":5,"competency":"Keahlian berdasarkan bidang ilmu","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
203	3	26	f1764	Pada saat ini, pada tingkat mana kompetensi Keahlian berdasarkan bidang ilmu diperlukan dalam pekerjaan? (B)	number	t	31	{"scale_min":1,"scale_max":5,"competency":"Keahlian berdasarkan bidang ilmu","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
204	3	26	f1765	Pada saat lulus, pada tingkat mana kompetensi Bahasa Inggris Anda kuasai? (A)	number	t	32	{"scale_min":1,"scale_max":5,"competency":"Bahasa Inggris","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
205	3	26	f1766	Pada saat ini, pada tingkat mana kompetensi Bahasa Inggris diperlukan dalam pekerjaan? (B)	number	t	33	{"scale_min":1,"scale_max":5,"competency":"Bahasa Inggris","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
206	3	26	f1767	Pada saat lulus, pada tingkat mana kompetensi Penggunaan Teknologi Informasi Anda kuasai? (A)	number	t	34	{"scale_min":1,"scale_max":5,"competency":"Penggunaan Teknologi Informasi","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
207	3	26	f1768	Pada saat ini, pada tingkat mana kompetensi Penggunaan Teknologi Informasi diperlukan dalam pekerjaan? (B)	number	t	35	{"scale_min":1,"scale_max":5,"competency":"Penggunaan Teknologi Informasi","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
208	3	26	f1769	Pada saat lulus, pada tingkat mana kompetensi Komunikasi Anda kuasai? (A)	number	t	36	{"scale_min":1,"scale_max":5,"competency":"Komunikasi","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
209	3	26	f1770	Pada saat ini, pada tingkat mana kompetensi Komunikasi diperlukan dalam pekerjaan? (B)	number	t	37	{"scale_min":1,"scale_max":5,"competency":"Komunikasi","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
210	3	26	f1771	Pada saat lulus, pada tingkat mana kompetensi Kerja sama tim Anda kuasai? (A)	number	t	38	{"scale_min":1,"scale_max":5,"competency":"Kerja sama tim","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
211	3	26	f1772	Pada saat ini, pada tingkat mana kompetensi Kerja sama tim diperlukan dalam pekerjaan? (B)	number	t	39	{"scale_min":1,"scale_max":5,"competency":"Kerja sama tim","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
212	3	26	f1773	Pada saat lulus, pada tingkat mana kompetensi Pengembangan diri Anda kuasai? (A)	number	t	40	{"scale_min":1,"scale_max":5,"competency":"Pengembangan diri","dimension":"saat_lulus","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
213	3	26	f1774	Pada saat ini, pada tingkat mana kompetensi Pengembangan diri diperlukan dalam pekerjaan? (B)	number	t	41	{"scale_min":1,"scale_max":5,"competency":"Pengembangan diri","dimension":"saat_ini","scale_labels":["Sangat Rendah","Rendah","Cukup","Tinggi","Sangat Tinggi"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
214	3	27	f21	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Perkuliahan" di program studi Anda?	number	t	42	{"scale_min":1,"scale_max":5,"method":"Perkuliahan","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
215	3	27	f22	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Demonstrasi" di program studi Anda?	number	t	43	{"scale_min":1,"scale_max":5,"method":"Demonstrasi","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
216	3	27	f23	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Partisipasi dalam proyek riset" di program studi Anda?	number	t	44	{"scale_min":1,"scale_max":5,"method":"Partisipasi dalam proyek riset","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
217	3	27	f24	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Magang" di program studi Anda?	number	t	45	{"scale_min":1,"scale_max":5,"method":"Magang","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
218	3	27	f25	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Praktikum" di program studi Anda?	number	t	46	{"scale_min":1,"scale_max":5,"method":"Praktikum","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
219	3	27	f26	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Kerja Lapangan" di program studi Anda?	number	t	47	{"scale_min":1,"scale_max":5,"method":"Kerja Lapangan","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
220	3	27	f27	Menurut Anda seberapa besar penekanan pada metode pembelajaran "Diskusi" di program studi Anda?	number	t	48	{"scale_min":1,"scale_max":5,"method":"Diskusi","scale_labels":["Sangat Besar","Besar","Cukup Besar","Kurang Besar","Tidak Sama Sekali"]}	2026-06-01 10:16:26	2026-06-01 10:16:26
221	3	28	f301	Kapan Anda mulai mencari pekerjaan? Mohon pekerjaan sambilan tidak dimasukkan.	single_choice	t	49	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
222	3	28	f302	Kira-kira berapa bulan sebelum lulus Anda mulai mencari pekerjaan?	number	f	50	{"show_if":{"f301":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
223	3	28	f303	Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?	number	f	51	{"show_if":{"f301":[2]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
224	3	28	f401	Bagaimana Anda mencari pekerjaan tersebut? — Melalui iklan di koran/majalah, brosur	boolean	f	52	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui iklan di koran\\/majalah, brosur","group_title":"Bagaimana Anda mencari pekerjaan tersebut? Jawaban bisa lebih dari satu."}	2026-06-01 10:16:26	2026-06-01 10:16:26
225	3	28	f402	Bagaimana Anda mencari pekerjaan tersebut? — Melamar ke perusahaan tanpa mengetahui lowongan yang ada	boolean	f	53	{"group_code":"q16_cara_cari_kerja","group_label":"Melamar ke perusahaan tanpa mengetahui lowongan yang ada"}	2026-06-01 10:16:26	2026-06-01 10:16:26
226	3	28	f403	Bagaimana Anda mencari pekerjaan tersebut? — Pergi ke bursa/pameran kerja	boolean	f	54	{"group_code":"q16_cara_cari_kerja","group_label":"Pergi ke bursa\\/pameran kerja"}	2026-06-01 10:16:26	2026-06-01 10:16:26
227	3	28	f404	Bagaimana Anda mencari pekerjaan tersebut? — Mencari lewat internet/iklan online/milis	boolean	f	55	{"group_code":"q16_cara_cari_kerja","group_label":"Mencari lewat internet\\/iklan online\\/milis"}	2026-06-01 10:16:26	2026-06-01 10:16:26
228	3	28	f405	Bagaimana Anda mencari pekerjaan tersebut? — Dihubungi oleh perusahaan	boolean	f	56	{"group_code":"q16_cara_cari_kerja","group_label":"Dihubungi oleh perusahaan"}	2026-06-01 10:16:26	2026-06-01 10:16:26
229	3	28	f406	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi Kemenakertrans	boolean	f	57	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi Kemenakertrans"}	2026-06-01 10:16:26	2026-06-01 10:16:26
230	3	28	f407	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi agen tenaga kerja komersial/swasta	boolean	f	58	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi agen tenaga kerja komersial\\/swasta"}	2026-06-01 10:16:26	2026-06-01 10:16:26
231	3	28	f408	Bagaimana Anda mencari pekerjaan tersebut? — Memperoleh informasi dari pusat/kantor pengembangan karir fakultas/universitas	boolean	f	59	{"group_code":"q16_cara_cari_kerja","group_label":"Memperoleh informasi dari pusat\\/kantor pengembangan karir fakultas\\/universitas"}	2026-06-01 10:16:26	2026-06-01 10:16:26
232	3	28	f409	Bagaimana Anda mencari pekerjaan tersebut? — Menghubungi kantor kemahasiswaan/hubungan alumni	boolean	f	60	{"group_code":"q16_cara_cari_kerja","group_label":"Menghubungi kantor kemahasiswaan\\/hubungan alumni"}	2026-06-01 10:16:26	2026-06-01 10:16:26
233	3	28	f410	Bagaimana Anda mencari pekerjaan tersebut? — Membangun jejaring (network) sejak masih kuliah	boolean	f	61	{"group_code":"q16_cara_cari_kerja","group_label":"Membangun jejaring (network) sejak masih kuliah"}	2026-06-01 10:16:26	2026-06-01 10:16:26
234	3	28	f411	Bagaimana Anda mencari pekerjaan tersebut? — Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll.)	boolean	f	62	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll.)"}	2026-06-01 10:16:26	2026-06-01 10:16:26
235	3	28	f412	Bagaimana Anda mencari pekerjaan tersebut? — Membangun bisnis sendiri	boolean	f	63	{"group_code":"q16_cara_cari_kerja","group_label":"Membangun bisnis sendiri"}	2026-06-01 10:16:26	2026-06-01 10:16:26
236	3	28	f413	Bagaimana Anda mencari pekerjaan tersebut? — Melalui penempatan kerja atau magang	boolean	f	64	{"group_code":"q16_cara_cari_kerja","group_label":"Melalui penempatan kerja atau magang"}	2026-06-01 10:16:26	2026-06-01 10:16:26
237	3	28	f414	Bagaimana Anda mencari pekerjaan tersebut? — Bekerja di tempat yang sama dengan tempat kerja semasa kuliah	boolean	f	65	{"group_code":"q16_cara_cari_kerja","group_label":"Bekerja di tempat yang sama dengan tempat kerja semasa kuliah"}	2026-06-01 10:16:26	2026-06-01 10:16:26
238	3	28	f415	Bagaimana Anda mencari pekerjaan tersebut? — Lainnya	boolean	f	66	{"group_code":"q16_cara_cari_kerja","group_label":"Lainnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
239	3	28	f416	Sebutkan cara lainnya dalam mencari pekerjaan	short_text	f	67	{"show_if":{"f415":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
240	3	29	f6	Berapa perusahaan/instansi yang sudah Anda lamar (lewat surat atau e-mail) sebelum Anda memperoleh pekerjaan pertama?	number	f	68	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
241	3	29	f7	Berapa banyak perusahaan/instansi yang merespons lamaran Anda?	number	f	69	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
242	3	29	f7a	Berapa banyak perusahaan/instansi yang mengundang Anda untuk wawancara?	number	f	70	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
243	3	30	f1001	Apakah Anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilih satu jawaban.	single_choice	t	71	\N	2026-06-01 10:16:26	2026-06-01 10:16:26
244	3	30	f1002	Sebutkan aktivitas lainnya dalam mencari pekerjaan	short_text	f	72	{"show_if":{"f1001":[5]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
245	3	30	f1601	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya	boolean	f	73	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya","group_title":"Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? Jawaban bisa lebih dari satu."}	2026-06-01 10:16:26	2026-06-01 10:16:26
246	3	30	f1602	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya belum mendapatkan pekerjaan yang lebih sesuai	boolean	f	74	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya belum mendapatkan pekerjaan yang lebih sesuai"}	2026-06-01 10:16:26	2026-06-01 10:16:26
247	3	30	f1603	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Di pekerjaan ini saya memperoleh prospek karir yang baik	boolean	f	75	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Di pekerjaan ini saya memperoleh prospek karir yang baik"}	2026-06-01 10:16:26	2026-06-01 10:16:26
248	3	30	f1604	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya	boolean	f	76	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
249	3	30	f1605	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya	boolean	f	77	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
250	3	30	f1606	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	boolean	f	78	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini"}	2026-06-01 10:16:26	2026-06-01 10:16:26
251	3	30	f1607	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih aman/terjamin/secure	boolean	f	79	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih aman\\/terjamin\\/secure"}	2026-06-01 10:16:26	2026-06-01 10:16:26
252	3	30	f1608	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih menarik	boolean	f	80	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih menarik"}	2026-06-01 10:16:26	2026-06-01 10:16:26
253	3	30	f1609	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.	boolean	f	81	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan\\/jadwal yang fleksibel, dll."}	2026-06-01 10:16:26	2026-06-01 10:16:26
254	3	30	f1610	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya	boolean	f	82	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
255	3	30	f1611	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya	boolean	f	83	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
256	3	30	f1612	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya	boolean	f	84	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
257	3	30	f1613	Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — Lainnya	boolean	f	85	{"group_code":"q21_alasan_tidak_sesuai","group_label":"Lainnya"}	2026-06-01 10:16:26	2026-06-01 10:16:26
258	3	30	f1614	Sebutkan alasan lainnya mengambil pekerjaan yang tidak sesuai pendidikan	short_text	f	86	{"show_if":{"f1613":[1]}}	2026-06-01 10:16:26	2026-06-01 10:16:26
18	1	2	f5c	Bila berwiraswasta, apa posisi/jabatan Anda saat ini?	single_choice	f	18	{"show_if":{"f8":[3]},"option_hints":{"1":"Pendiri utama usaha","2":"Salah satu pendiri bersama rekan lain","3":"Pekerja atau karyawan di usaha tersebut","4":"Pekerja paruh waktu atau lepas"}}	2026-06-01 10:16:26	2026-08-11 16:39:40
104	2	12	f5c	Bila berwiraswasta, apa posisi/jabatan Anda saat ini?	single_choice	f	18	{"show_if":{"f8":[3]},"option_hints":{"1":"Pendiri utama usaha","2":"Salah satu pendiri bersama rekan lain","3":"Pekerja atau karyawan di usaha tersebut","4":"Pekerja paruh waktu atau lepas"}}	2026-06-01 10:16:26	2026-08-11 16:39:40
190	3	22	f5c	Bila berwiraswasta, apa posisi/jabatan Anda saat ini?	single_choice	f	18	{"show_if":{"f8":[3]},"option_hints":{"1":"Pendiri utama usaha","2":"Salah satu pendiri bersama rekan lain","3":"Pekerja atau karyawan di usaha tersebut","4":"Pekerja paruh waktu atau lepas"}}	2026-06-01 10:16:26	2026-08-11 16:39:40
13	1	2	f5a1	Dimana lokasi tempat Anda bekerja? (Provinsi)	short_text	f	13	{"show_if":{"f8":[1,3]},"lookup":"province"}	2026-06-01 10:16:26	2026-08-11 16:39:40
99	2	12	f5a1	Dimana lokasi tempat Anda bekerja? (Provinsi)	short_text	f	13	{"show_if":{"f8":[1,3]},"lookup":"province"}	2026-06-01 10:16:26	2026-08-11 16:39:40
14	1	2	f5a2	Dimana lokasi tempat Anda bekerja? (Kota/Kabupaten)	short_text	f	14	{"show_if":{"f8":[1,3]},"lookup":"city","depends_on":"f5a1"}	2026-06-01 10:16:26	2026-08-11 16:39:40
100	2	12	f5a2	Dimana lokasi tempat Anda bekerja? (Kota/Kabupaten)	short_text	f	14	{"show_if":{"f8":[1,3]},"lookup":"city","depends_on":"f5a1"}	2026-06-01 10:16:26	2026-08-11 16:39:40
186	3	22	f5a2	Dimana lokasi tempat Anda bekerja? (Kota/Kabupaten)	short_text	f	14	{"show_if":{"f8":[1,3]},"lookup":"city","depends_on":"f5a1"}	2026-06-01 10:16:26	2026-08-11 16:39:40
4	1	1	kdpstmsmh	Kode Prodi	short_text	t	4	{"lookup":"program","lookup_value":"code"}	2026-06-01 10:16:26	2026-08-11 16:39:40
176	3	21	kdpstmsmh	Kode Prodi	short_text	t	4	{"lookup":"program","lookup_value":"code"}	2026-06-01 10:16:26	2026-08-11 16:39:40
647	1	31	stk1_nama	Tuliskan nama atasan Anda (bekerja) / rekan bisnis (wiraswasta) / dosen pembimbing (lanjut studi)	short_text	f	87	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 1","description":"Orang yang menilai hasil kerja Anda secara langsung."}	2026-08-11 16:39:40	2026-08-11 16:39:40
648	1	31	stk1_email	Tuliskan alamat surel Penilai 1	short_text	f	88	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
649	1	31	stk2_nama	Tuliskan nama senior terdekat Anda (bekerja) / rekan kerja (wiraswasta) / mahasiswa senior (lanjut studi)	short_text	f	89	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 2","description":"Orang yang sehari-hari bekerja atau belajar berdampingan dengan Anda."}	2026-08-11 16:39:40	2026-08-11 16:39:40
650	1	31	stk2_email	Tuliskan alamat surel Penilai 2	short_text	f	90	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
651	1	31	stk3_nama	Tuliskan nama HRD Anda (bekerja) / rekan kerja lainnya (wiraswasta) / mahasiswa seangkatan (lanjut studi)	short_text	f	91	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 3","description":"Orang ketiga yang dapat memberi gambaran berbeda tentang Anda."}	2026-08-11 16:39:40	2026-08-11 16:39:40
652	1	31	stk3_email	Tuliskan alamat surel Penilai 3	short_text	f	92	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
653	2	32	stk1_nama	Tuliskan nama atasan Anda (bekerja) / rekan bisnis (wiraswasta) / dosen pembimbing (lanjut studi)	short_text	f	87	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 1","description":"Orang yang menilai hasil kerja Anda secara langsung."}	2026-08-11 16:39:40	2026-08-11 16:39:40
654	2	32	stk1_email	Tuliskan alamat surel Penilai 1	short_text	f	88	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
655	2	32	stk2_nama	Tuliskan nama senior terdekat Anda (bekerja) / rekan kerja (wiraswasta) / mahasiswa senior (lanjut studi)	short_text	f	89	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 2","description":"Orang yang sehari-hari bekerja atau belajar berdampingan dengan Anda."}	2026-08-11 16:39:40	2026-08-11 16:39:40
656	2	32	stk2_email	Tuliskan alamat surel Penilai 2	short_text	f	90	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
657	2	32	stk3_nama	Tuliskan nama HRD Anda (bekerja) / rekan kerja lainnya (wiraswasta) / mahasiswa seangkatan (lanjut studi)	short_text	f	91	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 3","description":"Orang ketiga yang dapat memberi gambaran berbeda tentang Anda."}	2026-08-11 16:39:40	2026-08-11 16:39:40
658	2	32	stk3_email	Tuliskan alamat surel Penilai 3	short_text	f	92	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
659	3	33	stk1_nama	Tuliskan nama atasan Anda (bekerja) / rekan bisnis (wiraswasta) / dosen pembimbing (lanjut studi)	short_text	f	87	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 1","description":"Orang yang menilai hasil kerja Anda secara langsung."}	2026-08-11 16:39:40	2026-08-11 16:39:40
660	3	33	stk1_email	Tuliskan alamat surel Penilai 1	short_text	f	88	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
661	3	33	stk2_nama	Tuliskan nama senior terdekat Anda (bekerja) / rekan kerja (wiraswasta) / mahasiswa senior (lanjut studi)	short_text	f	89	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 2","description":"Orang yang sehari-hari bekerja atau belajar berdampingan dengan Anda."}	2026-08-11 16:39:40	2026-08-11 16:39:40
662	3	33	stk2_email	Tuliskan alamat surel Penilai 2	short_text	f	90	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
663	3	33	stk3_nama	Tuliskan nama HRD Anda (bekerja) / rekan kerja lainnya (wiraswasta) / mahasiswa seangkatan (lanjut studi)	short_text	f	91	{"show_if":{"f8":[1,3,4]},"divider_label":"Penilai 3","description":"Orang ketiga yang dapat memberi gambaran berbeda tentang Anda."}	2026-08-11 16:39:40	2026-08-11 16:39:40
664	3	33	stk3_email	Tuliskan alamat surel Penilai 3	short_text	f	92	{"show_if":{"f8":[1,3,4]},"description":"Sesuai nama yang Anda tulis di atas.","format":"email"}	2026-08-11 16:39:40	2026-08-11 16:39:40
\.



SELECT pg_catalog.setval('tracer_oltp.questionnaire_questions_id_seq', 664, true);









COPY tracer_oltp.questionnaire_options (id, question_id, option_code, option_label, option_value, order_no, is_active, created_at, updated_at, is_hidden) FROM stdin;
1	10	1	Bekerja (full time / part time)	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
2	10	2	Belum memungkinkan bekerja	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
3	10	3	Wiraswasta	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
4	10	4	Melanjutkan Pendidikan	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
5	10	5	Tidak kerja tetapi sedang mencari kerja	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
6	15	1	Instansi pemerintah	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
7	15	2	Organisasi non-profit/Lembaga Swadaya Masyarakat	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
8	15	3	Perusahaan swasta	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
9	15	4	Wiraswasta/perusahaan sendiri	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
13	19	1	Lokal/Wilayah/Wiraswasta tidak berbadan hukum	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
14	19	2	Nasional/Wiraswasta berbadan hukum	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
15	19	3	Multinasional/Internasional	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
16	20	1	Biaya Sendiri/Keluarga	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
17	20	2	Beasiswa	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
18	20	3	Asisten/Mengajar	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
19	20	4	Lainnya	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
20	24	1	Biaya Sendiri/Keluarga	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
21	24	2	Beasiswa ADIK	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
22	24	3	Beasiswa BIDIKMISI	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
23	24	4	Beasiswa PPA	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
24	24	5	Beasiswa AFIRMASI	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
25	24	6	Beasiswa Perusahaan/Swasta	\N	6	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
26	24	7	Lainnya, tuliskan	\N	7	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
27	26	1	Sangat Erat	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
28	26	2	Erat	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
29	26	3	Cukup Erat	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
30	26	4	Kurang Erat	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
31	26	5	Tidak Sama Sekali	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
32	27	1	Setingkat Lebih Tinggi	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
33	27	2	Tingkat yang Sama	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
34	27	3	Setingkat Lebih Rendah	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
35	27	4	Tidak Perlu Pendidikan Tinggi	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
36	49	1	Kira-kira __ bulan sebelum lulus	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
37	49	2	Kira-kira __ bulan sesudah lulus	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
38	49	3	Saya tidak mencari kerja	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
39	71	1	Tidak	\N	1	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
40	71	2	Tidak, tapi saya sedang menunggu hasil lamaran kerja	\N	2	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
41	71	3	Ya, saya akan mulai bekerja dalam 2 minggu ke depan	\N	3	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
42	71	4	Ya, tapi saya belum pasti akan bekerja dalam 2 minggu ke depan	\N	4	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
43	71	5	Lainnya	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
10	15	5	BUMN/BUMD	\N	5	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
11	15	6	Institusi/Organisasi Multilateral	\N	6	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
12	15	7	Lainnya, tuliskan	\N	7	t	2026-06-01 10:16:26	2026-06-01 10:16:26	f
134	86	1	Pertanyaan tidak sesuai; pekerjaan saya sudah sesuai pendidikan	\N	1	t	\N	\N	f
135	86	2	Saya belum mendapatkan pekerjaan yang lebih sesuai	\N	1	t	\N	\N	f
136	86	3	Di pekerjaan ini saya memperoleh prospek karir yang baik	\N	1	t	\N	\N	f
137	86	4	Saya lebih suka bekerja di area yang tidak ada hubungannya dengan pendidikan	\N	1	t	\N	\N	f
138	86	5	Dipromosikan ke posisi kurang berhubungan dengan pendidikan	\N	1	t	\N	\N	f
139	86	6	Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	\N	1	t	\N	\N	f
140	86	7	Pekerjaan saya saat ini lebih aman/terjamin/secure	\N	1	t	\N	\N	f
141	86	8	Pekerjaan saya saat ini lebih menarik	\N	1	t	\N	\N	f
142	86	9	Pekerjaan ini lebih memungkinkan jadwal fleksibel/pekerjaan tambahan	\N	1	t	\N	\N	f
143	86	10	Pekerjaan saya saat ini lokasinya lebih dekat dari rumah	\N	1	t	\N	\N	f
144	86	11	Pekerjaan saya dapat lebih menjamin kebutuhan keluarga	\N	1	t	\N	\N	f
145	86	12	Pada awal karir harus menerima pekerjaan tidak berhubungan dengan pendidikan	\N	1	t	\N	\N	f
146	86	13	Lainnya	\N	1	t	\N	\N	f
147	96	2	Belum memungkinkan bekerja	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
148	182	2	Belum memungkinkan bekerja	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
149	96	5	Tidak kerja tetapi sedang mencari kerja	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
150	182	5	Tidak kerja tetapi sedang mencari kerja	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
151	96	4	Melanjutkan Pendidikan	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
152	182	4	Melanjutkan Pendidikan	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
153	96	1	Bekerja (full time / part time)	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
154	182	1	Bekerja (full time / part time)	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
155	96	3	Wiraswasta	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
156	182	3	Wiraswasta	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
157	101	1	Instansi pemerintah	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
158	187	1	Instansi pemerintah	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
159	101	2	Organisasi non-profit/Lembaga Swadaya Masyarakat	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
160	187	2	Organisasi non-profit/Lembaga Swadaya Masyarakat	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
161	101	3	Perusahaan swasta	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
162	187	3	Perusahaan swasta	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
163	101	7	Lainnya, tuliskan	\N	7	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
164	187	7	Lainnya, tuliskan	\N	7	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
165	101	6	Institusi/Organisasi Multilateral	\N	6	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
166	187	6	Institusi/Organisasi Multilateral	\N	6	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
167	101	5	BUMN/BUMD	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
168	187	5	BUMN/BUMD	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
169	101	4	Wiraswasta/perusahaan sendiri	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
170	187	4	Wiraswasta/perusahaan sendiri	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
179	105	1	Lokal/Wilayah/Wiraswasta tidak berbadan hukum	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
180	191	1	Lokal/Wilayah/Wiraswasta tidak berbadan hukum	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
181	105	2	Nasional/Wiraswasta berbadan hukum	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
182	191	2	Nasional/Wiraswasta berbadan hukum	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
183	105	3	Multinasional/Internasional	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
184	191	3	Multinasional/Internasional	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
185	106	4	Lainnya	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
186	192	4	Lainnya	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
187	106	1	Biaya Sendiri/Keluarga	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
188	192	1	Biaya Sendiri/Keluarga	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
189	106	3	Asisten/Mengajar	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
190	192	3	Asisten/Mengajar	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
191	106	2	Beasiswa	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
192	192	2	Beasiswa	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
193	110	1	Biaya Sendiri/Keluarga	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
194	196	1	Biaya Sendiri/Keluarga	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
195	110	2	Beasiswa ADIK	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
196	196	2	Beasiswa ADIK	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
197	110	3	Beasiswa BIDIKMISI	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
198	196	3	Beasiswa BIDIKMISI	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
199	110	4	Beasiswa PPA	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
200	196	4	Beasiswa PPA	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
201	110	5	Beasiswa AFIRMASI	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
202	196	5	Beasiswa AFIRMASI	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
203	110	6	Beasiswa Perusahaan/Swasta	\N	6	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
204	196	6	Beasiswa Perusahaan/Swasta	\N	6	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
205	110	7	Lainnya, tuliskan	\N	7	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
206	196	7	Lainnya, tuliskan	\N	7	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
207	112	5	Tidak Sama Sekali	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
208	198	5	Tidak Sama Sekali	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
209	112	4	Kurang Erat	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
210	198	4	Kurang Erat	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
211	112	3	Cukup Erat	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
212	198	3	Cukup Erat	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
213	112	2	Erat	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
214	198	2	Erat	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
215	112	1	Sangat Erat	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
216	198	1	Sangat Erat	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
217	113	3	Setingkat Lebih Rendah	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
218	199	3	Setingkat Lebih Rendah	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
219	113	1	Setingkat Lebih Tinggi	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
220	199	1	Setingkat Lebih Tinggi	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
221	113	2	Tingkat yang Sama	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
222	199	2	Tingkat yang Sama	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
223	113	4	Tidak Perlu Pendidikan Tinggi	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
224	199	4	Tidak Perlu Pendidikan Tinggi	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
225	135	1	Kira-kira __ bulan sebelum lulus	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
226	221	1	Kira-kira __ bulan sebelum lulus	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
227	135	3	Saya tidak mencari kerja	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
228	221	3	Saya tidak mencari kerja	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
229	135	2	Kira-kira __ bulan sesudah lulus	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
230	221	2	Kira-kira __ bulan sesudah lulus	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
231	157	2	Tidak, tapi saya sedang menunggu hasil lamaran kerja	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
232	243	2	Tidak, tapi saya sedang menunggu hasil lamaran kerja	\N	2	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
233	157	1	Tidak	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
234	243	1	Tidak	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
235	157	5	Lainnya	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
236	243	5	Lainnya	\N	5	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
237	157	4	Ya, tapi saya belum pasti akan bekerja dalam 2 minggu ke depan	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
238	243	4	Ya, tapi saya belum pasti akan bekerja dalam 2 minggu ke depan	\N	4	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
239	157	3	Ya, saya akan mulai bekerja dalam 2 minggu ke depan	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
240	243	3	Ya, saya akan mulai bekerja dalam 2 minggu ke depan	\N	3	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
241	172	8	Pekerjaan saya saat ini lebih menarik	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
242	258	8	Pekerjaan saya saat ini lebih menarik	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
243	172	9	Pekerjaan ini lebih memungkinkan jadwal fleksibel/pekerjaan tambahan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
244	258	9	Pekerjaan ini lebih memungkinkan jadwal fleksibel/pekerjaan tambahan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
245	172	10	Pekerjaan saya saat ini lokasinya lebih dekat dari rumah	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
246	258	10	Pekerjaan saya saat ini lokasinya lebih dekat dari rumah	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
247	172	11	Pekerjaan saya dapat lebih menjamin kebutuhan keluarga	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
248	258	11	Pekerjaan saya dapat lebih menjamin kebutuhan keluarga	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
249	172	12	Pada awal karir harus menerima pekerjaan tidak berhubungan dengan pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
250	258	12	Pada awal karir harus menerima pekerjaan tidak berhubungan dengan pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
251	172	13	Lainnya	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
252	258	13	Lainnya	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
253	172	1	Pertanyaan tidak sesuai; pekerjaan saya sudah sesuai pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
254	258	1	Pertanyaan tidak sesuai; pekerjaan saya sudah sesuai pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
255	172	2	Saya belum mendapatkan pekerjaan yang lebih sesuai	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
256	258	2	Saya belum mendapatkan pekerjaan yang lebih sesuai	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
257	172	3	Di pekerjaan ini saya memperoleh prospek karir yang baik	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
258	258	3	Di pekerjaan ini saya memperoleh prospek karir yang baik	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
259	172	4	Saya lebih suka bekerja di area yang tidak ada hubungannya dengan pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
260	258	4	Saya lebih suka bekerja di area yang tidak ada hubungannya dengan pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
261	172	5	Dipromosikan ke posisi kurang berhubungan dengan pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
262	258	5	Dipromosikan ke posisi kurang berhubungan dengan pendidikan	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
263	172	6	Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
264	258	6	Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
265	172	7	Pekerjaan saya saat ini lebih aman/terjamin/secure	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
266	258	7	Pekerjaan saya saat ini lebih aman/terjamin/secure	\N	1	t	2026-06-19 20:27:02	2026-06-19 20:27:02	f
267	10	6	Melanjutkan pendidikan sambil bekerja	\N	6	t	2026-06-19 20:38:06	2026-06-19 20:38:06	f
268	10	7	Melanjutkan pendidikan sambil wiraswasta	\N	7	t	2026-06-19 20:38:06	2026-06-19 20:38:06	f
269	96	6	Melanjutkan pendidikan sambil bekerja	\N	6	t	2026-06-19 20:38:06	2026-06-19 20:38:06	f
270	96	7	Melanjutkan pendidikan sambil wiraswasta	\N	7	t	2026-06-19 20:38:06	2026-06-19 20:38:06	f
271	182	6	Melanjutkan pendidikan sambil bekerja	\N	6	t	2026-06-19 20:38:06	2026-06-19 20:38:06	f
272	182	7	Melanjutkan pendidikan sambil wiraswasta	\N	7	t	2026-06-19 20:38:06	2026-06-19 20:38:06	f
171	104	1	Founder	\N	1	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
177	104	2	Co-Founder	\N	2	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
175	104	3	Staff	\N	3	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
173	104	4	Freelance / Kerja Lepas	\N	4	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
172	190	1	Founder	\N	1	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
178	190	2	Co-Founder	\N	2	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
176	190	3	Staff	\N	3	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
174	190	4	Freelance / Kerja Lepas	\N	4	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
130	18	1	Founder	\N	1	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
131	18	2	Co-Founder	\N	2	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
132	18	3	Staff	\N	3	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
133	18	4	Freelance / Kerja Lepas	\N	4	t	2026-08-11 16:39:40	2026-08-11 16:39:40	f
\.



SELECT pg_catalog.setval('tracer_oltp.questionnaire_options_id_seq', 272, true);









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
154	1	f303	Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?	bulan_sesudah_lulus	narrow	2026-08-11	t	\N	\N	\N	2026-08-11 16:39:40	2026-08-11 16:39:40
155	2	f303	Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?	bulan_sesudah_lulus	narrow	2026-08-11	t	\N	\N	\N	2026-08-11 16:39:40	2026-08-11 16:39:40
156	3	f303	Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?	bulan_sesudah_lulus	narrow	2026-08-11	t	\N	\N	\N	2026-08-11 16:39:40	2026-08-11 16:39:40
\.



SELECT pg_catalog.setval('tracer_oltp.question_semantic_mapping_id_seq', 156, true);









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



SELECT pg_catalog.setval('tracer_oltp.tracer_response_thresholds_id_seq', 36, true);




