<?php
// app/Services/Transactional/ConsentService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * ConsentService — persetujuan alumni atas pemrosesan data pribadinya.
 *
 * DASAR
 * -----
 * UU 27/2022 Pasal 20 mewajibkan pengendali data memiliki dasar pemrosesan,
 * dan persetujuan eksplisit adalah salah satunya. Pasal 21 mewajibkan
 * penyampaian informasi tentang tujuan, jenis data, masa simpan, dan hak
 * subjek data. Pasal 22 menentukan bentuknya: persetujuan diberikan secara
 * tertulis atau terekam, elektronik maupun nonelektronik. Pasal 24 mewajibkan
 * pengendali menunjukkan BUKTI persetujuan -- dan pasal itulah alasan
 * persetujuan di sini disimpan sebagai baris data lengkap dengan waktu, versi,
 * dan alamat asal, bukan sekadar penanda benar/salah.
 *
 * Yang diwujudkan di sini adalah bentuk minimum yang jujur: alumni membaca
 * pemberitahuan, menyetujui secara aktif, dan persetujuannya tercatat lengkap
 * dengan versi teks yang disetujui.
 *
 * PERSETUJUAN TERIKAT VERSI, BUKAN SEKADAR TANGGAL
 * ------------------------------------------------
 * Menyimpan tanggal saja membuat sistem tidak bisa menjawab pertanyaan
 * terpenting: alumni ini menyetujui APA. Begitu tujuan pemrosesan bertambah,
 * seluruh persetujuan lama tidak lagi mencakup tujuan barunya, dan tanpa
 * kolom versi tidak ada cara membedakan siapa yang perlu diminta ulang.
 * Karena itu isCurrent() membandingkan versi, bukan hanya memeriksa
 * consent_at terisi.
 *
 * PENARIKAN TIDAK MENGHAPUS DATA
 * ------------------------------
 * Menarik persetujuan menghentikan pemrosesan ke depan: alumni tidak lagi
 * bisa mengisi kuesioner sampai menyetujui ulang. Ia TIDAK menghapus jawaban
 * yang sudah masuk, karena data itu sudah menyatu ke dalam agregat pelaporan
 * PDDIKTI dan akreditasi yang tunduk pada kewajiban hukum tersendiri.
 * Penghapusan ditempuh lewat permintaan yang ditinjau manusia — lihat
 * DataSubjectRequestService. Perbedaan ini harus dinyatakan terang-terangan
 * di teks pemberitahuan, bukan disembunyikan di kode.
 */
class ConsentService
{
    private const CONN = 'oltp';

    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    /** Versi pemberitahuan privasi yang sedang berlaku. */
    public function currentVersion(): string
    {
        return (string) config('privacy.notice_version');
    }

    /**
     * Apakah alumni ini sudah menyetujui versi yang sedang berlaku.
     *
     * @param object $alumni baris alumni_profiles (dari repository, sudah didekripsi)
     */
    public function isCurrent(object $alumni): bool
    {
        return !empty($alumni->consent_at)
            && (string) ($alumni->consent_version ?? '') === $this->currentVersion();
    }

    /**
     * Keadaan persetujuan seorang alumni, siap dikirim ke antarmuka.
     *
     * Dikirim ikut pada respons login supaya frontend bisa memutuskan
     * menampilkan layar persetujuan SEBELUM formulir dimuat — bukan setelah
     * alumni selesai mengisi lalu ditolak server, yang membuang seluruh
     * pekerjaannya dan merusak response rate.
     */
    public function state(object $alumni): array
    {
        return [
            'granted'          => $this->isCurrent($alumni),
            'granted_at'       => $alumni->consent_at ?? null,
            'granted_version'  => $alumni->consent_version ?? null,
            'current_version'  => $this->currentVersion(),
            // Membedakan "belum pernah menyetujui" dari "menyetujui versi
            // lama". Keduanya sama-sama menghalangi pengisian, tapi kalimat
            // yang pantas ditampilkan berbeda: yang satu perkenalan, yang
            // lain pemberitahuan bahwa ketentuannya berubah.
            'needs_renewal'    => !empty($alumni->consent_at) && !$this->isCurrent($alumni),
            'retention_years'  => (int) config('privacy.retention_years'),
        ];
    }

    /** Catat persetujuan alumni atas versi yang sedang berlaku. */
    public function grant(int $alumniId): array
    {
        $version = $this->currentVersion();
        $now     = now();

        DB::connection(self::CONN)->table('alumni_profiles')
            ->where('id', $alumniId)
            ->update([
                'consent_at'      => $now,
                'consent_version' => $version,
                'consent_ip'      => Request::ip(),
                'updated_at'      => $now,
            ]);

        $this->audit->record('consent.granted', [
            'entity_type'       => 'alumni_profiles',
            'entity_id'         => $alumniId,
            'subject_alumni_id' => $alumniId,
            'context'           => ['version' => $version],
        ]);

        return [
            'granted_at'      => $now->toIso8601String(),
            'granted_version' => $version,
        ];
    }

    /**
     * Tarik persetujuan.
     *
     * Ketiga kolom dikosongkan sekaligus. Menyisakan consent_version terisi
     * sementara consent_at kosong akan membuat isCurrent() bergantung pada
     * urutan pemeriksaan, dan keadaan setengah-setengah semacam itu adalah
     * sumber bug yang tidak pernah kelihatan sampai audit.
     */
    public function withdraw(int $alumniId): void
    {
        $previous = DB::connection(self::CONN)->table('alumni_profiles')
            ->where('id', $alumniId)
            ->value('consent_version');

        DB::connection(self::CONN)->table('alumni_profiles')
            ->where('id', $alumniId)
            ->update([
                'consent_at'      => null,
                'consent_version' => null,
                'consent_ip'      => null,
                'updated_at'      => now(),
            ]);

        $this->audit->record('consent.withdrawn', [
            'entity_type'       => 'alumni_profiles',
            'entity_id'         => $alumniId,
            'subject_alumni_id' => $alumniId,
            'context'           => ['previous_version' => $previous],
        ]);
    }

    /**
     * Hentikan pemrosesan kalau alumni belum menyetujui versi yang berlaku.
     *
     * Dipanggil di gerbang setiap jalur yang menuliskan jawaban alumni —
     * pengiriman kuesioner maupun penyimpanan draf. Draf ikut dijaga karena
     * autosave juga pemrosesan data pribadi; melindungi pengiriman saja akan
     * menyisakan jalur yang menyimpan jawaban tanpa dasar hukum.
     *
     * @throws BusinessException 451 kalau persetujuannya belum ada atau kedaluwarsa
     */
    public function assertGranted(object $alumni): void
    {
        if ($this->isCurrent($alumni)) {
            return;
        }

        // 451 Unavailable For Legal Reasons, bukan 403. Bedanya penting bagi
        // frontend: 403 berarti "Anda tidak berhak" dan berakhir di layar
        // galat, sedangkan yang terjadi di sini adalah "ada satu langkah yang
        // belum ditempuh" dan jalan keluarnya membuka layar persetujuan.
        $neverGranted = empty($alumni->consent_at);

        throw new BusinessException(
            $neverGranted
                ? 'Anda belum menyetujui pemberitahuan privasi. Setujui terlebih dahulu untuk melanjutkan pengisian.'
                : 'Pemberitahuan privasi telah diperbarui. Setujui versi terbaru untuk melanjutkan pengisian.',
            451,
            // Kode mesin ikut dikirim supaya frontend tidak perlu mencocokkan
            // kalimat untuk tahu apa yang harus dibuka. Mencocokkan kalimat
            // berarti setiap perbaikan ejaan di sisi server diam-diam
            // mematahkan sisi klien.
            [
                'error'           => 'consent_required',
                'needs_renewal'   => !$neverGranted,
                'current_version' => $this->currentVersion(),
            ],
        );
    }
}
