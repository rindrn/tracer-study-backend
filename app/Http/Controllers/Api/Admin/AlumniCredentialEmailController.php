<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAlumniAccountEmailJob;
use App\Models\Transactional\AlumniEmailLog;
use App\Services\Transactional\AlumniCredentialService;
use App\Services\Transactional\AuditLogService;
use App\Support\EmailFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Penerbitan akun alumni langsung lewat email bulk ("Terbitkan Akun").
 *
 * Sasarannya sekarang SELEKSI HYBRID dari tabel "Manajemen Email" (checkbox
 * pilih-per-baris, dengan opsi "pilih semua sesuai filter" + pengecualian
 * manual) -- bukan lagi filter murni. Diproses lewat
 * AlumniCredentialService::issueForSelection(), yang memakai
 * AlumniSelectionResolver yang sama dipakai fitur reminder
 * (AlumniReminderController), supaya logika resolusi seleksi hanya hidup
 * di satu tempat.
 *
 * TIDAK memengaruhi AlumniCredentialController ("Terbitkan Kredensial"
 * xlsx di Kelola Mahasiswa) -- itu tetap filter-murni lewat
 * AlumniCredentialService::issue() apa adanya.
 *
 * Password tetap TIDAK PERNAH kembali ke frontend -- balasannya cuma
 * cacah, bukan daftar kredensial, persis seperti sebelumnya.
 *
 * Pengiriman lewat SendAlumniAccountEmailJob yang diantrekan -- lihat
 * komentar di job itu untuk alasan chunking & queue.
 *
 * Terbatas pada head_tracer lewat middleware di routes/api.php.
 */
class AlumniCredentialEmailController extends Controller
{
    public function __construct(
        private readonly AlumniCredentialService $service,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * POST /api/alumni/credentials/issue-email
     *
     * Body: `batch_id` (uuid) wajib, lalu `nims` (mode eksplisit -- daftar
     * NIM yang dicentang manual di tabel) ATAU
     * `graduation_year`/`jurusan`/`program_id`/`excluded_nims` (mode
     * filtered -- "pilih semua sesuai filter" dikurangi yang di-uncheck
     * manual), plus `only_without_credentials`/`after_nim`/`limit`.
     *
     * Balasan 200: { data: { issued_count, queued, skipped_no_email,
     * remaining, last_nim } }. Selama `remaining` masih di atas nol,
     * pemanggil memanggil lagi dengan `after_nim` = `last_nim`.
     *
     * Status kirim yang sesungguhnya (sent/failed per alumni) HANYA bisa
     * diketahui lewat GET /alumni/email-batches/{batchId} (AlumniEmailBatchController),
     * karena pengiriman SMTP terjadi belakangan di worker.
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
            'only_without_credentials' => ['nullable', 'boolean'],
            'limit'                    => ['nullable', 'integer', 'min:1', 'max:' . AlumniCredentialService::MAX_BATCH],
            'after_nim'                => ['nullable', 'string', 'max:30'],
        ]);

        $selection = !empty($validated['nims'])
            ? ['mode' => 'explicit', 'nims' => $validated['nims']]
            : [
                'mode'          => 'filtered',
                'filters'       => [
                    'graduation_year'          => $validated['graduation_year'] ?? null,
                    'jurusan'                  => $validated['jurusan'] ?? null,
                    'program_id'               => $validated['program_id'] ?? null,
                    'only_without_credentials' => $validated['only_without_credentials'] ?? false,
                ],
                'excluded_nims' => $validated['excluded_nims'] ?? [],
            ];

        $limit = (int) ($validated['limit'] ?? AlumniCredentialService::DEFAULT_LIMIT);

        // Bcrypt di dalam service sengaja lambat -- lihat
        // AlumniCredentialController::issue() untuk penjelasan lengkap.
        set_time_limit(0);

        $result = $this->service->issueForSelection($selection, $validated['after_nim'] ?? null, $limit);

        if ($result['issued']->isEmpty() && empty($validated['after_nim'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada alumni yang cocok dengan seleksi tersebut, sehingga tidak ada akun yang diterbitkan.',
            ], 422);
        }

        $loginUrl = rtrim((string) config('services.frontend.url'), '/') . '/login';
        $batchId  = $validated['batch_id'];

        $queued = 0;
        $skippedNoEmail = 0;
        $userId = $request->user()?->id;

        foreach ($result['issued'] as $row) {
            // Dua alasan berbeda dicek terpisah -- kosong (data memang tidak
            // punya surel) vs format rusak (ada isinya tapi bukan surel sah)
            // -- supaya pesan errornya jelas menunjukkan mana yang terjadi,
            // bukan disamaratakan "tidak punya surel".
            if (empty($row['email'])) {
                AlumniEmailLog::create([
                    'batch_id'      => $batchId,
                    'kind'          => 'account',
                    'created_by'    => $userId,
                    'nim'           => $row['nim'],
                    'name'          => $row['name'],
                    'email'         => '',
                    'status'        => 'failed',
                    'error_message' => 'Tidak ada alamat surel terdaftar.',
                ]);
                $skippedNoEmail++;
                Log::warning("AlumniCredentialEmailController: nim={$row['nim']} dilewati, tidak punya surel.");
                continue;
            }

            if (!EmailFormat::isValid($row['email'])) {
                AlumniEmailLog::create([
                    'batch_id'      => $batchId,
                    'kind'          => 'account',
                    'created_by'    => $userId,
                    'nim'           => $row['nim'],
                    'name'          => $row['name'],
                    'email'         => $row['email'],
                    'status'        => 'failed',
                    'error_message' => "Format alamat surel tidak valid: {$row['email']}",
                ]);
                $skippedNoEmail++;
                Log::warning("AlumniCredentialEmailController: nim={$row['nim']} dilewati, format surel tidak valid ({$row['email']}).");
                continue;
            }

            if (!EmailFormat::hasDeliverableDomain($row['email'])) {
                AlumniEmailLog::create([
                    'batch_id'      => $batchId,
                    'kind'          => 'account',
                    'created_by'    => $userId,
                    'nim'           => $row['nim'],
                    'name'          => $row['name'],
                    'email'         => $row['email'],
                    'status'        => 'failed',
                    'error_message' => "Domain surel tidak dapat menerima email (tidak ada MX/A record): {$row['email']}",
                ]);
                $skippedNoEmail++;
                Log::warning("AlumniCredentialEmailController: nim={$row['nim']} dilewati, domain tidak dapat menerima email ({$row['email']}).");
                continue;
            }

            $log = AlumniEmailLog::create([
                'batch_id' => $batchId,
                'kind'     => 'account',
                'created_by' => $userId,
                'nim'      => $row['nim'],
                'name'     => $row['name'],
                'email'    => $row['email'],
                'status'   => 'queued',
            ]);

            SendAlumniAccountEmailJob::dispatch($log->id, $row['nim'], $row['name'], $row['password'], $row['email'], $loginUrl);

            Log::info("AlumniCredentialEmailController: email untuk nim={$row['nim']} ({$row['email']}) masuk antrean.");
            $queued++;
        }

        $this->audit->record('alumni.credentials_emailed', [
            'entity_type' => 'alumni_profiles',
            'context'     => [
                'batch_id'          => $batchId,
                'queued'            => $queued,
                'skipped_no_email'  => $skippedNoEmail,
                'selection_mode'    => $selection['mode'],
                'graduation_year'   => $selection['filters']['graduation_year'] ?? null,
                'program_id'        => $selection['filters']['program_id'] ?? null,
                'jurusan'           => $selection['filters']['jurusan'] ?? null,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'issued_count'     => $result['issued']->count(),
                'queued'           => $queued,
                'skipped_no_email' => $skippedNoEmail,
                'remaining'        => $result['remaining'],
                'last_nim'         => $result['last_nim'],
            ],
        ]);
    }
}
