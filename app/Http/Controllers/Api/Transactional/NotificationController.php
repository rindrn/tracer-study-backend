<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Models\Transactional\ApprovalRequest;
use App\Models\Transactional\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan pekerjaan yang menunggu tindakan pemanggil.
 *
 * Hanya menghitung, tidak menyimpan: tidak ada tabel notifikasi, tidak ada
 * status "sudah dibaca". Angkanya diturunkan langsung dari antrean yang
 * sudah jadi sumber kebenarannya, jadi tidak mungkin lonceng menyala untuk
 * permintaan yang sebenarnya sudah diproses lewat halamannya.
 *
 * Cakupan tiap angka MENGIKUTI gate endpoint aslinya — kalau tidak, lonceng
 * membocorkan keberadaan pekerjaan yang halamannya sendiri menolak dibuka.
 */
class NotificationController extends Controller
{
    /** GET /api/notifications/summary */
    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $approvals = $this->pendingApprovals($user);
        $dsr       = $this->pendingDataSubjectRequests($user);

        return response()->json([
            'success' => true,
            'data'    => [
                'approvals_pending'             => $approvals,
                'data_subject_requests_pending' => $dsr,
                'total'                         => $approvals + $dsr,
            ],
        ]);
    }

    /**
     * Persetujuan yang menunggu, dengan cakupan yang sama seperti
     * ApprovalController::index(): Ketua Tracer melihat seluruh antrean,
     * pemohon hanya permintaannya sendiri.
     */
    private function pendingApprovals(User $user): int
    {
        $canSeeQueue = in_array($user->role, [
            User::ROLE_HEAD_TRACER,
            User::ROLE_TRACER_TEAM,
            User::ROLE_KAPRODI,
        ], strict: true);

        if (!$canSeeQueue) {
            return 0;
        }

        $query = ApprovalRequest::where('status', ApprovalRequest::STATUS_PENDING);

        if (!$user->isHeadTracer()) {
            $query->where('requester_id', $user->id);
        }

        return $query->count();
    }

    /**
     * Permintaan hak subjek data yang belum disentuh siapa pun.
     *
     * Yang dihitung hanya status 'pending'. 'in_review' berarti sudah ada
     * yang memegangnya — masih terbuka, tetapi bukan lagi hal yang perlu
     * diberitahukan sebagai pekerjaan baru.
     *
     * Sama seperti endpoint antreannya, terbatas pada Ketua Tracer.
     */
    private function pendingDataSubjectRequests(User $user): int
    {
        if (!$user->isHeadTracer()) {
            return 0;
        }

        return DB::connection('oltp')->table('data_subject_requests')
            ->where('status', 'pending')
            ->count();
    }
}
