<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Transactional\AlumniCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Penerbitan kredensial alumni untuk kiriman surel (RBAC-16).
 *
 * KENAPA MEMBANGKITKAN DAN MENGUNDUH DALAM SATU LANGKAH
 *
 * Kata sandi disimpan sebagai cincangan bcrypt, yang tidak dapat dibalik. Jadi
 * tidak mungkin ada endpoint "ekspor kata sandi" terpisah yang dipanggil
 * belakangan atas data yang sudah tersimpan — teks polosnya hanya ada pada
 * detik ia dibangkitkan. Karena itu satu permintaan ini melakukan keduanya:
 * menerbitkan, lalu langsung mengalirkan berkasnya.
 *
 * Berkasnya TIDAK PERNAH ditulis ke disk server. Isinya kredensial yang masih
 * berlaku untuk banyak orang sekaligus; menyimpannya berarti menciptakan
 * salinan kedua yang tidak ada yang mengawasi.
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
     * Body: { graduation_year?, program_id?, only_without_credentials? }
     * Balasan: berkas CSV berisi NIM, Nama, Surel, Kata Sandi — atau JSON 422
     * bila penyaringnya tidak menjangkau seorang pun.
     */
    public function issue(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'graduation_year'          => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'program_id'               => ['nullable', 'integer', 'exists:oltp.programs,id'],
            'only_without_credentials' => ['nullable', 'boolean'],
        ]);

        // Pencincangan bcrypt memakan sekitar sepertiga detik per alumni, jadi
        // satu kelompok penuh berjalan puluhan detik — jauh di atas batas
        // bawaan PHP 30 detik. Jumlah barisnya sendiri sudah dibatasi
        // AlumniCredentialService::MAX_BATCH, sehingga ini tidak membuka pintu
        // bagi permintaan yang berjalan tanpa akhir.
        //
        // Catatan penggelaran: proksi di depan aplikasi (nginx, Apache) punya
        // batas tunggunya sendiri yang TIDAK dipengaruhi baris ini. Kalau
        // penerbitan berhenti di tengah pada server, itu yang perlu dinaikkan.
        set_time_limit(0);

        $issued = $this->service->issue($validated);

        if ($issued->isEmpty()) {
            // 422, bukan berkas kosong: berkas nol baris terlihat seperti
            // penerbitan yang berhasil dan mudah terkirim begitu saja.
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada alumni aktif yang cocok dengan penyaring tersebut, sehingga tidak ada kredensial yang diterbitkan.',
            ], 422);
        }

        $stamp = now()->format('Ymd_His');
        $csv   = "NIM,Nama,Surel,Kata Sandi\n";

        foreach ($issued as $row) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $row['nim'],
                str_replace('"', '""', $row['name']),
                $row['email'],
                $row['password'],
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"kredensial_alumni_{$stamp}.csv\"",
            // Dibaca frontend untuk memberi tahu berapa banyak yang terbit;
            // jumlah baris tidak bisa disimpulkan dari unduhan biner.
            'X-Issued-Count'      => (string) $issued->count(),
        ]);
    }
}
