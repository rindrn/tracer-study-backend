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
 * Lingkupnya SELALU seluruh kuesioner yang sedang aktif bagi alumni
 * tersebut, bukan hanya kuesioner yang kebetulan sedang dibuka petugas.
 * Alasannya ada di ResponseRepository::reopenForAlumni().
 */
class ResponseReopenService
{
    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ResponseRepository      $responseRepo,
    ) {}

    /**
     * @return int jumlah pengisian yang dibuka kembali; 0 berarti tidak ada
     *             yang berstatus Finish (mis. sudah dibuka sebelumnya)
     */
    public function reopen(int $alumniId): int
    {
        return $this->responseRepo->reopenForAlumni(
            $alumniId,
            $this->activeQuestionnaireIds($alumniId),
        );
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
