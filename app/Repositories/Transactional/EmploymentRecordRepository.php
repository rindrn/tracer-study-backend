<?php
// app/Repositories/Transactional/EmploymentRecordRepository.php
namespace App\Repositories\Transactional;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * EmploymentRecordRepository — aggregate root EmploymentRecord.
 * Menyimpan data pekerjaan alumni (pekerja tetap / wiraswasta).
 */
class EmploymentRecordRepository
{
    private const CONN = 'oltp';

    public function deleteByAlumniId(int $alumniId): void
    {
        DB::connection(self::CONN)->table('employment_records')
            ->where('alumni_id', $alumniId)
            ->delete();
    }

    public function create(array $data): void
    {
        $now = Carbon::now();
        DB::connection(self::CONN)->table('employment_records')->insert(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }
}
