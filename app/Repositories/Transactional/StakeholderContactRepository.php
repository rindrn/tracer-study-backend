<?php

namespace App\Repositories\Transactional;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StakeholderContactRepository
{
    private const CONN = 'oltp';

    public function bulkUpsert(int $alumniId, int $questionnaireId, array $contacts): void
    {
        $conn = DB::connection(self::CONN);
        $conn->table('stakeholder_contacts')
            ->where('alumni_id', $alumniId)
            ->where('questionnaire_id', $questionnaireId)
            ->delete();

        $now = now();
        $rows = array_map(fn ($c) => [
            'alumni_id' => $alumniId,
            'questionnaire_id' => $questionnaireId,
            'contact_type' => $c['contact_type'],
            'contact_name' => $c['contact_name'],
            'contact_email' => $c['contact_email'],
            'alumni_status' => $c['alumni_status'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $contacts);

        if (!empty($rows)) {
            $conn->table('stakeholder_contacts')->insert($rows);
        }
    }

    public function paginate(array $filters, int $perPage = 100): LengthAwarePaginator
    {
        $query = DB::connection(self::CONN)->table('stakeholder_contacts as sc')
            ->join('alumni_profiles as ap', 'sc.alumni_id', '=', 'ap.id')
            ->select('sc.*', 'ap.nim', 'ap.name as alumni_name', 'ap.graduation_year');

        if (!empty($filters['graduation_year'])) {
            $query->where('ap.graduation_year', $filters['graduation_year']);
        }
        if (!empty($filters['alumni_status'])) {
            $query->where('sc.alumni_status', $filters['alumni_status']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q->where('sc.contact_name', 'ilike', "%{$s}%")->orWhere('sc.contact_email', 'ilike', "%{$s}%")->orWhere('ap.nim', 'ilike', "%{$s}%"));
        }

        return $query->orderByDesc('sc.created_at')->paginate($perPage);
    }

    public function getAll(array $filters): \Illuminate\Support\Collection
    {
        $query = DB::connection(self::CONN)->table('stakeholder_contacts as sc')
            ->join('alumni_profiles as ap', 'sc.alumni_id', '=', 'ap.id')
            ->select('sc.contact_type', 'sc.contact_name', 'sc.contact_email', 'sc.alumni_status', 'ap.nim', 'ap.name as alumni_name', 'ap.graduation_year');

        if (!empty($filters['graduation_year'])) {
            $query->where('ap.graduation_year', $filters['graduation_year']);
        }
        if (!empty($filters['alumni_status'])) {
            $query->where('sc.alumni_status', $filters['alumni_status']);
        }

        return $query->orderBy('ap.nim')->get();
    }
}
