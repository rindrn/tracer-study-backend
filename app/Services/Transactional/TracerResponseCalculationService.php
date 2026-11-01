<?php
// app/Services/Transactional/TracerResponseCalculationService.php
namespace App\Services\Transactional;

use App\Traits\WithCache;
use Illuminate\Support\Facades\DB;

class TracerResponseCalculationService
{
    use WithCache;

    private const MARGIN_ERROR = 0.023;

    public function recalculate(int $programId, int $graduatedYear): ?array
    {
        $totalLulusan = DB::connection('oltp')
            ->table('alumni_profiles')
            ->where('program_id', $programId)
            ->where('graduation_year', $graduatedYear)
            ->count();

        if ($totalLulusan === 0) {
            return null;
        }

        $d            = self::MARGIN_ERROR;
        $minResponden = $totalLulusan / ($totalLulusan * ($d ** 2) + 1);
        $percentage   = round(($minResponden / $totalLulusan) * 100, 2);

        DB::connection('oltp')->table('tracer_response_thresholds')->updateOrInsert(
            ['program_id' => $programId, 'graduated_year' => $graduatedYear],
            [
                'total_lulusan'   => $totalLulusan,
                'margin_error'    => $d,
                'min_responden'   => (int) round($minResponden),
                'threshold_value' => $percentage,
                'calculated_at'   => now(),
            ]
        );

        // Nilai ini dibaca lewat cache di ThresholdService/LamService (tag 'thresholds'/'lams') —
        // tanpa ini, hasil recalculate baru akan kelihatan setelah TTL cache lama habis.
        $this->forgetTag('thresholds', 'lams');

        return compact('totalLulusan', 'percentage');
    }

    /**
     * Hitung threshold seluruh tahun lulus untuk prodi tertentu — dipanggil saat
     * prodi didaftarkan ke LAM, supaya garis threshold langsung tersedia tanpa
     * menunggu jadwal harian tracer:recalc-response-threshold.
     */
    public function recalculateForPrograms(array $programIds): int
    {
        if ($programIds === []) {
            return 0;
        }

        $combos = DB::connection('oltp')
            ->table('alumni_profiles')
            ->whereIn('program_id', $programIds)
            ->select('program_id', 'graduation_year')
            ->distinct()
            ->get();

        foreach ($combos as $combo) {
            $this->recalculate((int) $combo->program_id, (int) $combo->graduation_year);
        }

        return $combos->count();
    }

    /**
     * Scan kombinasi program+tahun yang punya data lulusan.
     * Dibatasi ke prodi yang terdaftar di lam_programs saja — prodi tanpa LAM
     * tidak butuh threshold akreditasi, jadi tidak perlu dihitung/disimpan.
     */
    public function recalculateAllStale(): int
    {
        $combos = DB::connection('oltp')
            ->table('alumni_profiles as ap')
            ->join('lam_programs as lp', 'lp.program_id', '=', 'ap.program_id')
            ->select('ap.program_id', 'ap.graduation_year')
            ->distinct()
            ->get();

        $count = 0;
        foreach ($combos as $combo) {
            $existing = DB::connection('oltp')->table('tracer_response_thresholds')
                ->where('program_id', $combo->program_id)
                ->where('graduated_year', $combo->graduation_year)
                ->first();

            $currentN = DB::connection('oltp')
                ->table('alumni_profiles')
                ->where('program_id', $combo->program_id)
                ->where('graduation_year', $combo->graduation_year)
                ->count();

            if ($existing && (int) $existing->total_lulusan === $currentN) {
                continue; // tidak berubah, skip
            }

            $this->recalculate($combo->program_id, $combo->graduation_year);
            $count++;
        }

        return $count;
    }
}