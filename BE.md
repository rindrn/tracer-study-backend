# SmartTracer OLAP — Dokumentasi API per Grafik

> Dokumen ini menjelaskan endpoint API, cara memanggil data dari repository terkait,
> dan contoh response JSON untuk setiap grafik di dashboard SmartTracer.
>
> **Arsitektur:** Laravel Controller → Service → Repository → Cube.js → PostgreSQL OLAP
>
> **Pre-Aggregation yang Tersedia (di-cache Redis, refresh harian):**
> - `FactTracerStudy.utama` — KPI utama, distribusi status, tren tahun lulus
> - `FactTracerStudy.distribusi_masa_tunggu` — rentang masa tunggu kerja
> - `FactTracerStudy.distribusi_gaji` — avg/min/max gaji per prodi & status
> - `FactTracerStudy.distribusi_kesesuaian` — kesesuaian bidang & level
> - `FactTracerStudy.sebaran_instansi_lokasi` — jenis & lokasi instansi
> - `FactTracerStudy.distribusi_wirausaha` — tingkat & lokasi wirausaha
> - `FactTracerStudy.distribusi_studi_lanjut` — perguruan tinggi, prodi, sumber biaya
> - `FactMultiSelect.per_indikator` — multi-select evaluasi (alasan, dll)
> - `FactRangeEvaluasi.per_indikator` — rating kompetensi & metode pembelajaran
>
> **Filter Global (tersedia di semua endpoint):**
> | Parameter | Cube Member | Contoh |
> |---|---|---|
> | `jenjang` | `DimProdi.jenjang` | `"D3"`, `"D4"` |
> | `jurusan` | `DimProdi.jurusan` | `"Teknik Informatika"` |
> | `nama_prodi` | `DimProdi.nama_prodi` | `"Teknik Elektronika"` |
> | `tahun_lulus` | `DimAlumni.tahun_lulus` | `"2023"` |
> | `minggu_snapshot` | `DimWaktu.minggu_snapshot` | `"W-48"` |
>
> **Konvensi Drill-Down & Bandingkan:**
> Setiap segmen chart yang bisa diklik (pie slice, bar segment, bar row) memanggil
> endpoint `/drill-down` dengan parameter yang sesuai. Halaman "Bandingkan Prodi"
> memiliki endpoint `/bandingkan` tersendiri per segmen KPI.

---

## Daftar Endpoint

| # | Segmen | Endpoint |
|---|--------|----------|
| 1 | Tingkat Partisipasi Alumni | `GET /api/dashboard/partisipasi/bar` |
| 2 | Status Kelengkapan Survei | `GET /api/dashboard/partisipasi/donut` |
| 3 | Partisipasi Antar Periode (Tren) | `GET /api/dashboard/partisipasi/tren` |
| 4 | Tingkat Keterserapan Lulusan | `GET /api/dashboard/keterserapan/bar` |
| | | `GET /api/dashboard/keterserapan/pie` |
| | | `GET /api/dashboard/keterserapan/drill-down` |
| | | `GET /api/dashboard/keterserapan/bandingkan` |
| 5 | Masa Tunggu Kerja Lulusan | `GET /api/dashboard/masa-tunggu/bar` |
| | | `GET /api/dashboard/masa-tunggu/distribusi` |
| | | `GET /api/dashboard/masa-tunggu/drill-down` |
| | | `GET /api/dashboard/masa-tunggu/bandingkan` |
| 6 | Kesesuaian Bidang Kerja | `GET /api/dashboard/kesesuaian/bar` |
| | | `GET /api/dashboard/kesesuaian/pie` |
| | | `GET /api/dashboard/kesesuaian/alasan` |
| | | `GET /api/dashboard/kesesuaian/drill-down` |
| | | `GET /api/dashboard/kesesuaian/bandingkan` |
| 7 | Penerimaan Lulusan Berwirausaha | `GET /api/dashboard/wirausaha/bar` |
| | | `GET /api/dashboard/wirausaha/pie` |
| | | `GET /api/dashboard/wirausaha/drill-down` |
| | | `GET /api/dashboard/wirausaha/bandingkan` |
| 8 | Pendapatan Lulusan | `GET /api/dashboard/pendapatan/per-prodi` |
| | | `GET /api/dashboard/pendapatan/distribusi` |
| | | `GET /api/dashboard/pendapatan/drill-down` |
| | | `GET /api/dashboard/pendapatan/bandingkan` |
| 9 | Analisis Gap Kompetensi | `GET /api/dashboard/kompetensi/gap` |
| | | `GET /api/dashboard/kompetensi/gap/bandingkan` |
| 10 | Analisis Metode Pembelajaran | `GET /api/dashboard/kompetensi/metode` |
| | | `GET /api/dashboard/kompetensi/metode/bandingkan` |
| 11 | Distribusi Sumber Pembiayaan | `GET /api/dashboard/pembiayaan/pie` |
| | | `GET /api/dashboard/pembiayaan/per-prodi` |
| | | `GET /api/dashboard/pembiayaan/bandingkan` |
| 12 | Sebaran Instansi & Lokasi Kerja | `GET /api/dashboard/instansi/jenis` |
| | | `GET /api/dashboard/instansi/tingkat` |
| | | `GET /api/dashboard/instansi/lokasi` |
| | | `GET /api/dashboard/instansi/drill-down` |
| | | `GET /api/dashboard/instansi/bandingkan` |
| 13 | Perbandingan KPI Lintas Prodi | `GET /api/dashboard/kpi/per-prodi` |
| Meta | Filter & Snapshot | `GET /api/dashboard/meta/filter-options` |

---

## KPI 1 — Tingkat Partisipasi Alumni

**Grafik:** Stacked bar horizontal per prodi (submitted vs belum)

### `GET /api/dashboard/partisipasi/bar`

**Query params (semua opsional):**
| Param | Tipe | Keterangan |
|---|---|---|
| `jenjang` | string | Filter jenjang (D3/D4) |
| `jurusan` | string | Filter jurusan |
| `nama_prodi` | string | Filter nama prodi |
| `minggu_snapshot` | string | Filter snapshot |

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimProdi.kode_prodi',
    ],
    'filters' => $this->buildGlobalFilters(jenjang: $jenjang, jurusan: $jurusan, namaProdi: $namaProdi, mingguSnapshot: $mingguSnapshot),
    'order'   => [['DimProdi.nama_prodi', 'asc']],
]);
```

**Pre-agg:** `FactTracerStudy.utama` ✅

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": { "jenjang": "D3" },
    "data": [
      {
        "kode_prodi": "TI",
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D3",
        "jurusan": "Teknik Informatika",
        "count_submit": 95,
        "total_assigned": 120,
        "pct_partisipasi": 79.2
      }
    ]
  }
}
```

> **Catatan:** `count_alumni` dari DW = yang sudah submit. `total_assigned` dari OLTP terpisah.
> Persentase = `count_alumni / total_assigned * 100`.

---

## KPI 2 — Status Kelengkapan Pengisian Survei

**Grafik:** Donut chart (Tidak Lengkap / Lengkap / Tidak Mengisi)

### `GET /api/dashboard/partisipasi/donut`

> Data dari OLTP, bukan Cube.js.

**Cara panggil (OLTP):**
```php
DB::connection('oltp')
    ->table('tracer_oltp.responses')
    ->selectRaw("
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as lengkap,
        SUM(CASE WHEN status = 'draft'     THEN 1 ELSE 0 END) as tidak_lengkap,
        COUNT(*) as total
    ")
    ->first();
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "lengkap": 504,
    "tidak_lengkap": 87,
    "tidak_mengisi": 209,
    "total_assigned": 800
  }
}
```

---

## KPI 3 — Partisipasi Alumni Antar Periode (Tren)

**Grafik:** Line chart tren partisipasi per tahun snapshot

### `GET /api/dashboard/partisipasi/tren`

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `minggu_snapshot`

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimWaktu.tahun_snapshot',
        'DimProdi.jenjang',
    ],
    'filters' => $this->buildGlobalFilters(jenjang: $jenjang, jurusan: $jurusan, namaProdi: $namaProdi),
    'order'   => [['DimWaktu.tahun_snapshot', 'asc']],
]);
```

**Pre-agg:** `FactTracerStudy.utama` ✅

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      { "tahun_snapshot": "2022", "count_alumni": 312 },
      { "tahun_snapshot": "2023", "count_alumni": 389 },
      { "tahun_snapshot": "2024", "count_alumni": 451 },
      { "tahun_snapshot": "2025", "count_alumni": 504 }
    ]
  }
}
```

---

## KPI 5 — Masa Tunggu Kerja Lulusan

**Grafik kiri:** Bar "% lulusan masa tunggu ≤ 6 bulan" per prodi
**Grafik kanan:** Bar horizontal distribusi rentang masa tunggu (0-3, 3-6, >6 bulan)

---

### `GET /api/dashboard/masa-tunggu/bar`

Persentase lulusan yang dapat kerja dalam ≤ 6 bulan, per prodi.

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures' => [
        'FactTracerStudy.count_alumni',
        'FactTracerStudy.count_terserap',
        'FactTracerStudy.count_masa_tunggu_cepat',
        'FactTracerStudy.avg_masa_tunggu_bekerja',
    ],
    'dimensions' => [
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => $this->buildGlobalFilters(...),
]);
```

**Pre-agg:** `FactTracerStudy.utama` ✅

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "jurusan": "Teknik Informatika",
        "tahun_lulus": "2023",
        "count_alumni": 95,
        "count_terserap": 81,
        "count_masa_tunggu_cepat": 62,
        "pct_cepat": 76.5,
        "avg_masa_tunggu_bekerja": 3.8
      }
    ]
  }
}
```

---

### `GET /api/dashboard/masa-tunggu/distribusi`

Distribusi rentang masa tunggu (0-3, 3-6, >6 bulan).

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.distribusi_masa_tunggu` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures' => [
        'FactTracerStudy.count_tunggu_0_3_bulan',
        'FactTracerStudy.count_tunggu_3_6_bulan',
        'FactTracerStudy.count_tunggu_lebih_6_bulan',
        'FactTracerStudy.avg_masa_tunggu_bekerja',
        'FactTracerStudy.min_masa_tunggu_bekerja',
        'FactTracerStudy.max_masa_tunggu_bekerja',
    ],
    'dimensions' => [
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        namaProdi:      $namaProdi,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
    ),
    'order' => [['DimProdi.nama_prodi', 'asc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "tahun_lulus": "2023",
        "count_tunggu_0_3_bulan": 45,
        "count_tunggu_3_6_bulan": 38,
        "count_tunggu_lebih_6_bulan": 22,
        "avg_masa_tunggu_bekerja": 4.2,
        "min_masa_tunggu_bekerja": 0,
        "max_masa_tunggu_bekerja": 18
      }
    ]
  }
}
```

---

### `GET /api/dashboard/masa-tunggu/drill-down`

List alumni berdasarkan rentang masa tunggu yang diklik.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `rentang` | string | `"0-3"`, `"3-6"`, `">6"` — wajib diisi |
| `nama_prodi` | string | Filter opsional |
| `jenjang` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |
| `search` | string | Cari nama / NIM |
| `page` | int | Default: 1 |
| `per_page` | int | Default: 15, max: 100 |

> **Note:** Tidak pakai pre-agg — data individual alumni.

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "rentang": "0-3",
    "filters": { "nama_prodi": "Teknik Informatika" },
    "pagination": { "page": 1, "per_page": 15, "total_on_page": 15 },
    "data": [
      {
        "nama": "Eko Prasetyo",
        "nim": "3240006",
        "nama_prodi": "Teknik Pendingin",
        "jenjang": "D3",
        "tahun_lulus": "2024",
        "masa_tunggu_bekerja": 2,
        "status": "Bekerja"
      }
    ]
  }
}
```
### `GET /api/dashboard/masa-tunggu/bandingkan`

Perbandingan distribusi masa tunggu per prodi — count rentang + avg masa tunggu.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `prodi[]` | array | Nama prodi yang dipilih chip (kosong = semua) |
| `jenjang` | string | Filter opsional |
| `jurusan` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |

**Pre-agg:** `FactTracerStudy.distribusi_masa_tunggu` ✅

**Cara panggil (Repository):**
```php
$extra = [];
if (!empty($prodiFilter)) {
    $extra[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
}

$this->cube->load([
    'measures' => [
        'FactTracerStudy.count_tunggu_0_3_bulan',
        'FactTracerStudy.count_tunggu_3_6_bulan',
        'FactTracerStudy.count_tunggu_lebih_6_bulan',
        'FactTracerStudy.avg_masa_tunggu_bekerja',
        'FactTracerStudy.min_masa_tunggu_bekerja',
        'FactTracerStudy.max_masa_tunggu_bekerja',
    ],
    'dimensions' => [
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
        extra:          $extra,
    ),
    'order' => [['DimProdi.nama_prodi', 'asc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": { "jenjang": "D4" },
    "prodi_list": ["Teknik Informatika", "Teknik Elektronika"],
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "tahun_lulus": "2023",
        "count_tunggu_0_3_bulan": 45,
        "count_tunggu_3_6_bulan": 38,
        "count_tunggu_lebih_6_bulan": 22,
        "avg_masa_tunggu_bekerja": 4.2,
        "min_masa_tunggu_bekerja": 0,
        "max_masa_tunggu_bekerja": 18,
        "pct_cepat": 79.6
      }
    ]
  }
}
```
---

## KPI 6 — Kesesuaian Bidang Kerja

**Grafik kiri:** Grouped bar per prodi (sesuai vs tidak sesuai)
**Grafik kanan:** Pie distribusi tingkat kesesuaian (Sangat Erat / Erat / dll)
**Grafik bawah:** Bar horizontal alasan kerja tidak sesuai (dari `FactMultiSelect`)

---

### `GET /api/dashboard/kesesuaian/bar`

Bar per prodi: sesuai vs tidak sesuai bidang.

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.distribusi_kesesuaian` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures' => [
        'FactTracerStudy.count_alumni',
        'FactTracerStudy.count_sesuai_bidang',
        'FactTracerStudy.count_tidak_sesuai_bidang',
    ],
    'dimensions' => [
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        namaProdi:      $namaProdi,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
    ),
    'order' => [['DimProdi.nama_prodi', 'asc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "tahun_lulus": "2023",
        "count_alumni": 95,
        "count_sesuai_bidang": 71,
        "count_tidak_sesuai_bidang": 24,
        "pct_sesuai": 74.7,
        "pct_tidak_sesuai": 25.3
      }
    ]
  }
}
```

---

### `GET /api/dashboard/kesesuaian/pie`

Distribusi tingkat kesesuaian (Sangat Erat, Erat, Cukup Erat, Kurang Erat, Tidak Sama Sekali).

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.distribusi_kesesuaian` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => ['DimKesesuaianBidang.label'],
    'filters' => array_merge(
        $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        ),
        // hanya alumni bekerja (status_alumni_sk = 1)
        [['member' => 'FactTracerStudy.status_alumni_sk', 'operator' => 'equals', 'values' => ['1']]],
    ),
    'order' => [['FactTracerStudy.count_alumni', 'desc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "chart_type": "pie",
    "filters": {},
    "total": 218,
    "data": [
      { "label": "Sangat Erat",       "count": 98, "pct": 44.9 },
      { "label": "Erat",              "count": 76, "pct": 34.9 },
      { "label": "Cukup Erat",        "count": 52, "pct": 23.9 },
      { "label": "Kurang Erat",       "count": 34, "pct": 15.6 },
      { "label": "Tidak Sama Sekali", "count": 18, "pct": 8.3  }
    ]
  }
}
```

---

### `GET /api/dashboard/kesesuaian/alasan`

Bar horizontal alasan kerja tidak sesuai bidang (dari `FactMultiSelect`).

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactMultiSelect.per_indikator` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactMultiSelect.count_pilihan'],
    'dimensions' => [
        'DimIndikatorEvaluasi.label_pertanyaan',
        'DimIndikatorEvaluasi.kode_field',
    ],
    'filters' => array_merge(
        $this->buildGlobalFilters(...),
        [['member' => 'DimIndikatorEvaluasi.kategori_pertanyaan',
          'operator' => 'equals', 'values' => ['AlasanKerjaTdkSesuai']]]
    ),
    'order' => [['FactMultiSelect.count_pilihan', 'desc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      { "kode_field": "f1601", "label": "Belum ada lowongan sesuai", "count": 87 },
      { "kode_field": "f1602", "label": "Gaji tidak sesuai harapan", "count": 64 },
      { "kode_field": "f1603", "label": "Kompetensi kurang",         "count": 41 }
    ]
  }
}
```

---

### `GET /api/dashboard/kesesuaian/drill-down`

List alumni berdasarkan kategori kesesuaian yang diklik.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `kesesuaian_sk` | int | SK kesesuaian bidang (1-5). Wajib. |
| `jenjang` | string | Filter opsional |
| `nama_prodi` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |
| `search` | string | Cari nama / NIM |
| `page` | int | Default: 1 |
| `per_page` | int | Default: 15, max: 100 |

> **Note:** Tidak pakai pre-agg — data individual alumni.

**Cara panggil (Repository):**
```php
$filters = array_merge(
    $this->buildGlobalFilters(
        jenjang:        $jenjang,
        namaProdi:      $namaProdi,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
    ),
    [['member' => 'FactTracerStudy.kesesuaian_bidang_sk', 'operator' => 'equals', 'values' => [(string) $kesesuaianSk]]],
);

if ($search) {
    $filters[] = ['member' => 'DimAlumni.nama', 'operator' => 'contains', 'values' => [$search]];
}

$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimAlumni.nama',
        'DimAlumni.nim',
        'DimProdi.nama_prodi',
        'DimProdi.jenjang',
        'DimAlumni.tahun_lulus',
        'DimKesesuaianBidang.label',
        'DimStatusAlumni.label',
    ],
    'filters' => $filters,
    'order'   => [['DimAlumni.nama', 'asc']],
    'limit'   => $perPage,
    'offset'  => ($page - 1) * $perPage,
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "kesesuaian_label": "Sangat Erat",
    "filters": {},
    "pagination": { "page": 1, "per_page": 15, "total_on_page": 15 },
    "data": [
      {
        "nama": "Putri Ayu",
        "nim": "3210001",
        "nama_prodi": "Teknik Telekomunikasi",
        "jenjang": "D3",
        "tahun_lulus": "2021",
        "kesesuaian_bidang": "Sangat Erat",
        "status": "Bekerja"
      }
    ]
  }
}
```

---

## KPI 7 — Penerimaan Lulusan Berwirausaha

**Grafik kiri:** Bar per prodi (count wirausaha per tahun lulus)
**Grafik kanan:** Pie distribusi tingkat wirausaha (Lokal / Nasional / Internasional)

---

### `GET /api/dashboard/wirausaha/bar`

Jumlah alumni wirausaha per prodi per tahun lulus.

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.distribusi_wirausaha` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures' => [
        'FactTracerStudy.count_alumni',
        'FactTracerStudy.avg_masa_tunggu_wirausaha',
    ],
    'dimensions' => [
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => array_merge(
        $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        ),
        // hanya alumni wirausaha (status_alumni_sk = 3)
        [['member' => 'FactTracerStudy.status_alumni_sk', 'operator' => 'equals', 'values' => ['3']]],
    ),
    'order' => [['DimProdi.nama_prodi', 'asc'], ['DimAlumni.tahun_lulus', 'asc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "tahun_lulus": "2023",
        "count_alumni": 95,
        "count_wirausaha": 12,
        "pct_wirausaha": 12.6,
        "avg_masa_tunggu_wirausaha": 5.3
      }
    ]
  }
}
```

---

### `GET /api/dashboard/wirausaha/pie`

Distribusi tingkat wirausaha (Lokal, Nasional, Internasional) dan sebaran kota/provinsi.

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.distribusi_wirausaha` ✅

**Cara panggil (Repository):**
```php
// Query 1: distribusi tingkat wirausaha
$tingkat = $this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => ['DimWirausaha.label_tingkat_instansi'],
    'filters'    => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        namaProdi:      $namaProdi,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
    ),
    'order' => [['FactTracerStudy.count_alumni', 'desc']],
]);

// Query 2: sebaran kota alumni wirausaha
$kota = $this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimWirausaha.nama_kota',
        'DimWirausaha.nama_provinsi',
    ],
    'filters' => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        namaProdi:      $namaProdi,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
    ),
    'order' => [['FactTracerStudy.count_alumni', 'desc']],
    'limit' => 15,
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "chart_type": "pie",
    "filters": {},
    "total": 87,
    "tingkat": [
      { "label": "Lokal",         "count": 52, "pct": 59.8 },
      { "label": "Nasional",      "count": 28, "pct": 32.2 },
      { "label": "Internasional", "count": 7,  "pct": 8.0  }
    ],
    "sebaran_kota": [
      { "nama_kota": "Bandung",  "nama_provinsi": "Jawa Barat",  "count": 34 },
      { "nama_kota": "Jakarta",  "nama_provinsi": "DKI Jakarta", "count": 18 }
    ]
  }
}
```

---

### `GET /api/dashboard/wirausaha/drill-down`

List alumni wirausaha berdasarkan tingkat yang diklik.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `tingkat` | string | `"Lokal"`, `"Nasional"`, `"Internasional"`. Wajib. |
| `jenjang` | string | Filter opsional |
| `nama_prodi` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |
| `search` | string | Cari nama / NIM |
| `page` | int | Default: 1 |
| `per_page` | int | Default: 15, max: 100 |

> **Note:** Tidak pakai pre-agg — data individual alumni.

**Cara panggil (Repository):**
```php
$filters = array_merge(
    $this->buildGlobalFilters(
        jenjang:        $jenjang,
        namaProdi:      $namaProdi,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
    ),
    [
        ['member' => 'FactTracerStudy.status_alumni_sk', 'operator' => 'equals', 'values' => ['3']],
        ['member' => 'DimWirausaha.label_tingkat_instansi', 'operator' => 'equals', 'values' => [$tingkat]],
    ],
);

if ($search) {
    $filters[] = ['member' => 'DimAlumni.nama', 'operator' => 'contains', 'values' => [$search]];
}

$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimAlumni.nama',
        'DimAlumni.nim',
        'DimProdi.nama_prodi',
        'DimProdi.jenjang',
        'DimAlumni.tahun_lulus',
        'DimWirausaha.nama_kota',
        'DimWirausaha.nama_provinsi',
        'DimWirausaha.label_tingkat_instansi',
        'FactTracerStudy.masa_tunggu_wirausaha',
    ],
    'filters' => $filters,
    'order'   => [['DimAlumni.nama', 'asc']],
    'limit'   => $perPage,
    'offset'  => ($page - 1) * $perPage,
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "tingkat": "Lokal",
    "filters": {},
    "pagination": { "page": 1, "per_page": 15, "total_on_page": 12 },
    "data": [
      {
        "nama": "Siti Nurhaliza",
        "nim": "4200003",
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "tahun_lulus": "2020",
        "nama_kota": "Bandung",
        "nama_provinsi": "Jawa Barat",
        "tingkat_instansi": "Lokal",
        "masa_tunggu_wirausaha": 4
      }
    ]
  }
}
```

---

### `GET /api/dashboard/wirausaha/bandingkan`

Perbandingan wirausaha per prodi — count, persentase, dan rata-rata masa tunggu.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `prodi[]` | array | Nama prodi yang dipilih chip (kosong = semua) |
| `jenjang` | string | Filter opsional |
| `jurusan` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |

**Pre-agg:** `FactTracerStudy.distribusi_wirausaha` ✅

**Cara panggil (Repository):**
```php
$extra = [];
if (!empty($prodiFilter)) {
    $extra[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
}

$this->cube->load([
    'measures' => [
        'FactTracerStudy.count_alumni',
        'FactTracerStudy.avg_masa_tunggu_wirausaha',
        'FactTracerStudy.min_masa_tunggu_wirausaha',
        'FactTracerStudy.max_masa_tunggu_wirausaha',
    ],
    'dimensions' => [
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimWirausaha.label_tingkat_instansi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
        extra:          $extra,
    ),
    'order' => [
        ['DimProdi.nama_prodi',              'asc'],
        ['DimWirausaha.label_tingkat_instansi', 'asc'],
    ],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": { "jenjang": "D4" },
    "prodi_list": ["Teknik Informatika", "Teknik Elektronika"],
    "chart": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "total_alumni": 95,
        "count_wirausaha": 12,
        "pct_wirausaha": 12.6,
        "avg_masa_tunggu_wirausaha": 5.3,
        "tingkat": [
          { "label": "Lokal",    "count": 7,  "pct": 58.3 },
          { "label": "Nasional", "count": 4,  "pct": 33.3 },
          { "label": "Internasional", "count": 1, "pct": 8.3 }
        ]
      }
    ]
  }
}
```

## KPI 9 — Analisis Gap Kompetensi Lulusan

**Grafik kiri:** Radar chart (skor kompetensi saat lulus vs dibutuhkan di tempat kerja)
**Grafik kanan:** Bar horizontal gap per indikator (Kompetensi_B − Kompetensi_A)

### `GET /api/dashboard/kompetensi/gap`

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactRangeEvaluasi.per_indikator` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => [
        'FactRangeEvaluasi.avg_skor',
        'FactRangeEvaluasi.count',
    ],
    'dimensions' => [
        'DimIndikatorEvaluasi.label_pertanyaan',
        'DimIndikatorEvaluasi.kode_field',
        'DimIndikatorEvaluasi.kategori_pertanyaan', // Kompetensi_A atau Kompetensi_B
    ],
    'filters' => array_merge(
        $this->buildGlobalFilters(...),
        [['member' => 'DimIndikatorEvaluasi.kategori_pertanyaan',
          'operator' => 'in', 'values' => ['Kompetensi_A', 'Kompetensi_B']]]
    ),
    'order' => [['DimIndikatorEvaluasi.kode_field', 'asc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      {
        "kode_field": "f1761",
        "label": "Etika",
        "skor_lulus": 3.8,
        "skor_dibutuhkan": 4.5,
        "gap": 0.7,
        "count_responden": 218
      },
      {
        "kode_field": "f1762",
        "label": "Komunikasi",
        "skor_lulus": 3.5,
        "skor_dibutuhkan": 4.3,
        "gap": 0.8,
        "count_responden": 218
      }
    ]
  }
}
```

> **Catatan:** `gap` positif = kompetensi dibutuhkan > dikuasai (perlu ditingkatkan).
> `gap` = `skor_dibutuhkan (Kompetensi_B) - skor_lulus (Kompetensi_A)`.

### `GET /api/dashboard/kompetensi/gap/bandingkan`

Perbandingan gap kompetensi per prodi — skor rata-rata Kompetensi_A vs Kompetensi_B per indikator.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `prodi[]` | array | Nama prodi yang dipilih chip (kosong = semua) |
| `jenjang` | string | Filter opsional |
| `jurusan` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |

**Pre-agg:** `FactRangeEvaluasi.per_indikator` ✅

**Cara panggil (Repository):**
```php
$extra = [];
if (!empty($prodiFilter)) {
    $extra[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
}

$this->cube->load([
    'measures'   => [
        'FactRangeEvaluasi.avg_skor',
        'FactRangeEvaluasi.count',
    ],
    'dimensions' => [
        'DimIndikatorEvaluasi.kode_field',
        'DimIndikatorEvaluasi.label_pertanyaan',
        'DimIndikatorEvaluasi.kategori_pertanyaan', // Kompetensi_A atau Kompetensi_B
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => array_merge(
        $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        ),
        [['member' => 'DimIndikatorEvaluasi.kategori_pertanyaan',
          'operator' => 'in', 'values' => ['Kompetensi_A', 'Kompetensi_B']]],
    ),
    'order' => [
        ['DimProdi.nama_prodi',             'asc'],
        ['DimIndikatorEvaluasi.kode_field', 'asc'],
    ],
]);
// Service memisahkan baris Kompetensi_A dan Kompetensi_B per (nama_prodi, kode_field),
// lalu menghitung gap = avg_skor_B - avg_skor_A.
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": { "jenjang": "D4" },
    "prodi_list": ["Teknik Informatika", "Teknik Elektronika"],
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "indikator": [
          {
            "kode_field": "f1761",
            "label": "Etika",
            "skor_lulus": 3.8,
            "skor_dibutuhkan": 4.5,
            "gap": 0.7,
            "count_responden": 81
          },
          {
            "kode_field": "f1762",
            "label": "Komunikasi",
            "skor_lulus": 3.5,
            "skor_dibutuhkan": 4.3,
            "gap": 0.8,
            "count_responden": 81
          }
        ]
      }
    ]
  }
}
```
---

## KPI 10 — Analisis Metode Pembelajaran

**Grafik:** Radar chart skor metode pembelajaran (Perkuliahan, Demonstrasi, Praktikum, dll)

### `GET /api/dashboard/kompetensi/metode`

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactRangeEvaluasi.per_indikator` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => [
        'FactRangeEvaluasi.avg_skor',
        'FactRangeEvaluasi.count',
    ],
    'dimensions' => [
        'DimIndikatorEvaluasi.label_pertanyaan',
        'DimIndikatorEvaluasi.kode_field',
    ],
    'filters' => array_merge(
        $this->buildGlobalFilters(...),
        [['member' => 'DimIndikatorEvaluasi.kategori_pertanyaan',
          'operator' => 'equals', 'values' => ['MetodePembelajaran']]]
    ),
    'order' => [['DimIndikatorEvaluasi.kode_field', 'asc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      { "kode_field": "f21", "label": "Perkuliahan",   "avg_skor": 4.1, "count_responden": 218 },
      { "kode_field": "f22", "label": "Demonstrasi",   "avg_skor": 3.7, "count_responden": 218 },
      { "kode_field": "f23", "label": "Praktikum",     "avg_skor": 4.3, "count_responden": 218 },
      { "kode_field": "f24", "label": "Magang/PKL",    "avg_skor": 4.0, "count_responden": 218 }
    ]
  }
}
```
### `GET /api/dashboard/kompetensi/metode/bandingkan`

Perbandingan skor metode pembelajaran per prodi.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `prodi[]` | array | Nama prodi yang dipilih chip (kosong = semua) |
| `jenjang` | string | Filter opsional |
| `jurusan` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |

**Pre-agg:** `FactRangeEvaluasi.per_indikator` ✅

**Cara panggil (Repository):**
```php
$extra = [];
if (!empty($prodiFilter)) {
    $extra[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
}

$this->cube->load([
    'measures'   => [
        'FactRangeEvaluasi.avg_skor',
        'FactRangeEvaluasi.count',
    ],
    'dimensions' => [
        'DimIndikatorEvaluasi.kode_field',
        'DimIndikatorEvaluasi.label_pertanyaan',
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus',
    ],
    'filters' => array_merge(
        $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        ),
        [['member' => 'DimIndikatorEvaluasi.kategori_pertanyaan',
          'operator' => 'equals', 'values' => ['MetodePembelajaran']]],
    ),
    'order' => [
        ['DimProdi.nama_prodi',             'asc'],
        ['DimIndikatorEvaluasi.kode_field', 'asc'],
    ],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": { "jenjang": "D4" },
    "prodi_list": ["Teknik Informatika", "Teknik Elektronika"],
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "metode": [
          { "kode_field": "f21", "label": "Perkuliahan", "avg_skor": 4.1, "count_responden": 81 },
          { "kode_field": "f22", "label": "Demonstrasi", "avg_skor": 3.7, "count_responden": 81 },
          { "kode_field": "f23", "label": "Praktikum",   "avg_skor": 4.3, "count_responden": 81 },
          { "kode_field": "f24", "label": "Magang/PKL",  "avg_skor": 4.0, "count_responden": 81 }
        ]
      }
    ]
  }
}
```
---

## KPI 11 — Distribusi Sumber Pembiayaan Kuliah

**Grafik kiri:** Pie distribusi sumber biaya
**Grafik kanan:** Bar stacked per prodi (distribusi sumber biaya per prodi)

---

### `GET /api/dashboard/pembiayaan/pie`

Distribusi sumber pembiayaan kuliah.

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => ['DimAlumni.label_sumber_biaya_dipolban'],
    'filters'    => $this->buildGlobalFilters(...),
    'order'      => [['FactTracerStudy.count_alumni', 'desc']],
]);
```

**Pre-agg:** `FactTracerStudy.utama` ✅

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "chart_type": "pie",
    "filters": {},
    "total": 504,
    "data": [
      { "sumber_biaya": "Beasiswa",      "count": 187, "pct": 37.1 },
      { "sumber_biaya": "Orang Tua",     "count": 172, "pct": 34.1 },
      { "sumber_biaya": "Biaya Sendiri", "count": 145, "pct": 28.8 }
    ]
  }
}
```

---

### `GET /api/dashboard/pembiayaan/per-prodi`

Distribusi sumber pembiayaan per prodi (bar stacked).

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.distribusi_studi_lanjut` (partial) ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimAlumni.label_sumber_biaya_dipolban',
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
    ],
    'filters' => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        namaProdi:      $namaProdi,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
    ),
    'order' => [
        ['DimProdi.nama_prodi',                    'asc'],
        ['FactTracerStudy.count_alumni',            'desc'],
    ],
]);
// Service group by nama_prodi → sumber[] dengan count + pct per prodi.
```


**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "total": 95,
        "sumber": [
          { "label": "Beasiswa",      "count": 42, "pct": 44.2 },
          { "label": "Orang Tua",     "count": 31, "pct": 32.6 },
          { "label": "Biaya Sendiri", "count": 22, "pct": 23.2 }
        ]
      }
    ]
  }
}
```
### `GET /api/dashboard/pembiayaan/bandingkan`

Perbandingan distribusi sumber pembiayaan per prodi yang dipilih.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `prodi[]` | array | Nama prodi yang dipilih chip (kosong = semua) |
| `jenjang` | string | Filter opsional |
| `jurusan` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |

**Pre-agg:** `FactTracerStudy.utama` ✅

**Cara panggil (Repository):**
```php
$extra = [];
if (!empty($prodiFilter)) {
    $extra[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
}

$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimAlumni.label_sumber_biaya_dipolban',
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
    ],
    'filters' => $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
        extra:          $extra,
    ),
    'order' => [
        ['DimProdi.nama_prodi',         'asc'],
        ['FactTracerStudy.count_alumni', 'desc'],
    ],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": { "jenjang": "D4" },
    "prodi_list": ["Teknik Informatika", "Teknik Elektronika"],
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "total": 95,
        "sumber": [
          { "label": "Beasiswa",      "count": 42, "pct": 44.2 },
          { "label": "Orang Tua",     "count": 31, "pct": 32.6 },
          { "label": "Biaya Sendiri", "count": 22, "pct": 23.2 }
        ]
      }
    ]
  }
}
```
---

## KPI 12 — Sebaran Instansi & Lokasi Kerja

**Grafik kiri:** Pie jenis instansi (Swasta, BUMN, Pemerintah, dll)
**Grafik kanan:** Bar per prodi distribusi tingkat instansi (Lokal/Nasional/Internasional)
**Grafik bawah:** Top kota/provinsi tempat kerja alumni

---

### `GET /api/dashboard/instansi/jenis`

Distribusi jenis instansi tempat alumni bekerja.

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.sebaran_instansi_lokasi` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => ['DimPerusahaan.label_jenis_perusahaan'],
    'filters'    => array_merge(
        $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        ),
        // Hanya alumni bekerja (bukan wirausaha, bukan null)
        [['member' => 'FactTracerStudy.status_alumni_sk', 'operator' => 'equals', 'values' => ['1']]],
    ),
    'order' => [['FactTracerStudy.count_alumni', 'desc']],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "chart_type": "pie",
    "filters": {},
    "total": 218,
    "data": [
      { "jenis": "Perusahaan Swasta",   "count": 134, "pct": 61.5 },
      { "jenis": "BUMN / BUMD",         "count": 52,  "pct": 23.9 },
      { "jenis": "Instansi Pemerintah", "count": 28,  "pct": 12.8 },
      { "jenis": "Wiraswasta/Sendiri",  "count": 4,   "pct": 1.8  }
    ]
  }
}
```

---

### `GET /api/dashboard/instansi/tingkat`

Distribusi tingkat instansi per prodi (Lokal, Nasional, Internasional).

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`

**Pre-agg:** `FactTracerStudy.sebaran_instansi_lokasi` ✅

**Cara panggil (Repository):**
```php
$this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimPerusahaan.label_tingkat_instansi',
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
    ],
    'filters' => array_merge(
        $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        ),
        [['member' => 'FactTracerStudy.status_alumni_sk', 'operator' => 'equals', 'values' => ['1']]],
    ),
    'order' => [
        ['DimProdi.nama_prodi',                    'asc'],
        ['FactTracerStudy.count_alumni',            'desc'],
    ],
]);
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "tingkat": [
          { "label": "Lokal",         "count": 40, "pct": 55.6 },
          { "label": "Nasional",      "count": 26, "pct": 36.1 },
          { "label": "Internasional", "count": 6,  "pct": 8.3  }
        ]
      }
    ]
  }
}
```
### `GET /api/dashboard/instansi/bandingkan`

Perbandingan sebaran jenis dan tingkat instansi per prodi yang dipilih.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `prodi[]` | array | Nama prodi yang dipilih chip (kosong = semua) |
| `jenjang` | string | Filter opsional |
| `jurusan` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |

**Pre-agg:** `FactTracerStudy.sebaran_instansi_lokasi` ✅

**Cara panggil (Repository):**
```php
$extra = [];
if (!empty($prodiFilter)) {
    $extra[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
}

$baseFilters = array_merge(
    $this->buildGlobalFilters(
        jenjang:        $jenjang,
        jurusan:        $jurusan,
        tahunLulus:     $tahunLulus,
        mingguSnapshot: $mingguSnapshot,
        extra:          $extra,
    ),
    [['member' => 'FactTracerStudy.status_alumni_sk', 'operator' => 'equals', 'values' => ['1']]],
);

// Query 1: distribusi per jenis instansi per prodi
$jenis = $this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimPerusahaan.label_jenis_perusahaan',
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
    ],
    'filters' => $baseFilters,
    'order'   => [['DimProdi.nama_prodi', 'asc'], ['FactTracerStudy.count_alumni', 'desc']],
]);

// Query 2: distribusi per tingkat instansi per prodi
$tingkat = $this->cube->load([
    'measures'   => ['FactTracerStudy.count_alumni'],
    'dimensions' => [
        'DimPerusahaan.label_tingkat_instansi',
        'DimProdi.jenjang',
        'DimProdi.jurusan',
        'DimProdi.nama_prodi',
    ],
    'filters' => $baseFilters,
    'order'   => [['DimProdi.nama_prodi', 'asc'], ['FactTracerStudy.count_alumni', 'desc']],
]);
// Service menggabungkan kedua query per nama_prodi.
```

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": { "jenjang": "D4" },
    "prodi_list": ["Teknik Informatika", "Teknik Elektronika"],
    "data": [
      {
        "nama_prodi": "Teknik Informatika",
        "jenjang": "D4",
        "total": 72,
        "jenis": [
          { "label": "Perusahaan Swasta",   "count": 44, "pct": 61.1 },
          { "label": "BUMN / BUMD",         "count": 20, "pct": 27.8 },
          { "label": "Instansi Pemerintah", "count": 8,  "pct": 11.1 }
        ],
        "tingkat": [
          { "label": "Nasional",      "count": 38, "pct": 52.8 },
          { "label": "Lokal",         "count": 28, "pct": 38.9 },
          { "label": "Internasional", "count": 6,  "pct": 8.3  }
        ]
      }
    ]
  }
}
```


---

### `GET /api/dashboard/instansi/lokasi`

Top kota dan provinsi tempat kerja alumni.

**Query params (semua opsional):** `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`, `limit` (default 15)

**Pre-agg:** `FactTracerStudy.sebaran_instansi_lokasi` ✅

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "filters": {},
    "top_kota": [
      { "nama_kota": "Bandung",  "nama_provinsi": "Jawa Barat",  "count": 98 },
      { "nama_kota": "Jakarta",  "nama_provinsi": "DKI Jakarta", "count": 54 },
      { "nama_kota": "Surabaya", "nama_provinsi": "Jawa Timur",  "count": 22 }
    ],
    "top_provinsi": [
      { "nama_provinsi": "Jawa Barat",  "count": 112 },
      { "nama_provinsi": "DKI Jakarta", "count": 67  }
    ]
  }
}
```

---

### `GET /api/dashboard/instansi/drill-down`

List alumni berdasarkan jenis instansi atau tingkat yang diklik.

**Query params:**
| Param | Tipe | Keterangan |
|---|---|---|
| `jenis_instansi` | string | Label jenis instansi. Wajib jika `tingkat_instansi` kosong. |
| `tingkat_instansi` | string | `"Lokal"`, `"Nasional"`, `"Internasional"`. Wajib jika `jenis_instansi` kosong. |
| `jenjang` | string | Filter opsional |
| `nama_prodi` | string | Filter opsional |
| `tahun_lulus` | string | Filter opsional |
| `minggu_snapshot` | string | Filter opsional |
| `search` | string | Cari nama / NIM |
| `page` | int | Default: 1 |
| `per_page` | int | Default: 15, max: 100 |

**Response JSON `200`:**
```json
{
  "success": true,
  "data": {
    "jenis_instansi": "BUMN / BUMD",
    "tingkat_instansi": null,
    "filters": {},
    "pagination": { "page": 1, "per_page": 15, "total_on_page": 15 },
    "data": [
      {
        "nama": "Umar Fathoni",
        "nim": "3240004",
        "nama_prodi": "Teknik Telekomunikasi",
        "jenjang": "D3",
        "tahun_lulus": "2024",
        "nama_kota": "Bandung",
        "jenis_instansi": "BUMN / BUMD",
        "tingkat_instansi": "Nasional",
        "status": "Bekerja"
      }
    ]
  }
}
```

---
```

**Kalkulasi % di Service/Frontend:**
```js
const pct_terserap = (count_terserap / count_alumni * 100).toFixed(1)
const pct_cepat    = (count_masa_tunggu_cepat / count_terserap * 100).toFixed(1)
const pct_sesuai   = (count_sesuai_bidang / count_terserap * 100).toFixed(1)
const avg_gaji_juta = (avg_take_home_pay / 1_000_000).toFixed(1) + ' jt'
```

---


## Pre-Aggregation Summary

| Pre-agg | Melayani KPI | Measures | Dimensi Utama |
|---|---|---|---|
| `FactTracerStudy.utama` | 1, 3, 4, 11, 13 | count_alumni, count_terserap, count_masa_tunggu_cepat, avg_masa_tunggu_bekerja, avg_take_home_pay, count_sesuai/tidak_sesuai_bidang | DimProdi.*, DimStatusAlumni.label, DimAlumni.tahun_lulus, DimWaktu.tahun_snapshot + minggu_snapshot |
| `FactTracerStudy.distribusi_masa_tunggu` | 5 | count_tunggu_0/3/6_bulan, avg/min/max_masa_tunggu_bekerja | DimProdi.*, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |
| `FactTracerStudy.distribusi_gaji` | 8 | avg/min/max_take_home_pay | DimProdi.*, DimStatusAlumni.label, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |
| `FactTracerStudy.distribusi_kesesuaian` | 6, 13 | count_alumni, count_sesuai/tidak_sesuai_bidang | DimKesesuaianBidang.label, DimKesesuaianLevel.label, DimProdi.*, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |
| `FactTracerStudy.sebaran_instansi_lokasi` | 12 | count_alumni | DimPerusahaan.label_jenis/tingkat, nama_kota, nama_provinsi, DimProdi.*, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |
| `FactTracerStudy.distribusi_wirausaha` | 7 | count_alumni, avg/min/max_masa_tunggu_wirausaha | DimWirausaha.label_tingkat_instansi, nama_provinsi, nama_kota, DimProdi.*, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |
| `FactTracerStudy.distribusi_studi_lanjut` | 11 (partial) | count_alumni | DimStudiLanjut.perguruan_tinggi, program_studi, sumber_biaya, DimProdi.*, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |
| `FactMultiSelect.per_indikator` | 6 (alasan tidak sesuai) | count_pilihan, count_alumni_unik | DimIndikatorEvaluasi.*, DimProdi.*, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |
| `FactRangeEvaluasi.per_indikator` | 9, 10 | avg/min/max_skor, count | DimIndikatorEvaluasi.*, DimProdi.*, DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot |

> **Refresh strategy:** Semua pre-agg dicek setiap hari (`every: 1 day`).
> Rebuild hanya terjadi jika `MAX(tanggal_refresh)` di `dim_waktu` berubah.
> Efektif rebuild sekali seminggu mengikuti jadwal ETL.

---

## Panduan Drill-Down

Setiap klik elemen chart memanggil endpoint `/drill-down` dengan parameter berikut:

| Aksi User | Endpoint | Parameter Kunci |
|---|---|---|
| Klik slice pie keterserapan | `/keterserapan/drill-down` | `status=<label>` |
| Klik bar tahun keterserapan | `/keterserapan/drill-down` | `tahun_lulus=<tahun>` |
| Klik bar tahun + segmen tertentu | `/keterserapan/drill-down` | `tahun_lulus=<tahun>&status=<label>` |
| Klik bar rentang masa tunggu | `/masa-tunggu/drill-down` | `rentang=<0-3\|3-6\|>6>` |
| Klik slice kesesuaian | `/kesesuaian/drill-down` | `kesesuaian_sk=<1-5>` |
| Klik bar wirausaha / slice tingkat | `/wirausaha/drill-down` | `tingkat=<Lokal\|Nasional\|Internasional>` |
| Klik bar rentang gaji | `/pendapatan/drill-down` | `rentang=<\<3jt\|3-5jt\|5-10jt\|>10jt>` |
| Klik slice jenis instansi | `/instansi/drill-down` | `jenis_instansi=<label>` |
| Klik bar tingkat instansi | `/instansi/drill-down` | `tingkat_instansi=<label>` |

Semua drill-down mendukung tambahan filter: `jenjang`, `jurusan`, `nama_prodi`, `tahun_lulus`, `minggu_snapshot`, `search`, `page`, `per_page`.

---

## Catatan Dimensi — DimAlumni.js

```js
// Pastikan dimension ini ada di DimAlumni.js
label_sumber_biaya_dipolban: {
  sql: `label_sumber_biaya_dipolban`,
  type: `string`,
  description: `Label sumber biaya kuliah: Beasiswa, Orang Tua, Mandiri, dll`,
},
tahun_lulus: {
  sql: `tahun_lulus`,
  type: `string`,
},
angkatan: {
  sql: `angkatan`,
  type: `string`,
},
nama: {
  sql: `nama`,
  type: `string`,
},
nim: {
  sql: `nim`,
  type: `string`,
},
```
