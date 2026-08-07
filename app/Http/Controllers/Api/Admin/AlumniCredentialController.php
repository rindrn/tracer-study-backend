<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Transactional\AlumniCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penerbitan kredensial alumni untuk kiriman surel (RBAC-16).
 *
 * KENAPA POTONGAN, DAN KENAPA JSON ALIH-ALIH BERKAS
 *
 * Kata sandi disimpan sebagai cincangan bcrypt yang tidak dapat dibalik, jadi
 * teks polosnya hanya ada pada detik ia dibangkitkan — tidak akan pernah ada
 * endpoint "ekspor kata sandi" yang dipanggil belakangan. Sekilas itu menuntut
 * satu permintaan yang membangkitkan sekaligus menyerahkan berkasnya.
 *
 * Masalahnya, bcrypt sengaja lambat. Satu angkatan politeknik bisa mencapai
 * ribuan orang, yang berarti belasan menit dalam satu permintaan HTTP — mati
 * di batas tunggu proksi jauh sebelum selesai, dan yang diterima petugas bukan
 * galat yang jelas melainkan koneksi yang putus begitu saja.
 *
 * Maka endpoint ini menerbitkan SATU POTONG per permintaan dan mengembalikan
 * JSON berisi baris yang baru terbit beserta sisa yang belum. Frontend
 * mengulang sampai habis, menampilkan kemajuannya, lalu merakit berkasnya
 * sendiri. Bentuk JSON dipilih karena unduhan biner tidak bisa dirangkai
 * bertahap tanpa menyimpan hasil antara di server — dan menyimpan kata sandi
 * polos, walau sementara, persis yang harus dihindari.
 *
 * Tidak ada kata sandi polos yang pernah menyentuh disk server.
 *
 * Terbatas pada head_tracer lewat middleware di routes/api.php.
 */
class AlumniCredentialController extends Controller
{
    public function __construct(
        private readonly AlumniCredentialService $service,
    ) {}

    /**
     * POST /api/alumni/credentials/issue
     *
     * Body: { graduation_year?, program_id?, only_without_credentials?,
     *         limit?, after_nim? }
     *
     * Balasan 200: { data: { issued: [...], remaining, last_nim } }. Selama
     * `remaining` masih di atas nol, pemanggil memanggil lagi dengan
     * `after_nim` = `last_nim`.
     *
     * Balasan 422 hanya untuk panggilan PERTAMA yang tidak menjangkau seorang
     * pun — supaya petugas tahu penyaringnya keliru, alih-alih menerima berkas
     * kosong yang terlihat seperti keberhasilan.
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'graduation_year'          => ['nullable', 'integer', 'min:1900', 'max:2200'],
            // Keduanya opsional dan saling bebas: jurusan saja berarti seluruh
            // program studi di bawahnya, keduanya berarti irisannya, tidak
            // satu pun berarti seluruh alumni.
            'jurusan'                  => ['nullable', 'string', 'max:100'],
            'program_id'               => ['nullable', 'integer', 'exists:oltp.programs,id'],
            'only_without_credentials' => ['nullable', 'boolean'],
            'limit'                    => ['nullable', 'integer', 'min:1', 'max:' . AlumniCredentialService::MAX_BATCH],
            'after_nim'                => ['nullable', 'string', 'max:30'],
        ]);

        // Satu potong pun bisa berjalan puluhan detik karena bcrypt sengaja
        // lambat — di atas batas bawaan PHP 30 detik. Besar potongannya sudah
        // dibatasi MAX_BATCH, jadi ini tidak membuka pintu bagi permintaan yang
        // berjalan tanpa akhir.
        //
        // Catatan penggelaran: proksi di depan aplikasi (nginx, Apache) punya
        // batas tunggunya sendiri yang TIDAK dipengaruhi baris ini. Bila
        // potongan sering putus di server, turunkan `limit` dari frontend atau
        // naikkan batas tunggu proksi.
        set_time_limit(0);

        $result = $this->service->issue($validated);

        if ($result['issued']->isEmpty() && empty($validated['after_nim'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada alumni aktif yang cocok dengan penyaring tersebut, sehingga tidak ada kredensial yang diterbitkan.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'issued'    => $result['issued']->values(),
                'remaining' => $result['remaining'],
                'last_nim'  => $result['last_nim'],
            ],
        ]);
    }
}
