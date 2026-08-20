<?php
// app/Services/Transactional/DataSubjectRequestService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * DataSubjectRequestService — permintaan alumni atas datanya sendiri.
 *
 * Melayani tiga hak dari UU 27/2022 yang tidak bisa dijalankan otomatis:
 * perbaikan data (Pasal 6), pengakhiran pemrosesan dan penghapusan (Pasal 8),
 * serta penundaan atau pembatasan pemrosesan (Pasal 11) yang di antarmuka
 * disebut "keberatan".
 *
 * Catatan penamaan: Pasal 10 juga memakai kata "keberatan", tetapi cakupannya
 * sempit -- keberatan atas keputusan yang diambil sepenuhnya secara otomatis,
 * termasuk pemrofilan. SmartTracer tidak mengambil keputusan otomatis apa pun
 * atas alumni, jadi yang relevan di sini Pasal 11, bukan Pasal 10.
 *
 * Bentuk antrean ini juga yang dikehendaki Pasal 14: pelaksanaan hak subjek
 * data diajukan melalui PERMOHONAN TERCATAT, elektronik maupun nonelektronik.
 * Tabel permintaan inilah wujud "tercatat" itu.
 *
 * Hak akses (Pasal 7) dan hak atas informasi (Pasal 5) tidak lewat sini — sudah dilayani langsung oleh
 * endpoint "Data Saya" tanpa perlu antre, karena tidak ada yang perlu
 * ditimbang untuk memperlihatkan data seseorang kepada dirinya sendiri.
 *
 * KENAPA DITINJAU MANUSIA
 * -----------------------
 * Bukan karena tidak sempat membuat otomatisasinya, melainkan karena
 * keputusannya memang bukan keputusan mesin:
 *
 *   - Koreksi data akademik menggeser angka keterserapan per prodi. Alumni
 *     yang bisa memindahkan dirinya ke prodi lain berarti bisa menggeser
 *     angka akreditasi prodi itu.
 *   - Penghapusan berbenturan dengan kewajiban pelaporan PDDIKTI, yang
 *     merupakan dasar pemrosesan tersendiri di luar persetujuan. Mana yang
 *     menang bergantung pada data apa yang diminta dihapus.
 *
 * Yang dijamin sistem karena itu bukan "permintaan dikabulkan", melainkan
 * "permintaan tercatat, tidak bisa hilang, dan dijawab tertulis".
 */
class DataSubjectRequestService
{
    private const CONN = 'oltp';

    public const TYPES = ['correction', 'erasure', 'objection'];

    /** Batas permintaan terbuka per alumni. Lihat alasannya di submit(). */
    private const MAX_OPEN_PER_ALUMNI = 3;

    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Ajukan permintaan baru.
     *
     * @throws BusinessException 422 kalau tipenya tidak dikenal
     * @throws BusinessException 429 kalau permintaan terbuka sudah menumpuk
     */
    public function submit(int $alumniId, string $type, string $message): array
    {
        if (!in_array($type, self::TYPES, strict: true)) {
            throw new BusinessException('Jenis permintaan tidak dikenal.', 422);
        }

        // Pembatas ini melindungi petugas, bukan sistem. Tanpa batas, satu
        // orang yang tidak sabar bisa mengirim puluhan permintaan serupa dan
        // antrean tinjauan jadi tidak terbaca — yang justru memperlambat
        // jawaban bagi dirinya sendiri dan bagi orang lain.
        $open = DB::connection(self::CONN)->table('data_subject_requests')
            ->where('alumni_id', $alumniId)
            ->whereIn('status', ['pending', 'in_review'])
            ->count();

        if ($open >= self::MAX_OPEN_PER_ALUMNI) {
            throw new BusinessException(
                'Anda masih memiliki permintaan yang belum selesai ditinjau. Tunggu jawabannya sebelum mengajukan yang baru.',
                429,
            );
        }

        $now = now();

        $id = DB::connection(self::CONN)->table('data_subject_requests')->insertGetId([
            'alumni_id'  => $alumniId,
            'type'       => $type,
            'message'    => $message,
            'status'     => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit->record('dsr.submitted', [
            'entity_type'       => 'data_subject_requests',
            'entity_id'         => $id,
            'subject_alumni_id' => $alumniId,
            'context'           => ['type' => $type],
        ]);

        return ['id' => $id, 'status' => 'pending', 'created_at' => $now->toIso8601String()];
    }

    /** Riwayat permintaan milik satu alumni. */
    public function listForAlumni(int $alumniId): array
    {
        return DB::connection(self::CONN)->table('data_subject_requests')
            ->where('alumni_id', $alumniId)
            ->orderByDesc('created_at')
            ->get([
                'id', 'type', 'message', 'status', 'response',
                'handled_at', 'created_at',
            ])
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Antrean tinjauan bagi Ketua Tracer.
     *
     * Digabung ke alumni_profiles supaya petugas melihat siapa yang meminta
     * tanpa perlu memanggil endpoint kedua per baris. NIK dan NPWP TIDAK ikut
     * diambil — menampilkannya di layar antrean tidak menambah apa pun untuk
     * memutuskan, dan setiap tempat yang menampilkannya adalah satu tempat
     * lagi yang bisa bocor.
     */
    public function listForReview(?string $status = null): array
    {
        $query = DB::connection(self::CONN)->table('data_subject_requests')
            ->leftJoin('alumni_profiles', 'data_subject_requests.alumni_id', '=', 'alumni_profiles.id')
            ->select(
                'data_subject_requests.*',
                'alumni_profiles.nim as alumni_nim',
                'alumni_profiles.name as alumni_name',
            );

        if ($status) {
            $query->where('data_subject_requests.status', $status);
        }

        return $query->orderByDesc('data_subject_requests.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Ubah status permintaan dan tulis jawabannya.
     *
     * @throws BusinessException 404 kalau permintaannya tidak ada
     * @throws BusinessException 422 kalau statusnya tidak dikenal atau penolakan tanpa alasan
     */
    public function resolve(int $requestId, string $status, ?string $response, object $handler): array
    {
        $allowed = ['in_review', 'fulfilled', 'rejected'];

        if (!in_array($status, $allowed, strict: true)) {
            throw new BusinessException('Status permintaan tidak dikenal.', 422);
        }

        $request = DB::connection(self::CONN)->table('data_subject_requests')
            ->where('id', $requestId)
            ->first();

        if (!$request) {
            throw new BusinessException('Permintaan tidak ditemukan.', 404);
        }

        // Penolakan tanpa alasan bukan jawaban. UU PDP memberi subjek data
        // hak atas kejelasan, dan alasan tertulis juga yang membuat penolakan
        // bisa dipersoalkan kalau keliru.
        if ($status === 'rejected' && trim((string) $response) === '') {
            throw new BusinessException('Penolakan wajib disertai alasan tertulis.', 422);
        }

        $now      = now();
        $isClosed = in_array($status, ['fulfilled', 'rejected'], strict: true);

        DB::connection(self::CONN)->table('data_subject_requests')
            ->where('id', $requestId)
            ->update([
                'status'           => $status,
                'response'         => $response,
                'handled_by'       => $handler->id,
                'handled_by_label' => trim("{$handler->name} <{$handler->email}>"),
                // Hanya diisi saat permintaannya benar-benar selesai. Diisi
                // juga saat berpindah ke 'in_review' akan membuat laporan
                // "berapa lama permintaan dijawab" menghitung waktu mulai
                // meninjau, bukan waktu selesai.
                'handled_at'       => $isClosed ? $now : null,
                'updated_at'       => $now,
            ]);

        $this->audit->record("dsr.{$status}", [
            'entity_type'       => 'data_subject_requests',
            'entity_id'         => $requestId,
            'subject_alumni_id' => $request->alumni_id,
            'context'           => ['type' => $request->type],
        ]);

        return ['id' => $requestId, 'status' => $status];
    }
}
