<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAlumniReminderEmailJob;
use App\Models\Transactional\AlumniEmailLog;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Services\Transactional\AlumniCredentialService;
use App\Services\Transactional\AlumniSelectionResolver;
use App\Services\Transactional\AuditLogService;
use App\Support\EmailFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pengingat isi kuesioner ("Kirim Reminder") -- alumni yang SUDAH punya
 * akun (password_issued_at terisi) tapi BELUM menyelesaikan kuesioner
 * global. TIDAK meregenerasi kata sandi -- beda mendasar dari
 * AlumniCredentialEmailController ("Terbitkan Akun"), yang membuat
 * controller ini tidak memanggil AlumniCredentialService sama sekali.
 *
 * Seleksi (checkbox tabel "Manajemen Email": pilih semua sesuai filter
 * dengan pengecualian manual, ATAU daftar NIM eksplisit) diresolusi lewat
 * AlumniSelectionResolver yang sama dengan "Terbitkan Akun" -- syarat
 * TAMBAHAN "sudah punya akun dan belum selesai kuesioner" dilapiskan lewat
 * closure $extra yang memanggil AlumniProfileRepository, supaya definisi
 * "belum selesai" tetap satu sumber kebenaran (sama dengan yang dipakai
 * GET /alumni untuk kolom Status Kuesioner).
 *
 * Terbatas pada head_tracer lewat middleware di routes/api.php, sama
 * seperti AlumniCredentialEmailController.
 */
class AlumniReminderController extends Controller
{
    public function __construct(
        private readonly AlumniSelectionResolver $selectionResolver,
        private readonly AlumniProfileRepository $alumniProfiles,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * POST /api/alumni/reminders/issue-email
     *
     * Body: sama pola seleksi seperti /alumni/credentials/issue-email --
     * `batch_id` (uuid) wajib, lalu `nims` (mode eksplisit) ATAU
     * `graduation_year`/`jurusan`/`program_id`/`excluded_nims` (mode
     * filtered), plus `after_nim`/`limit` untuk kursor.
     *
     * Balasan 200: { data: { issued_count, queued, skipped_no_email,
     * remaining, last_nim } }. `queued` berarti "masuk antrean worker",
     * bukan "sudah terkirim" -- sama seperti endpoint kredensial.
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id'                 => ['required', 'uuid'],
            'nims'                     => ['required_without_all:graduation_year,jurusan,program_id', 'array'],
            'nims.*'                   => ['string', 'max:30'],
            'graduation_year'          => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'jurusan'                  => ['nullable', 'string', 'max:100'],
            'program_id'               => ['nullable', 'integer', 'exists:oltp.programs,id'],
            'excluded_nims'            => ['nullable', 'array'],
            'excluded_nims.*'          => ['string', 'max:30'],
            'limit'                    => ['nullable', 'integer', 'min:1', 'max:' . AlumniCredentialService::MAX_BATCH],
            'after_nim'                => ['nullable', 'string', 'max:30'],
        ]);

        $selection = !empty($validated['nims'])
            ? ['mode' => 'explicit', 'nims' => $validated['nims']]
            : [
                'mode'          => 'filtered',
                'filters'       => [
                    'graduation_year' => $validated['graduation_year'] ?? null,
                    'jurusan'         => $validated['jurusan'] ?? null,
                    'program_id'      => $validated['program_id'] ?? null,
                ],
                'excluded_nims' => $validated['excluded_nims'] ?? [],
            ];

        $limit = (int) ($validated['limit'] ?? AlumniCredentialService::DEFAULT_LIMIT);
        $limit = max(1, min($limit, AlumniCredentialService::MAX_BATCH));

        // Syarat tambahan "reminder-eligible": sudah punya akun, belum
        // selesai kuesioner global -- lihat AlumniProfileRepository::
        // applyNotFinishedConstraint(). Definisi "selesai" ada SATU tempat
        // di repository itu, tidak diduplikasi di sini.
        $extra = function ($query) {
            $query->whereNotNull('password_issued_at');
            $this->alumniProfiles->applyNotFinishedConstraint($query);
        };

        $targets = $this->selectionResolver->resolveChunk(
            $selection, $validated['after_nim'] ?? null, $limit, $extra,
        );

        if ($targets->isEmpty() && empty($validated['after_nim'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada alumni yang cocok -- pastikan yang dipilih sudah punya akun dan belum menyelesaikan kuesioner.',
            ], 422);
        }

        $loginUrl = rtrim((string) config('services.frontend.url'), '/') . '/login';
        $batchId  = $validated['batch_id'];

        $queued = 0;
        $skippedNoEmail = 0;
        $userId = $request->user()?->id;

        foreach ($targets as $row) {
            if (empty($row->email)) {
                AlumniEmailLog::create([
                    'batch_id'      => $batchId,
                    'kind'          => 'reminder',
                    'created_by'    => $userId,
                    'nim'           => $row->nim,
                    'name'          => $row->name,
                    'email'         => '',
                    'status'        => 'failed',
                    'error_message' => 'Tidak ada alamat surel terdaftar.',
                ]);
                $skippedNoEmail++;
                Log::warning("AlumniReminderController: nim={$row->nim} dilewati, tidak punya surel.");
                continue;
            }

            if (!EmailFormat::isValid($row->email)) {
                AlumniEmailLog::create([
                    'batch_id'      => $batchId,
                    'kind'          => 'reminder',
                    'created_by'    => $userId,
                    'nim'           => $row->nim,
                    'name'          => $row->name,
                    'email'         => $row->email,
                    'status'        => 'failed',
                    'error_message' => "Format alamat surel tidak valid: {$row->email}",
                ]);
                $skippedNoEmail++;
                Log::warning("AlumniReminderController: nim={$row->nim} dilewati, format surel tidak valid ({$row->email}).");
                continue;
            }

            if (!EmailFormat::hasDeliverableDomain($row->email)) {
                AlumniEmailLog::create([
                    'batch_id'      => $batchId,
                    'kind'          => 'reminder',
                    'created_by'    => $userId,
                    'nim'           => $row->nim,
                    'name'          => $row->name,
                    'email'         => $row->email,
                    'status'        => 'failed',
                    'error_message' => "Domain surel tidak dapat menerima email (tidak ada MX/A record): {$row->email}",
                ]);
                $skippedNoEmail++;
                Log::warning("AlumniReminderController: nim={$row->nim} dilewati, domain tidak dapat menerima email ({$row->email}).");
                continue;
            }

            $log = AlumniEmailLog::create([
                'batch_id' => $batchId,
                'kind'     => 'reminder',
                'created_by' => $userId,
                'nim'      => $row->nim,
                'name'     => $row->name,
                'email'    => $row->email,
                'status'   => 'queued',
            ]);

            SendAlumniReminderEmailJob::dispatch($log->id, $row->nim, (string) $row->name, $row->email, $loginUrl);

            Log::info("AlumniReminderController: reminder untuk nim={$row->nim} ({$row->email}) masuk antrean (batch={$batchId}).");
            $queued++;
        }

        $lastNim = $targets->isNotEmpty() ? (string) $targets->last()->nim : ($validated['after_nim'] ?? null);

        $this->audit->record('alumni.reminder_emailed', [
            'entity_type' => 'alumni_profiles',
            'context'     => [
                'batch_id'         => $batchId,
                'queued'           => $queued,
                'skipped_no_email' => $skippedNoEmail,
                'selection_mode'   => $selection['mode'],
                'graduation_year'  => $selection['filters']['graduation_year'] ?? null,
                'program_id'       => $selection['filters']['program_id'] ?? null,
                'jurusan'          => $selection['filters']['jurusan'] ?? null,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'issued_count'     => $targets->count(),
                'queued'           => $queued,
                'skipped_no_email' => $skippedNoEmail,
                'remaining'        => $lastNim ? $this->selectionResolver->countRemaining($selection, $lastNim, $extra) : 0,
                'last_nim'         => $lastNim,
            ],
        ]);
    }
}
