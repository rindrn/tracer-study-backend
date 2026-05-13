<?php
// app/Repositories/Transactional/EducationRecordRepository.php
namespace App\Repositories\Transactional;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * EducationRecordRepository — aggregate root EducationRecord.
 * Menyimpan data studi lanjut alumni.
 */
class EducationRecordRepository
{
    private const CONN = 'oltp';

    public function deleteByAlumniId(int $alumniId): void
    {
        DB::connection(self::CONN)->table('education_records')
            ->where('alumni_id', $alumniId)
            ->delete();
    }

    public function create(array $data): void
    {
        $now = Carbon::now();
        DB::connection(self::CONN)->table('education_records')->insert(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }
}
