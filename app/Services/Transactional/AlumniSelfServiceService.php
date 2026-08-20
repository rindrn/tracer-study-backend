<?php
// app/Services/Transactional/AlumniSelfServiceService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\AlumniProfileRepository;
use Illuminate\Support\Facades\DB;

/**
 * AlumniSelfServiceService — portal "Data Saya" bagi alumni.
 *
 * Mewujudkan dua hak sekaligus: hak atas informasi (UU 27/2022 Pasal 5 --
 * kejelasan identitas pengendali, dasar hukum, dan tujuan pemrosesan) dan hak
 * akses atas salinan datanya sendiri (Pasal 7). Alumni berhak tahu
 * data apa saja tentang dirinya yang disimpan, dari mana asalnya, untuk apa
 * dipakai, sampai kapan disimpan, dan siapa saja yang pernah menyentuhnya.
 *
 * SATU HAL YANG SERING TERLEWAT
 * -----------------------------
 * Hak akses bukan hanya "lihat profil". Yang membuatnya berarti adalah tiga
 * hal yang biasanya tidak ditampilkan: jawaban kuesioner yang pernah dikirim,
 * riwayat siapa yang menyentuh datanya, dan batas masa simpannya. Tanpa
 * ketiganya, halaman ini hanya salinan formulir profil dan tidak menambah
 * kendali apa pun bagi pemiliknya.
 *
 * BATAS YANG DISENGAJA
 * --------------------
 * Alumni TIDAK bisa mengubah apa pun dari sini. Data akademik (NIM, prodi,
 * tahun lulus) menentukan angka keterserapan per prodi, dan menyerahkan
 * suntingannya kepada subjek data berarti menyerahkan angka akreditasi
 * kepada orang yang dinilai. Perubahan ditempuh lewat permintaan koreksi
 * yang ditinjau petugas — lihat DataSubjectRequestService.
 */
class AlumniSelfServiceService
{
    private const CONN = 'oltp';

    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
        private readonly ConsentService          $consent,
        private readonly AuditLogService         $audit,
    ) {}

    /**
     * Seluruh data yang disimpan sistem tentang satu alumni.
     *
     * @throws BusinessException 404 kalau profilnya tidak ada
     */
    public function overview(int $alumniId): array
    {
        $alumni = $this->alumniRepo->findByIdWithProgram($alumniId);

        if (!$alumni) {
            throw new BusinessException('Profil alumni tidak ditemukan.', 404);
        }

        // Pembukaan data pribadi kepada pemiliknya sendiri tetap dicatat.
        // Bukan karena mencurigai alumni, melainkan karena inilah yang
        // membuat jejak audit lengkap: kalau hanya perbuatan staf yang
        // tercatat, riwayat sebuah baris data punya lubang yang tidak bisa
        // dijelaskan saat ditelusuri.
        $this->audit->record('alumni.self_viewed', [
            'entity_type'       => 'alumni_profiles',
            'entity_id'         => $alumniId,
            'subject_alumni_id' => $alumniId,
        ]);

        return [
            'profile'    => $this->profile($alumni),
            'consent'    => $this->consent->state($alumni),
            'retention'  => $this->retention($alumni),
            'responses'  => $this->responses($alumniId),
            // Dibatasi 50 baris terakhir: halaman ini untuk ditengok, bukan
            // untuk diaudit menyeluruh. Penelusuran lengkap adalah pekerjaan
            // Ketua Tracer lewat endpoint audit tersendiri.
            'activity'   => $this->audit->forAlumni($alumniId, 50),
        ];
    }

    /**
     * Identitas yang disimpan.
     *
     * NIK dan NPWP ikut ditampilkan utuh — kepada pemiliknya sendiri, bukan
     * kepada orang lain. Menyamarkannya di sini justru bertentangan dengan
     * tujuan halaman ini: alumni tidak bisa memeriksa apakah NIK-nya salah
     * ketik kalau yang diperlihatkan hanya bulatan.
     */
    private function profile(object $alumni): array
    {
        return [
            'nim'             => $alumni->nim,
            'name'            => $alumni->name,
            'email'           => $alumni->email,
            'phone'           => $alumni->phone,
            'nik'             => $alumni->nik,
            'npwp'            => $alumni->npwp,
            'program_name'    => $alumni->program_name ?? null,
            'program_degree'  => $alumni->program_degree ?? null,
            'jurusan'         => $alumni->jurusan_name ?? null,
            'entry_year'      => $alumni->entry_year,
            'graduation_year' => $alumni->graduation_year,
        ];
    }

    /**
     * Sampai kapan datanya disimpan, dan atas dasar apa.
     *
     * Menyatakan dasar pemrosesan sama wajibnya dengan meminta persetujuan.
     * Alumni yang menarik persetujuannya harus tetap bisa melihat bahwa
     * sebagian datanya tetap disimpan karena kewajiban pelaporan — kalau itu
     * tidak dinyatakan, penarikan persetujuan terasa seperti janji palsu.
     */
    private function retention(object $alumni): array
    {
        $years = (int) config('privacy.retention_years');
        $until = $alumni->graduation_year
            ? (int) $alumni->graduation_year + $years
            : null;

        return [
            'years'       => $years,
            'until_year'  => $until,
            'legal_basis' => [
                'Persetujuan Anda, untuk pengolahan jawaban tracer study menjadi statistik institusi.',
                'Kewajiban hukum pelaporan penyerapan lulusan ke PDDIKTI (Permendikbud No. 7 Tahun 2020 Pasal 36 ayat 7), yang berlaku terlepas dari persetujuan.',
            ],
        ];
    }

    /**
     * Kuesioner yang pernah diisi alumni ini.
     *
     * Yang ditampilkan status dan waktunya, bukan isi jawabannya. Isi jawaban
     * baru berguna kalau bisa dibaca sebagai pasangan pertanyaan-jawaban, dan
     * itu berarti memuat seluruh definisi kuesioner beserta label opsinya —
     * pekerjaan tersendiri yang belum dikerjakan. Ini dicatat terang-terangan
     * sebagai batas yang diketahui, bukan disamarkan.
     */
    private function responses(int $alumniId): array
    {
        return DB::connection(self::CONN)->table('responses')
            ->leftJoin('questionnaires', 'responses.questionnaire_id', '=', 'questionnaires.id')
            ->where('responses.alumni_id', $alumniId)
            ->orderByDesc('responses.created_at')
            ->get([
                'responses.id',
                'responses.status',
                'responses.created_at',
                'questionnaires.title as questionnaire_title',
            ])
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }
}
