<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Models\Transactional\ApprovalRequest;
use App\Models\Transactional\User;
use App\Services\Transactional\ResponseReopenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ResponseReopenService $reopenService,
    ) {}

    /** GET /api/approvals — head_tracer melihat semua, pemohon melihat miliknya */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ApprovalRequest::with('requester:id,name,email')
            ->orderByDesc('created_at');

        // Hanya Ketua Tracer yang berhak melihat permintaan orang lain.
        // Sebelumnya penyaringan dipatok ke isTracerTeam(), sehingga peran
        // pemohon lain (Kaprodi) akan melihat seluruh antrean persetujuan.
        if (!$user->isHeadTracer()) {
            $query->where('requester_id', $user->id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    /** POST /api/approvals/{id}/approve */
    public function approve(Request $request, int $id): JsonResponse
    {
        $approval = ApprovalRequest::findOrFail($id);

        if (!$approval->isPending()) {
            return response()->json(['success' => false, 'message' => 'Request sudah diproses.'], 422);
        }

        $approval->update([
            'status'      => ApprovalRequest::STATUS_APPROVED,
            'approver_id' => $request->user()->id,
            'note'        => $request->input('note'),
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $this->executeApprovedAction($approval),
        ]);
    }

    /**
     * Jalankan efek samping persetujuan sesuai jenis permintaan.
     *
     * Dipisahkan dari approve() karena jenisnya sudah tiga; rantai if di
     * dalam approve() mulai mengaburkan bahwa pencatatan persetujuan dan
     * eksekusinya adalah dua hal berbeda.
     *
     * @return string pesan yang dikembalikan ke pemanggil
     */
    private function executeApprovedAction(ApprovalRequest $approval): string
    {
        $payload = $approval->payload ?? [];

        return match ($approval->type) {
            ApprovalRequest::TYPE_ADD_QUESTIONNAIRE => $this->publishQuestionnaire($payload),
            ApprovalRequest::TYPE_DELETE_QUESTIONNAIRE => $this->deleteQuestionnaire($payload),
            ApprovalRequest::TYPE_REOPEN_RESPONSE => $this->reopenResponse($payload),
            default => 'Request disetujui.',
        };
    }

    private function publishQuestionnaire(array $payload): string
    {
        if (!isset($payload['questionnaire_id'])) {
            return 'Request disetujui.';
        }

        DB::connection('oltp')->table('questionnaires')
            ->where('id', $payload['questionnaire_id'])
            ->update(['status' => 'published', 'published_at' => now()]);

        return 'Request disetujui. Kuesioner diterbitkan.';
    }

    private function deleteQuestionnaire(array $payload): string
    {
        if (!isset($payload['questionnaire_id'])) {
            return 'Request disetujui.';
        }

        DB::connection('oltp')->table('questionnaires')
            ->where('id', $payload['questionnaire_id'])
            ->delete();

        return 'Request disetujui. Kuesioner dihapus.';
    }

    /**
     * Buka kembali pengisian alumni (Finish → Ongoing) tanpa menghapus
     * jawabannya — lihat ResponseRepository::resetToOngoing().
     *
     * Kegagalan di sini tidak membatalkan persetujuan yang sudah tercatat:
     * permintaannya memang disetujui, hanya sasarannya yang sudah tidak
     * dalam keadaan yang bisa dibuka (mis. sudah dibuka lewat permintaan
     * lain). Perbedaan itu disampaikan lewat pesan, bukan lewat galat.
     */
    private function reopenResponse(array $payload): string
    {
        if (!isset($payload['alumni_id'])) {
            return 'Request disetujui.';
        }

        $reopened = $this->reopenService->reopen((int) $payload['alumni_id']);

        return $reopened > 0
            ? "Request disetujui. Pengisian alumni dibuka kembali ({$reopened} kuesioner) dan jawaban sebelumnya dipertahankan."
            : 'Request disetujui, tetapi pengisian alumni sudah tidak berstatus Finish sehingga tidak ada yang dibuka.';
    }

    /** POST /api/approvals/request-delete — tracer_team submits delete request */
    public function requestDelete(Request $request): JsonResponse
    {
        $request->validate([
            'questionnaire_id' => ['required', 'integer'],
            'title'            => ['required', 'string'],
            'note'             => ['required', 'string'],
        ]);

        ApprovalRequest::create([
            'requester_id' => $request->user()->id,
            'type'         => ApprovalRequest::TYPE_DELETE_QUESTIONNAIRE,
            'payload'      => [
                'questionnaire_id' => $request->input('questionnaire_id'),
                'title'            => $request->input('title'),
            ],
            'status' => ApprovalRequest::STATUS_PENDING,
            'note'   => $request->input('note'),
        ]);

        return response()->json(['success' => true, 'message' => 'Permintaan penghapusan berhasil diajukan.']);
    }

    /**
     * POST /api/approvals/request-reopen — Tim Tracer atau Kaprodi mengajukan
     * pembukaan kembali pengisian alumni (RBAC-08, RBAC-12).
     *
     * Pengajuan tidak mengubah apa pun. Perubahan status baru terjadi saat
     * Ketua Tracer menyetujui, lewat executeApprovedAction() — itulah yang
     * membuat RBAC-04 terpenuhi: ada satu pintu, dan pintunya tercatat.
     */
    public function requestReopen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alumni_id' => ['required', 'integer', 'exists:oltp.alumni_profiles,id'],
            // Opsional: pemohon dari halaman Data Alumni tidak punya konteks
            // kuesioner. Lingkup pembukaan memang per alumni, jadi nilai ini
            // hanya menentukan tautan "Lihat" milik penyetuju — kalau kosong,
            // backend memilih kuesioner aktif pertama milik alumni tersebut.
            'questionnaire_id' => ['nullable', 'integer', 'exists:oltp.questionnaires,id'],
            'note'             => ['required', 'string'],
        ]);

        $questionnaireId = $validated['questionnaire_id']
            ?? ($this->reopenService->activeQuestionnaireIds((int) $validated['alumni_id'])[0] ?? null);

        if ($questionnaireId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Alumni ini tidak memiliki kuesioner aktif, sehingga tidak ada yang bisa dibuka kembali.',
            ], 422);
        }

        // Kembar diperiksa per ALUMNI saja, bukan per kuesioner: satu
        // persetujuan membuka seluruh kuesioner aktif alumni tersebut, jadi
        // permintaan kedua untuk alumni yang sama tidak akan menemukan apa
        // pun lagi untuk dibuka.
        $duplicate = ApprovalRequest::where('type', ApprovalRequest::TYPE_REOPEN_RESPONSE)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->whereRaw("payload->>'alumni_id' = ?", [(string) $validated['alumni_id']])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada permintaan pembukaan kembali yang menunggu persetujuan untuk alumni ini.',
            ], 422);
        }

        // Nama dan NIM ikut disimpan di payload, bukan dicari ulang saat
        // ditampilkan: penyetuju perlu tahu SIAPA yang diminta dibuka, dan
        // "Alumni #200" tidak memberi tahu apa pun. Pola ini sama dengan
        // requestDelete() yang menyimpan judul kuesioner.
        $alumni = DB::connection('oltp')->table('alumni_profiles')
            ->where('id', $validated['alumni_id'])
            ->first(['name', 'nim']);

        ApprovalRequest::create([
            'requester_id' => $request->user()->id,
            'type'         => ApprovalRequest::TYPE_REOPEN_RESPONSE,
            'payload'      => [
                'alumni_id'        => $validated['alumni_id'],
                'questionnaire_id' => $questionnaireId,
                'alumni_name'      => $alumni->name ?? null,
                'alumni_nim'       => $alumni->nim ?? null,
            ],
            'status' => ApprovalRequest::STATUS_PENDING,
            'note'   => $validated['note'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan pembukaan kembali berhasil diajukan dan menunggu persetujuan Ketua Tracer.',
        ]);
    }

    /** POST /api/approvals/{id}/reject */
    public function reject(Request $request, int $id): JsonResponse
    {
        $approval = ApprovalRequest::findOrFail($id);

        if (!$approval->isPending()) {
            return response()->json(['success' => false, 'message' => 'Request sudah diproses.'], 422);
        }

        $approval->update([
            'status'      => ApprovalRequest::STATUS_REJECTED,
            'approver_id' => $request->user()->id,
            'note'        => $request->input('note'),
            'resolved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Request ditolak.']);
    }
}
