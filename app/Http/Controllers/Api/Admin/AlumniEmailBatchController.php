<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transactional\AlumniEmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Status batch pengiriman email alumni -- dipoll FE, dipakai KEDUA aksi
 * ("Terbitkan Akun" lewat AlumniCredentialEmailController dan "Kirim
 * Reminder" lewat AlumniReminderController) karena keduanya menulis ke
 * tabel alumni_email_log yang sama, dibedakan kolom `kind`. Query di sini
 * TIDAK menyaring `kind` -- satu batch_id hanya pernah dipakai satu aksi
 * (dibangkitkan FE sekali per sesi kirim), jadi menyaringnya tidak perlu.
 */
class AlumniEmailBatchController extends Controller
{
    /**
     * GET /api/alumni/email-batches/{batchId}
     *
     * Dipoll FE SETELAH seluruh potongan issue-email selesai diantrekan,
     * sampai `pending` mencapai nol -- itu tandanya worker sudah mencoba
     * SETIAP email di batch ini (sukses atau gagal permanen).
     *
     * `failed_items` dibatasi 500 baris -- lihat alasan angka ini di
     * riwayat AlumniCredentialEmailController (dua potongan MAX_BATCH
     * penuh gagal total; lebih dari itu berarti ada yang salah secara
     * sistemik, bukan kegagalan acak per alumni).
     */
    public function status(string $batchId): JsonResponse
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $batchId)) {
            return response()->json(['success' => false, 'message' => 'batch_id tidak valid.'], 422);
        }

        $snapshot = $this->snapshot($batchId);

        if (!$snapshot) {
            return response()->json(['success' => false, 'message' => 'Batch tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $snapshot]);
    }

    /**
     * GET /api/alumni/email-batches/active?kind=account|reminder
     *
     * Dipanggil FE SEKALI saat halaman Manajemen Email dimuat (termasuk
     * setelah refresh) supaya progres batch yang masih berjalan bisa
     * dipulihkan tanpa mengandalkan state komponen atau localStorage --
     * `batch_id` sebelumnya cuma hidup di memori React, hilang begitu
     * halaman direfresh, padahal worker di belakang tetap jalan.
     *
     * "Aktif" = batch TERBARU milik admin yang sedang login untuk `kind`
     * tsb yang masih punya baris berstatus `queued`. Kalau sudah tidak ada
     * yang `queued` (selesai semua, atau memang belum pernah kirim),
     * balasannya `data: null` -- FE tidak menampilkan panel progres apa
     * pun.
     */
    public function active(Request $request): JsonResponse
    {
        $kind = $request->query('kind');
        if (!in_array($kind, ['account', 'reminder'], true)) {
            return response()->json(['success' => false, 'message' => 'kind tidak valid.'], 422);
        }

        $userId = $request->user()?->id;

        $batchId = AlumniEmailLog::where('kind', $kind)
            ->where('created_by', $userId)
            ->where('status', 'queued')
            ->orderByDesc('id')
            ->value('batch_id');

        if (!$batchId) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json(['success' => true, 'data' => array_merge(
            ['batch_id' => $batchId],
            $this->snapshot($batchId) ?? [],
        )]);
    }

    /**
     * POST /api/alumni/email-batches/{batchId}/cancel
     *
     * Menandai seluruh baris `queued` di batch ini jadi `canceled` --
     * TIDAK menghapus job yang sudah masuk tabel `jobs`, karena job itu
     * sendiri mengecek status baris log sebelum benar-benar mengirim SMTP
     * (lihat SendAlumniAccountEmailJob::handle() /
     * SendAlumniReminderEmailJob::handle()). Worker akan tetap
     * mengambilnya dari antrean tapi langsung no-op begitu melihat
     * statusnya bukan lagi `queued` -- jauh lebih murah daripada mencari
     * baris `jobs` yang berkorelasi lewat payload.
     *
     * Baris yang statusnya sudah `sent`/`failed` (worker sudah lebih dulu
     * memprosesnya) TIDAK disentuh -- pembatalan hanya berlaku untuk yang
     * belum sempat diproses.
     */
    public function cancel(string $batchId): JsonResponse
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $batchId)) {
            return response()->json(['success' => false, 'message' => 'batch_id tidak valid.'], 422);
        }

        $canceled = AlumniEmailLog::where('batch_id', $batchId)
            ->where('status', 'queued')
            ->update(['status' => 'canceled']);

        $snapshot = $this->snapshot($batchId);

        if (!$snapshot) {
            return response()->json(['success' => false, 'message' => 'Batch tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => array_merge($snapshot, ['canceled' => $canceled])]);
    }

    /**
     * Agregat status satu batch, dipakai status()/active()/cancel() supaya
     * bentuk balasannya konsisten di ketiganya. `pending` HANYA menghitung
     * `queued` (bukan `queued + canceled`) -- baris `canceled` sudah final,
     * tidak akan pernah diproses worker lagi, jadi FE boleh berhenti
     * mem-poll begitu `pending` nol persis seperti sebelum ada fitur batal.
     *
     * @return array{total:int,sent:int,failed:int,canceled:int,pending:int,failed_items:mixed}|null
     */
    private function snapshot(string $batchId): ?array
    {
        $counts = AlumniEmailLog::where('batch_id', $batchId)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $total = (int) $counts->sum();

        if ($total === 0) {
            return null;
        }

        $failedItems = AlumniEmailLog::where('batch_id', $batchId)
            ->where('status', 'failed')
            ->orderBy('id')
            ->limit(500)
            ->get(['nim', 'name', 'email', 'error_message']);

        return [
            'total'        => $total,
            'sent'         => (int) ($counts['sent'] ?? 0),
            'failed'       => (int) ($counts['failed'] ?? 0),
            'canceled'     => (int) ($counts['canceled'] ?? 0),
            'pending'      => (int) ($counts['queued'] ?? 0),
            'failed_items' => $failedItems,
        ];
    }
}
