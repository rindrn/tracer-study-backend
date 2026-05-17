<?php
// database/seeders/LamSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LamSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. LAMs ──────────────────────────────────────────────
        $lams = [
            ['name' => 'LAM INFOKOM', 'code' => 'INFOKOM'],
            ['name' => 'LAM Teknik',  'code' => 'TEKNIK'],
        ];

        foreach ($lams as $data) {
            DB::connection('oltp')->table('lams')->updateOrInsert(
                ['code' => $data['code']],
                [...$data, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // ── 2. LAM Versions ──────────────────────────────────────
        $versions = [
            ['lam_code' => 'INFOKOM', 'year' => 2024, 'version_name' => 'Standar Akreditasi 2024'],
            ['lam_code' => 'INFOKOM', 'year' => 2025, 'version_name' => 'Standar Akreditasi 2025'],
            ['lam_code' => 'TEKNIK',  'year' => 2025, 'version_name' => 'Standar Teknik 2025'],
        ];

        foreach ($versions as $v) {
            $lam = DB::connection('oltp')->table('lams')->where('code', $v['lam_code'])->first();
            if (! $lam) continue;

            DB::connection('oltp')->table('lam_versions')->updateOrInsert(
                ['lam_id' => $lam->id, 'year' => $v['year']],
                [
                    'lam_id'       => $lam->id,
                    'year'         => $v['year'],
                    'version_name' => $v['version_name'],
                    'is_active'    => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }

        // ── 3. LAM <-> Program Mapping ───────────────────────────
        $mappings = [
            ['lam_code' => 'INFOKOM', 'program_codes' => ['TI', 'SI', 'MI']],
            ['lam_code' => 'TEKNIK',  'program_codes' => ['TI', 'TE']],
        ];

        foreach ($mappings as $m) {
            $lam = DB::connection('oltp')->table('lams')->where('code', $m['lam_code'])->first();
            if (! $lam) continue;

            foreach ($m['program_codes'] as $programCode) {
                $program = DB::connection('oltp')->table('programs')->where('code', $programCode)->first();
                if (! $program) continue;

                DB::connection('oltp')->table('lam_programs')->updateOrInsert(
                    ['lam_id' => $lam->id, 'program_id' => $program->id],
                    ['lam_id' => $lam->id, 'program_id' => $program->id, 'created_at' => now()]
                );
            }
        }

        // ── 4. Thresholds ─────────────────────────────────────────
        $thresholds = [
            ['lam_code' => 'INFOKOM', 'year' => 2024, 'name' => 'Tingkat Serapan Kerja',  'value' => 75,      'unit' => '%',    'operator' => '>='],
            ['lam_code' => 'INFOKOM', 'year' => 2024, 'name' => 'Waktu Tunggu Kerja',      'value' => 6,       'unit' => 'bulan','operator' => '<='],
            ['lam_code' => 'INFOKOM', 'year' => 2025, 'name' => 'Tingkat Serapan Kerja',  'value' => 80,      'unit' => '%',    'operator' => '>='],
            ['lam_code' => 'INFOKOM', 'year' => 2025, 'name' => 'Waktu Tunggu Kerja',      'value' => 6,       'unit' => 'bulan','operator' => '<='],
            ['lam_code' => 'TEKNIK',  'year' => 2025, 'name' => 'Tingkat Serapan Kerja',  'value' => 70,      'unit' => '%',    'operator' => '>='],
            ['lam_code' => 'TEKNIK',  'year' => 2025, 'name' => 'Rata-rata Gaji Alumni',  'value' => 6500000, 'unit' => 'IDR',  'operator' => '>='],
        ];

        // Ambil user pertama sebagai created_by
        $createdBy = DB::connection('oltp')->table('users')->value('id') ?? 1;

        foreach ($thresholds as $t) {
            $lam = DB::connection('oltp')->table('lams')->where('code', $t['lam_code'])->first();
            if (! $lam) continue;

            $version = DB::connection('oltp')->table('lam_versions')
                ->where('lam_id', $lam->id)
                ->where('year', $t['year'])
                ->first();
            if (! $version) continue;

            DB::connection('oltp')->table('thresholds')->updateOrInsert(
                ['lam_version_id' => $version->id, 'name' => $t['name']],
                [
                    'lam_version_id' => $version->id,
                    'name'           => $t['name'],
                    'value'          => $t['value'],
                    'unit'           => $t['unit'],
                    'operator'       => $t['operator'],
                    'created_by'     => $createdBy,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]
            );
        }
    }
}