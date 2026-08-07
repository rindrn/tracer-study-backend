<?php

namespace App\Repositories\Transactional;

use Illuminate\Support\Facades\DB;

/**
 * Ringkasan agregat per TAHUN LULUSAN.
 *
 * Dipakai halaman kartu tahun (alumni-data, student-management,
 * questionnaire-results, form-management) sebagai layar antara sebelum
 * pengguna memilih satu tahun. Tujuannya menunda penarikan data berat --
 * daftar kuesioner saja berukuran ~279 KB -- sampai tahun dipilih.
 *
 * CATATAN PENTING soal dua "tahun" yang berbeda:
 *
 *   - questionnaires.period_year          = TAHUN PELAKSANAAN tracer.
 *                                           Saat ini seluruh 111 kuesioner
 *                                           bernilai 2026, jadi TIDAK cocok
 *                                           dipakai sebagai pengelompok kartu.
 *   - questionnaires.target_graduation_years = TAHUN LULUSAN yang disasar,
 *                                           berupa jsonb array. Inilah sumbu
 *                                           yang dipakai di sini supaya
 *                                           seragam dengan alumni_profiles.
 *                                           graduation_year.
 *
 * Semua angka dihitung dengan COUNT(DISTINCT ...) karena satu alumni bisa
 * punya lebih dari satu response (kuesioner wajib + kuesioner tambahan
 * prodi). Tanpa DISTINCT, alumni yang mengisi keduanya terhitung dua kali --
 * kekeliruan yang sama seperti Temuan T-07.
 */
class RingkasanTahunRepository
{
    private const CONN = 'oltp';

    /**
     * @param array{program_id?: int|null, jurusan?: string|null} $scope
     * @return array<int, array{tahun:int, alumni:int, sudah_mengisi:int, belum_mengisi:int, response_rate:float, kuesioner:int, periode:array<int,int>}>
     */
    public function byGraduationYear(array $scope = []): array
    {
        $programId = $scope['program_id'] ?? null;
        $jurusan   = $scope['jurusan'] ?? null;

        // ── 1. Sisi alumni: total & yang sudah mengisi, per tahun lulus ──
        $alumniQuery = DB::connection(self::CONN)
            ->table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', function ($join) {
                $join->on('r.alumni_id', '=', 'ap.id')->where('r.status', '=', 'submitted');
            })
            ->whereNotNull('ap.graduation_year')
            ->select([
                'ap.graduation_year as tahun',
                DB::raw('COUNT(DISTINCT ap.id) as alumni'),
                DB::raw('COUNT(DISTINCT r.alumni_id) as sudah_mengisi'),
            ])
            ->groupBy('ap.graduation_year');

        if ($programId !== null) {
            $alumniQuery->where('ap.program_id', $programId);
        } elseif ($jurusan !== null) {
            $alumniQuery->where('p.jurusan', $jurusan);
        }

        $alumniRows = $alumniQuery->get()->keyBy('tahun');

        // ── 2. Sisi kuesioner: jumlah kuesioner yang menyasar tiap tahun ──
        //
        // target_graduation_years adalah jsonb array (mis. [2024]), jadi
        // dibongkar dengan jsonb_array_elements_text supaya bisa di-GROUP BY
        // per tahun. Kuesioner tanpa target (NULL atau []) sengaja TIDAK
        // dihitung ke tahun mana pun -- kuesioner semacam itu berlaku umum
        // dan akan tetap muncul saat tahun dibuka.
        // Bentuk "FROM a, fungsi(a.kolom) AS t(...)" adalah LATERAL implisit
        // di PostgreSQL -- cara paling ringkas membongkar jsonb array tanpa
        // perlu join builder yang berbelit.
        $questionnaireQuery = DB::connection(self::CONN)
            ->table(DB::raw('questionnaires as q, jsonb_array_elements_text(q.target_graduation_years) as t(tahun)'))
            ->select([
                DB::raw('t.tahun::int as tahun'),
                DB::raw('COUNT(*) as kuesioner'),
                DB::raw('MIN(q.period_year) as periode_min'),
                DB::raw('MAX(q.period_year) as periode_max'),
            ])
            ->whereNotNull('q.target_graduation_years')
            ->whereRaw("jsonb_typeof(q.target_graduation_years) = 'array'")
            ->groupBy(DB::raw('t.tahun::int'));

        if ($programId !== null) {
            // Kuesioner nasional (program_id NULL) berlaku untuk semua prodi,
            // jadi tetap ikut dihitung bagi Kaprodi.
            $questionnaireQuery->where(function ($w) use ($programId) {
                $w->whereNull('q.program_id')->orWhere('q.program_id', $programId);
            });
        } elseif ($jurusan !== null) {
            $questionnaireQuery->where(function ($w) use ($jurusan) {
                $w->whereNull('q.program_id')
                  ->orWhereIn('q.program_id', function ($sub) use ($jurusan) {
                      $sub->from('programs')->select('id')->where('jurusan', $jurusan);
                  });
            });
        }

        $questionnaireRows = $questionnaireQuery->get()->keyBy('tahun');

        // ── 2b. Sisi kontak penilai: jumlah kontak per tahun lulus ──
        //
        // Dipakai kartu tahun di halaman Kontak Penilai. Halaman itu tidak
        // berurusan dengan progres pengisian sama sekali, sehingga angka yang
        // berguna di sana adalah "berapa kontak yang akan saya kerjakan",
        // bukan "berapa persen alumni sudah mengisi".
        //
        // Lingkupnya mengikuti penyaring yang sama dengan sisi alumni supaya
        // Kaprodi dan Kajur tidak melihat cacah di luar cakupannya.
        $kontakQuery = DB::connection(self::CONN)
            ->table('stakeholder_contacts as sc')
            ->join('alumni_profiles as ap', 'sc.alumni_id', '=', 'ap.id')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->whereNotNull('ap.graduation_year')
            ->select([
                'ap.graduation_year as tahun',
                DB::raw('COUNT(*) as kontak'),
            ])
            ->groupBy('ap.graduation_year');

        if ($programId !== null) {
            $kontakQuery->where('ap.program_id', $programId);
        } elseif ($jurusan !== null) {
            $kontakQuery->where('p.jurusan', $jurusan);
        }

        $kontakRows = $kontakQuery->get()->keyBy('tahun');

        // ── 3. Gabungkan. Union tahun dari kedua sisi supaya tahun yang
        //       hanya punya alumni (mis. 2020, 2021 yang belum disasar
        //       kuesioner apa pun) tetap muncul sebagai kartu redup.
        $allYears = collect($alumniRows->keys())
            ->merge($questionnaireRows->keys())
            ->map(fn ($t) => (int) $t)
            ->unique()
            ->sortDesc()
            ->values();

        return $allYears->map(function (int $year) use ($alumniRows, $questionnaireRows, $kontakRows) {
            $a = $alumniRows->get($year);
            $k = $questionnaireRows->get($year);

            $total  = (int) ($a->alumni ?? 0);
            $responded = (int) ($a->sudah_mengisi ?? 0);

            $periods = [];
            if ($k) {
                $periods = $k->periode_min === $k->periode_max
                    ? [(int) $k->periode_min]
                    : [(int) $k->periode_min, (int) $k->periode_max];
            }

            return [
                'tahun'         => $year,
                'alumni'        => $total,
                'sudah_mengisi' => $responded,
                'belum_mengisi' => $total - $responded,
                'response_rate' => $total > 0 ? round($responded / $total * 100, 1) : 0.0,
                'kuesioner'     => (int) ($k->kuesioner ?? 0),
                'kontak'        => (int) ($kontakRows->get($year)->kontak ?? 0),
                'periode'       => $periods,
            ];
        })->all();
    }
}
