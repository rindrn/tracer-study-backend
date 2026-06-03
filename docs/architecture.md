# Konsep Arsitektur Sistem Tracer Study  
## Before vs After Brainstorming (MVC → Layered + Cube.js)

Dokumen ini disusun untuk kebutuhan **pembimbing / sidang / laporan TA**.  
Fokus: perubahan pendekatan dari **MVC klasik** menuju **Layered Architecture** dengan dukungan **Cube.js** sebagai analytic layer.

---

## 1. Arsitektur Sebelum (MVC Klasik)

**Pendekatan lama (sederhana):**
- Laravel MVC
  - Controller → langsung query PostgreSQL
  - Model → tabel fact & dimension (Star Schema)
- Repository → memanggil **View / Materialized View** di PostgreSQL
- Tidak ada layer **Service** / **DTO** yang jelas
- OLAP “mini” → SQL + Materialized View

**Kelemahan:**
- Fat Controller
- Query logic bercampur di banyak tempat
- Sulit mengganti data source di masa depan

---

## 2. Arsitektur Sesudah (Layered + Cube.js)

**Pendekatan baru (best practice):**
- ✅ **Repository** → hanya memanggil Cube.js API  
- ✅ **Service** → business logic & orkestrasi  
- ✅ **DTO** → transform response jadi “clean” untuk frontend  
- ✅ Controller tetap **tipis**  

**Alur request:**
Frontend (React) → Laravel Controller → Service → Repository → **Cube.js** → PostgreSQL

---

## 3. Materialized View vs Cube.js

| Aspek                 | Materialized View (PostgreSQL) | Cube.js (Analytic Layer)              |
| --------------------- | ------------------------------ | ------------------------------------- |
| Tempat logic KPI      | SQL manual                     | JS Schema (measure + dimension)       |
| Pre-aggregation       | Manual (refresh manual)        | Otomatis (rollup)                     |
| Drill-down / filter   | Query ulang setiap kali        | Instan dari cache/pre-agg             |
| Performa dashboard    | Sedang                         | Sangat cepat                          |
| Keamanan & akses role | Harus di Laravel               | Laravel tetap kontrol (auth)          |

**Kesimpulan:**
- ❌ OLAP Cube ≠ sekadar View di PostgreSQL  
- ✅ Tapi bisa “ditiru” pakai Materialized View (level implementasi sederhana)

---

## 4. Layer Penyimpanan & Analitik (4-Layer)

- **PostgreSQL** → Data Warehouse (Star Schema)
- **Cube.js** → Analytic Layer (pre-aggregate, roll-up, multidimensional)
- **Laravel** → Backend + Security (Auth, Role, DTO)
- **React** → Visualisasi Dashboard

**Keunggulan Cube.js:**
- Pre-aggregation otomatis
- Query pakai JSON (bukan SQL raw)
- Cache & roll-up multidimensional
- Scalable untuk data besar

---

## 5. Cara Kerja Cube.js (Praktis)

**Cube.js hanya definisi (bukan pindah data).**
- Data tetap di PostgreSQL
- Cube.js = schema JS (1 file per domain, contoh: `schema/Tracer.js`)
- Isi schema:
  - `sql:` → JOIN semua fact & dimension
  - `measures:` → KPI (keterserapan, rata_masa_tunggu, dll.)
  - `dimensions:` → tahun, prodi, angkatan, dll.
  - `preAggregations:` → roll-up otomatis

**Repository Laravel hanya HTTP POST ke Cube.js API** (bukan `DB::select` langsung).

Contoh (ilustrasi):
```php
// DashboardRepository.php
public function getKeterserapan($tahun)
{
    return Http::post('http://cubejs:4000/cubejs-api/v1/load', [
        "query" => [
            "measures" => ["Tracer.keterserapan"],
            "dimensions" => ["Tracer.prodi"],
            "filters" => [[
                "dimension" => "Tracer.tahun",
                "operator" => "equals",
                "values" => [$tahun]
            ]]
        ]
    ])->json();
}
```

DTO kemudian mengubah response Cube.js menjadi JSON yang rapi untuk React.

---

## 6. Kenapa Tetap Pakai Laravel (bukan React → Cube.js)

- Cube.js API langsung dari React berisiko (token bocor, query bisa dimanipulasi)
- Laravel berperan sebagai **API Gateway**
- Laravel menangani:
  - Authentication
  - Role-based access control (mis. Kaprodi hanya melihat prodi tertentu)
  - Business logic
  - DTO formatting

---

## 7. Kesimpulan

**Before:** MVC + Materialized View (cukup untuk skala kecil)  
**After:** Layered Architecture + Cube.js (lebih profesional, scalable, cepat)

- PostgreSQL tetap sebagai data warehouse
- Cube.js menjadi analytic layer (pre-aggregation otomatis)
- Laravel sebagai security + orchestration
- React untuk visualisasi

---

## 8. Paradigma Pemrograman (Laravel = OOP)

Laravel adalah framework berbasis **Object-Oriented Programming (OOP)**:
- Menggunakan class, inheritance, DI, Eloquent ORM, dll.
- Cocok untuk penerapan SOLID dan Separation of Concerns dalam layered architecture.
