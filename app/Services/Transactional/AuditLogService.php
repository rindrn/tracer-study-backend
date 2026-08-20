<?php
// app/Services/Transactional/AuditLogService.php
namespace App\Services\Transactional;

use App\Models\Transactional\AlumniProfile;
use App\Models\Transactional\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * AuditLogService — pencatat jejak audit (UU 27/2022, proposal BAB 2.3).
 *
 * PRINSIP: MENCATAT TIDAK BOLEH MENGGAGALKAN
 * ------------------------------------------
 * Seluruh penulisan dibungkus try/catch dan kegagalannya hanya masuk ke log
 * aplikasi. Alasannya: kalau tabel audit penuh, terkunci, atau kolomnya
 * berubah, akibatnya tidak boleh berupa alumni gagal mengirim kuesioner atau
 * staf gagal mengekspor. Jejak audit adalah pengamat, bukan penjaga pintu.
 *
 * Konsekuensinya harus diakui terang-terangan: jejak ini bisa berlubang
 * tanpa ada yang tahu selain lewat laravel.log. Untuk sistem yang tunduk
 * pada pemeriksaan yang lebih keras daripada PKM, pilihannya akan terbalik —
 * gagal mencatat berarti gagal memproses.
 *
 * APA YANG DICATAT
 * ----------------
 * Bukan setiap perbuatan, melainkan yang menyentuh data pribadi:
 *
 *   - persetujuan diberikan dan ditarik           (consent.*)
 *   - data alumni dibuat, diubah, dihapus         (alumni.*)
 *   - kredensial alumni diterbitkan               (alumni.credentials_issued)
 *   - data pribadi keluar sistem lewat ekspor     (export.*)
 *   - masuk dan gagal masuk                       (auth.*)
 *   - permintaan hak subjek data                  (dsr.*)
 *
 * Perbuatan yang tidak menyentuh data pribadi — mengubah threshold, menyusun
 * kuesioner, menjalankan ETL — SENGAJA tidak dicatat di sini. Jejak audit
 * yang penuh derau adalah jejak audit yang tidak pernah dibaca.
 *
 * APA YANG TIDAK BOLEH MASUK
 * --------------------------
 * Nilai data pribadi yang utuh. Tabel ini tidak terenkripsi, jadi menyalin
 * NIK ke kolom `context` akan meniadakan gunanya mengenkripsi kolom aslinya.
 * Pakai PersonalData::mask() sebelum memasukkan apa pun yang menyerupai
 * pengenal.
 */
class AuditLogService
{
    private const CONN  = 'oltp';
    private const TABLE = 'audit_logs';

    /**
     * Catat satu peristiwa.
     *
     * Pelakunya tidak diminta sebagai parameter melainkan disimpulkan dari
     * sesi yang sedang berjalan — supaya tiap pemanggil tidak perlu tahu
     * apakah yang sedang masuk itu staf, alumni, atau tidak ada siapa-siapa.
     * Lewatkan $actor secara eksplisit hanya kalau peristiwanya menyangkut
     * pelaku yang bukan pemilik sesi (mis. mencatat percobaan masuk yang
     * gagal, ketika belum ada sesi sama sekali).
     *
     * @param string      $action          mis. 'consent.granted'
     * @param array{
     *     entity_type?: string,
     *     entity_id?: string|int,
     *     subject_alumni_id?: int,
     *     context?: array,
     * } $attributes
     */
    public function record(string $action, array $attributes = []): void
    {
        try {
            [$actorType, $actorId, $actorLabel] = $this->resolveActor($attributes);

            DB::connection(self::CONN)->table(self::TABLE)->insert([
                'actor_type'        => $actorType,
                'actor_id'          => $actorId,
                'actor_label'       => $actorLabel,
                'action'            => $action,
                'entity_type'       => $attributes['entity_type'] ?? null,
                'entity_id'         => isset($attributes['entity_id'])
                    ? (string) $attributes['entity_id']
                    : null,
                'subject_alumni_id' => $attributes['subject_alumni_id'] ?? null,
                'ip_address'        => Request::ip(),
                // Dipotong, bukan dibiarkan panjang: kolomnya 255 karakter dan
                // user agent peramban modern rutin melampauinya. Tanpa potongan
                // ini seluruh pencatatan gagal diam-diam untuk sebagian
                // peramban saja — kegagalan yang paling sulit disadari.
                'user_agent'        => mb_substr((string) Request::userAgent(), 0, 255) ?: null,
                'context'           => isset($attributes['context'])
                    ? json_encode($attributes['context'], JSON_UNESCAPED_UNICODE)
                    : null,
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            // Lihat catatan "mencatat tidak boleh menggagalkan" di atas.
            Log::error('Gagal menulis jejak audit', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Riwayat perlakuan atas data seorang alumni.
     *
     * Dipakai dua pihak dengan alasan berbeda: alumni yang ingin tahu siapa
     * saja yang menyentuh datanya (hak akses), dan Ketua Tracer yang menelusuri
     * satu kasus. Karena itu ia mengembalikan data mentah, dan penyaringan
     * siapa boleh melihat apa ditegakkan di controller masing-masing.
     */
    public function forAlumni(int $alumniId, int $limit = 100): array
    {
        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('subject_alumni_id', $alumniId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'action'     => $row->action,
                'actor'      => $row->actor_label ?? 'Sistem',
                'actor_type' => $row->actor_type,
                'context'    => $row->context ? json_decode($row->context, true) : null,
                'created_at' => $row->created_at,
            ])
            ->toArray();
    }

    /**
     * Tentukan pelaku dari sesi yang sedang berjalan.
     *
     * `Auth::user()` tidak dipakai karena aplikasi ini punya dua guard yang
     * sengaja dipisah (lihat AlumniProfile). Keduanya diperiksa berurutan,
     * staf lebih dulu — sebuah permintaan tidak pernah membawa dua-duanya.
     *
     * @return array{0:string, 1:?int, 2:?string}
     */
    private function resolveActor(array $attributes): array
    {
        // Pelaku yang dilewatkan eksplisit selalu menang: hanya dipakai untuk
        // peristiwa yang terjadi SEBELUM ada sesi, mis. percobaan masuk gagal.
        if (isset($attributes['actor_type'])) {
            return [
                $attributes['actor_type'],
                $attributes['actor_id'] ?? null,
                $attributes['actor_label'] ?? null,
            ];
        }

        // Dua guard diperiksa terpisah, BUKAN lewat auth()->user() tanpa nama.
        // Guard 'sanctum' dipatok ke provider users dan guard 'alumni' ke
        // provider alumni (lihat config/auth.php beserta alasannya) — guard
        // bawaan tidak akan pernah mengenali token alumni, jadi memanggil satu
        // saja membuat seluruh perbuatan alumni tercatat sebagai 'system'.
        $user = auth('sanctum')->user() ?? auth('alumni')->user();

        if ($user instanceof User) {
            // Nama DAN surel disimpan sebagai satu teks, bukan sekadar id:
            // inilah yang membuat baris log tetap terbaca setelah akunnya
            // dihapus (DATA-08).
            return ['user', $user->id, trim("{$user->name} <{$user->email}>")];
        }

        if ($user instanceof AlumniProfile) {
            return ['alumni', $user->id, trim("{$user->name} ({$user->nim})")];
        }

        return ['system', null, null];
    }
}
