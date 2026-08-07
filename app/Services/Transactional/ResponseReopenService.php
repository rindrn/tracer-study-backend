<?php
// app/Services/Transactional/ResponseReopenService.php
namespace App\Services\Transactional;

use App\Repositories\Transactional\AlumniProfileRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;

/**
 * ResponseReopenService — mengembalikan pengisian alumni dari Finish ke
 * Ongoing tanpa menghapus jawaban (RBAC-04, ISI-03).
 *
 * Ada dua pintu menuju ke sini, dan keduanya harus berperilaku sama:
 * jalur langsung Ketua Tracer (AlumniController::resetResponse) dan jalur
 * persetujuan atas permintaan Tim Tracer/Kaprodi (ApprovalController).
 * Penentuan "kuesioner mana saja yang dibuka" ditaruh di satu tempat ini
 * supaya keduanya tidak bisa menyimpang diam-diam.
 *
 * LINGKUP — berubah, baca ini sebelum mengubah lagi.
 *
 * Dulu lingkupnya SELALU seluruh kuesioner aktif alumni, sekalipun petugas
 * hanya menunjuk satu. Itu bukan desain yang diinginkan, melainkan penambal
 * atas dua keterbatasan yang kini sudah hilang:
 *
 *   - `has_responded` dulu bernilai "ada salah satu yang sudah dikirim",
 *     sehingga membuka sebagian tidak ada gunanya — alumni tetap terkunci di
 *     layar "Anda Sudah Mengisi". Sekarang statusnya per kuesioner.
 *   - Formulir dulu menampilkan seluruh kuesioner aktif sekaligus dan
 *     mengirimkannya sekaligus, sehingga kuesioner yang masih terkirim akan
 *     tampil kosong lalu tertimpa. Sekarang bagian yang sudah selesai
 *     disembunyikan, dan submit hanya menyentuh kuesioner yang terbuka.
 *
 * Karena itu lingkupnya kini mengikuti apa yang diminta pemanggil, dan hanya
 * jatuh ke "seluruh kuesioner aktif" bila pemanggil memang tidak menyebut
 * satu pun. Permintaan tetap disaring terhadap kuesioner yang benar-benar
 * aktif bagi alumni tersebut — id di luar itu diabaikan, bukan dipercaya.
 */
class ResponseReopenService
{
    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ResponseRepository      $responseRepo,
    ) {}

    /**
     * @param  array<int>|null $questionnaireIds kuesioner yang diminta dibuka;
     *                         null berarti seluruh kuesioner aktif alumni
     * @return int jumlah pengisian yang dibuka kembali; 0 berarti tidak ada
     *             yang berstatus Finish (mis. sudah dibuka sebelumnya) atau
     *             yang diminta bukan kuesioner aktif alumni ini
     */
    public function reopen(int $alumniId, ?array $questionnaireIds = null): int
    {
        $active = $this->activeQuestionnaireIds($alumniId);

        $scope = $questionnaireIds === null
            ? $active
            : array_values(array_intersect($active, array_map('intval', $questionnaireIds)));

        return $this->responseRepo->reopenForAlumni($alumniId, $scope);
    }

    /**
     * Kuesioner yang sedang aktif bagi seorang alumni.
     *
     * Publik karena pengajuan persetujuan juga memerlukannya: pemohon dari
     * halaman Data Alumni tidak punya konteks kuesioner, jadi backend yang
     * menentukan. Dengan begitu penentuannya tetap satu sumber, tidak ada
     * versi kedua yang bisa menyimpang.
     *
     * @return array<int>
     */
    public function activeQuestionnaireIds(int $alumniId): array
    {
        $alumni = $this->alumniRepo->findByIdWithProgram($alumniId);
        if ($alumni === null) {
            return [];
        }

        // Tahun lulus dipakai kalau ada: kuesioner disaring per angkatan
        // lewat target_graduation_years. Alumni tanpa tahun lulus jatuh ke
        // penyaringan prodi saja, sama seperti jalur pengisiannya.
        $questionnaires = $alumni->graduation_year !== null
            ? $this->questionnaireRepo->findActiveForProdiAndYear(
                (int) $alumni->program_id,
                (int) $alumni->graduation_year,
            )
            : $this->questionnaireRepo->findActiveForProdi((int) $alumni->program_id);

        return $questionnaires->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
