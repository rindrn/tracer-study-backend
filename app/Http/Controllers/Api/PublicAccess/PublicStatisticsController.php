<?php

namespace App\Http\Controllers\Api\PublicAccess;

use App\Http\Controllers\Controller;
use App\Repositories\Analytical\ResponseRateRepository;
use App\Services\Transactional\PublicDisplaySettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Progress pengisian Tracer Study per program studi, untuk masyarakat umum --
 * TANPA autentikasi.
 *
 * Angkanya TIDAK dihitung ulang di sini: ResponseRateRepository::getBarData()
 * sudah menghasilkan tiga bucket yang sama (Selesai / Sedang Mengisi / Belum)
 * dari tabel OLTP langsung, dan sudah menangani kasus satu alumni yang mengisi
 * kuesioner global DAN kuesioner prodi supaya tidak terhitung dua kali.
 * Menyalin query-nya ke sini berarti dua definisi "sudah mengisi" yang bisa
 * lepas sinkron.
 *
 * Bedanya dengan endpoint dashboard: di sini tidak ada scoping prodi/jurusan
 * (pengunjung bukan siapa-siapa), tapi ADA batas rentang tahun pengarsipan
 * yang ditegakkan di server.
 */
class PublicStatisticsController extends Controller
{
    public function __construct(
        private readonly ResponseRateRepository $repo,
        private readonly PublicDisplaySettingService $settings,
    ) {}

    /**
     * GET /api/public/statistics/years
     *
     * Tahun lulusan yang boleh ditampilkan publik: yang punya data DAN masuk
     * rentang pengarsipan.
     */
    public function years(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'years' => $this->settings->getAvailableYears(),
                'range' => $this->settings->getRange(),
            ],
        ]);
    }

    /**
     * GET /api/public/statistics/progress[?graduation_year=2025]
     *
     * Balasannya selalu berupa daftar kelompok, satu kelompok per angkatan,
     * masing-masing berisi barisnya sendiri per program studi.
     *
     * Tanpa `graduation_year`, seluruh angkatan yang boleh ditampilkan publik
     * dikembalikan sekaligus, TETAP TERPISAH per angkatan. Angkanya sengaja
     * TIDAK dijumlahkan menjadi satu: satu program studi yang sudah rampung
     * pada satu angkatan tetapi tertinggal pada angkatan lain akan tampak
     * sedang-sedang saja bila keduanya dilebur, sehingga justru menyembunyikan
     * keadaan yang perlu dilihat. Situs lama pun menyajikannya bertumpuk per
     * angkatan.
     *
     * Yang dikembalikan hanya angkatan dalam rentang pengarsipan (LAP-04),
     * bukan seluruh angkatan yang pernah ada. Menyapu seluruh isi tabel akan
     * melewati pengarsipan itu diam-diam, sekaligus menimbulkan beban yang
     * justru hendak dihindarinya.
     */
    public function progress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'graduation_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
        ]);

        if (isset($validated['graduation_year'])) {
            $year = (int) $validated['graduation_year'];

            // Ditolak di server, bukan disaring di frontend saja -- kalau tidak,
            // pengarsipan bisa dilewati cukup dengan mengetik tahun lain di URL.
            if (!$this->settings->isYearVisible($year)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data angkatan tersebut tidak ditampilkan untuk publik.',
                ], 404);
            }

            $years = [$year];
        } else {
            $years = $this->settings->getAvailableYears();

            if (empty($years)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada angkatan yang ditampilkan untuk publik.',
                ], 404);
            }
        }

        // Angkatan terbaru lebih dahulu, mengikuti urutan yang dipakai situs
        // lama maupun daftar tahun pada endpoint years().
        $years = collect($years)->map(fn ($y) => (int) $y)->sortDesc()->values();

        $groups = $years->map(function (int $year) {
            $items = $this->repo->getBarData(graduationYear: (string) $year)
                ->map(fn (array $r) => [
                    'prodi'      => $this->prodiLabel($r),
                    'nama_prodi' => $r['nama_prodi'],
                    'jenjang'    => $r['jenjang'],
                    'finish'     => $r['count_submitted'],
                    'ongoing'    => $r['count_ongoing'],
                    'belum'      => $r['count_started'],
                    'jumlah'     => $r['total'],
                    // Persentase dihitung dari yang SELESAI saja, bukan
                    // selesai+sedang -- pengisian yang belum dikirim belum jadi
                    // respons, sama dengan definisi di situs lama.
                    'persentase' => $r['total'] > 0
                        ? round($r['count_submitted'] / $r['total'] * 100, 2)
                        : 0.0,
                ])
                ->sortBy('prodi', SORT_NATURAL)
                ->values();

            return [
                'graduation_year' => $year,
                'items'           => $items,
                'summary'         => [
                    'finish'  => $items->sum('finish'),
                    'ongoing' => $items->sum('ongoing'),
                    'belum'   => $items->sum('belum'),
                    'jumlah'  => $items->sum('jumlah'),
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => [
                // Angkatan mana saja yang benar-benar disertakan. Dikirim
                // supaya pengunjung tidak menyangka "semua angkatan" mencakup
                // angkatan yang sedang diarsipkan dari tampilan publik.
                'included_years' => $years->all(),
                'groups'         => $groups,
            ],
        ]);
    }

    /**
     * Label seperti "DIII - Teknik Mesin". Jenjang D3/D4 di kolom programs
     * ditulis angka Arab, sedangkan situs publik memakai angka Romawi.
     */
    private function prodiLabel(array $row): string
    {
        $jenjang = match ($row['jenjang']) {
            'D3'    => 'DIII',
            'D4'    => 'DIV',
            default => $row['jenjang'] ?? '',
        };

        return trim("{$jenjang} - {$row['nama_prodi']}", ' -');
    }
}
