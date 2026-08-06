<?php

namespace App\Repositories\Transactional;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
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

    /**
     * Kueri dasar beserta seluruh penyaringnya.
     *
     * Dipakai bersama oleh tampilan tabel dan unduhan. Sebelumnya keduanya
     * menyusun kueri sendiri-sendiri dan getAll() melewatkan penyaring
     * pencarian, sehingga berkas yang terunduh tidak sama dengan yang sedang
     * dilihat Tim Tracer.
     */
    private function baseQuery(array $filters): Builder
    {
        $query = DB::connection(self::CONN)->table('stakeholder_contacts as sc')
            ->join('alumni_profiles as ap', 'sc.alumni_id', '=', 'ap.id')
            // Prodi dipakai menyaring dan mengelompokkan kiriman surel —
            // Tim Tracer mengirim per prodi, bukan sekaligus seinstitusi.
            ->leftJoin('programs as p', 'ap.program_id', '=', 'p.id');

        if (!empty($filters['graduation_year'])) {
            $query->where('ap.graduation_year', $filters['graduation_year']);
        }
        if (!empty($filters['alumni_status'])) {
            $query->where('sc.alumni_status', $filters['alumni_status']);
        }
        if (!empty($filters['contact_type'])) {
            $query->where('sc.contact_type', $filters['contact_type']);
        }
        if (!empty($filters['program_code'])) {
            $query->where('p.code', $filters['program_code']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('sc.contact_name', 'ilike', "%{$s}%")
                ->orWhere('sc.contact_email', 'ilike', "%{$s}%")
                ->orWhere('ap.nim', 'ilike', "%{$s}%")
                ->orWhere('ap.name', 'ilike', "%{$s}%"));
        }

        return $query;
    }

    public function paginate(array $filters, int $perPage = 100): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->select(
                'sc.*',
                'ap.nim',
                'ap.name as alumni_name',
                'ap.graduation_year',
                'p.code as program_code',
                'p.name as program_name',
            )
            ->orderByDesc('sc.created_at')
            ->paginate($perPage);
    }

    public function getAll(array $filters): \Illuminate\Support\Collection
    {
        return $this->baseQuery($filters)
            ->select(
                'sc.contact_type',
                'sc.contact_name',
                'sc.contact_email',
                'sc.alumni_status',
                'ap.nim',
                'ap.name as alumni_name',
                'ap.graduation_year',
                'p.code as program_code',
                'p.name as program_name',
            )
            ->orderBy('ap.nim')
            ->get();
    }

    /**
     * Angka ringkas untuk kartu di kepala halaman.
     *
     * "Email unik" sengaja dihitung terpisah dari total kontak: selisih
     * keduanya adalah jumlah penilai yang disebut lebih dari satu alumni —
     * persis orang-orang yang akan menerima surel berkali-kali kalau daftar
     * kontak dipakai mentah-mentah untuk email blast.
     */
    public function stats(array $filters): array
    {
        $row = $this->baseQuery($filters)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT LOWER(sc.contact_email)) as unique_emails')
            ->selectRaw('COUNT(DISTINCT sc.alumni_id) as alumni_count')
            ->first();

        return [
            'total'         => (int) ($row->total ?? 0),
            'unique_emails' => (int) ($row->unique_emails ?? 0),
            'alumni_count'  => (int) ($row->alumni_count ?? 0),
        ];
    }
}
