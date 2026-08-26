# Endpoint terdaftar yang tidak terimplementasi

Ditemukan 26 Agustus 2026 saat menjalankan koleksi Postman secara penuh lewat
Newman. Seluruh endpoint di bawah **terdaftar di `php artisan route:list`**,
muncul di dokumentasi API, dan dijamin membalas **HTTP 500** begitu dipanggil —
karena method yang ditunjuknya tidak pernah ditulis.

Tidak satu pun dipanggil frontend, sehingga tidak pernah terlihat selama
pemakaian normal maupun pengujian manual lewat antarmuka.

## Akar masalah

`Route::apiResource()` mendaftarkan **lima route sekaligus** — `index`, `store`,
`show`, `update`, `destroy` — tanpa memeriksa apakah controllernya benar-benar
punya method itu. Selama tidak ada yang memanggil, route hantu tersebut diam
saja. Yang menutupinya lebih jauh: seluruh method yang benar-benar dipakai
frontend memang ada, jadi aplikasi berjalan normal.

Ini beda dengan bug `int $id` (500 untuk id non-numerik) yang diperbaiki di hari
yang sama lewat `Route::pattern()` di `AppServiceProvider`. Di sana id-nya yang
salah bentuk; di sini id-nya benar, methodnya yang tidak ada.

## 1. `ThresholdController` — 5 method memanggil service yang tidak ada

`app/Services/Transactional/ThresholdService.php` hanya punya lima method:
`byVersion`, `bulkCreate`, `bulkUpdate`, `forChart`, `tracerResponseByLam`.

Controllernya memanggil lima method lain yang tidak pernah dibuat:

| Baris controller | Memanggil | Ada di service? |
|---|---|---|
| `ThresholdController.php:20` | `service->list()` | tidak |
| `ThresholdController.php:28` | `service->show()` | tidak |
| `ThresholdController.php:47` | `service->create()` | tidak |
| `ThresholdController.php:57` | `service->update()` | tidak |
| `ThresholdController.php:63` | `service->delete()` | tidak |

Galat yang muncul, contoh nyata:

```
GET /api/thresholds
  → Call to undefined method App\Services\Transactional\ThresholdService::list()

GET /api/thresholds/1
  → Call to undefined method App\Services\Transactional\ThresholdService::show()
```

Route yang terdampak — **enam**, karena `thresholds` didaftarkan **dua kali**:

| Route | Didaftarkan di |
|---|---|
| `GET /api/thresholds` | `routes/api.php:367` (apiResource) |
| `POST /api/thresholds` | `routes/api.php:285` |
| `GET /api/thresholds/{threshold}` | `routes/api.php:367` (apiResource) |
| `PUT /api/thresholds/{id}` | `routes/api.php:286` |
| `PUT /api/thresholds/{threshold}` | `routes/api.php:367` (apiResource) |
| `DELETE /api/thresholds/{id}` | `routes/api.php:287` |
| `DELETE /api/thresholds/{threshold}` | `routes/api.php:367` (apiResource) |

Duplikasi itu sendiri layak dibereskan: `apiResource` di baris 367 mendaftarkan
ulang apa yang sudah ditulis eksplisit di baris 285–287, dengan nama parameter
berbeda (`{threshold}` vs `{id}`).

## 2. `UserController` — method `show()` tidak ada sama sekali

`app/Http/Controllers/Api/Transactional/UserController.php` hanya berisi
`index`, `store`, `update`, `destroy`, `toggleStatus`. Tidak ada `show`.

Tetapi `routes/api.php:199` menulis `Route::apiResource('users', ...)`, yang
tetap mendaftarkan:

```
GET /api/users/{user}
  → Call to undefined method
    App\Http\Controllers\Api\Transactional\UserController::show()
```

## Dipakai frontend?

Tidak. Diverifikasi dengan menelusuri seluruh `fe-tracer-study/src`:

| Endpoint | Dipakai FE? | Yang dipakai FE sebagai gantinya |
|---|---|---|
| `GET /users/{user}` | tidak | `GET /users` (daftar), lalu data dipakai dari hasil itu — `hooks/admin/useStaff.ts:40` |
| `/thresholds` CRUD satuan | tidak | `/lam-versions/{id}/thresholds/bulk`, `/dashboard/thresholds`, `/threshold-indicators` |

Pengaturan threshold di aplikasi dilakukan **per versi LAM secara borongan**
(`bulkStore` / `bulkUpdate`), bukan satu per satu. Itu menjelaskan kenapa CRUD
satuannya tidak pernah selesai dibuat.

## Rekomendasi

Perlu keputusan pemilik produk, karena dua arahnya berbeda makna:

1. **Hapus** — kalau CRUD threshold satuan dan detail user memang tidak
   direncanakan. Empat suntingan:
   - `routes/api.php:367` — hapus `apiResource('thresholds', ...)`
   - `routes/api.php:285–287` — hapus `POST` / `PUT` / `DELETE thresholds`
   - `routes/api.php:199` — `apiResource('users', ...)->except(['show'])`
   - `ThresholdController.php` — hapus 5 method mati

2. **Implementasikan** — kalau memang direncanakan tapi belum sempat.

Arah pertama lebih jujur: route-nya hilang dari dokumentasi, bukan ada tapi
rusak. Presedennya sudah ada di `routes/api.php:80`, yang membatasi
`apiResource('questionnaires', ...)` dengan `->only(['show'])`.

## Pencegahan

Setiap `apiResource` baru sebaiknya langsung dibatasi `->only([...])` atau
`->except([...])` sesuai method yang benar-benar ada di controller. Menjalankan
koleksi Postman secara penuh (`newman run postman/smarttracer.postman_collection.json`)
menangkap kelas galat ini, karena seluruh endpoint ikut dipanggil — termasuk
yang frontend tidak pernah sentuh.
