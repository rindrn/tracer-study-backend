<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pembacaan jejak audit, untuk Ketua Tracer.
 *
 * HANYA BACA. Tidak ada endpoint tulis, ubah, maupun hapus di sini, dan itu
 * bukan kelalaian: jejak audit yang bisa disunting oleh orang yang
 * perbuatannya dicatat di dalamnya tidak membuktikan apa pun. Penulisannya
 * hanya lewat AuditLogService, dari dalam alur yang dicatatnya.
 *
 * Pemangkasan baris lama pun sengaja tidak disediakan lewat API. Kalau kelak
 * dibutuhkan, tempatnya perintah CLI terjadwal dengan kebijakan masa simpan
 * yang tertulis — bukan tombol yang bisa ditekan sewaktu-waktu.
 */
class AuditLogController extends Controller
{
    private const CONN = 'oltp';

    /**
     * GET /api/admin/audit-logs
     *
     * Saringan: action, actor_type, subject_alumni_id, from, to.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action'            => ['nullable', 'string', 'max:100'],
            'actor_type'        => ['nullable', 'string', 'in:user,alumni,system'],
            'subject_alumni_id' => ['nullable', 'integer'],
            'from'              => ['nullable', 'date'],
            'to'                => ['nullable', 'date'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = DB::connection(self::CONN)->table('audit_logs');

        // Awalan, bukan kecocokan penuh: 'consent' menangkap consent.granted
        // sekaligus consent.withdrawn, yang memang selalu ditelusuri bersama.
        if (!empty($validated['action'])) {
            $query->where('action', 'like', $validated['action'] . '%');
        }

        if (!empty($validated['actor_type'])) {
            $query->where('actor_type', $validated['actor_type']);
        }

        if (!empty($validated['subject_alumni_id'])) {
            $query->where('subject_alumni_id', $validated['subject_alumni_id']);
        }

        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            // Batas atas dinaikkan ke akhir hari. Tanpa ini, menyaring
            // "sampai 20 Agustus" membuang seluruh kejadian pada 20 Agustus
            // itu sendiri, karena tanggal tanpa jam berarti pukul 00:00.
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }

        $page = $query->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 50);

        $page->getCollection()->transform(function ($row) {
            $row->context = $row->context ? json_decode($row->context, true) : null;
            return $row;
        });

        return response()->json([
            'success' => true,
            'data'    => $page,
        ]);
    }
}
