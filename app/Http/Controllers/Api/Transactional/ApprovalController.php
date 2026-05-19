<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Models\Transactional\ApprovalRequest;
use App\Models\Transactional\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /** GET /api/approvals — head_tracer sees all, tracer_team sees own */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ApprovalRequest::with('requester:id,name,email')
            ->orderByDesc('created_at');

        if ($user->isTracerTeam()) {
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

        // Auto-publish questionnaire if type is add_questionnaire
        if ($approval->type === ApprovalRequest::TYPE_ADD_QUESTIONNAIRE && isset($approval->payload['questionnaire_id'])) {
            DB::connection('oltp')->table('questionnaires')
                ->where('id', $approval->payload['questionnaire_id'])
                ->update(['status' => 'published', 'published_at' => now()]);
        }

        // Auto-delete questionnaire if type is delete_questionnaire
        if ($approval->type === ApprovalRequest::TYPE_DELETE_QUESTIONNAIRE && isset($approval->payload['questionnaire_id'])) {
            DB::connection('oltp')->table('questionnaires')
                ->where('id', $approval->payload['questionnaire_id'])
                ->delete();
        }

        return response()->json(['success' => true, 'message' => 'Request disetujui.']);
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
