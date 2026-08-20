<?php

namespace Database\Seeders;

use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\Transactional\OrgUnitService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrgUnitBackfillSeeder — DFR-24: konversi data POLBAN existing
 * (`programs.jurusan`, daftar induk `jurusans`) menjadi `org_units` di
 * bawah template Politeknik ("Jurusan", level 1), TANPA mengubah
 * `programs.jurusan` sama sekali (strategi expand-contract).
 *
 * WAJIB baca dulu: `jurusans` tidak ber-FK ke `programs` (lihat migration
 * create_jurusans_table), dan keduanya pernah ditulis lewat jalur terpisah
 * sebelum JurusanService konsisten dipakai -- ada risiko nyata isinya
 * berbeda (typo/spasi). Backfill ini SENGAJA memakai `jurusans.name` (daftar
 * induk terkurasi) sebagai sumber kebenaran, BUKAN distinct programs.jurusan
 * langsung -- supaya typo yang cuma nyasar ke satu baris program tidak ikut
 * melahirkan org_unit duplikat. Nilai programs.jurusan yang tidak match ke
 * jurusans manapun dilaporkan sebagai "prodi yatim" (orphaned), tidak
 * di-backfill diam-diam.
 *
 * Idempoten: hanya menambah nama yang belum jadi org_units, aman dijalankan
 * berulang (mis. tiap kali ada jurusan baru ditambahkan sebelum Fase 2
 * menyediakan CRUD sungguhan lewat OrgUnitService/DFR-07).
 */
class OrgUnitBackfillSeeder extends Seeder
{
    private const CONN             = 'oltp';
    private const INSTITUTION_TYPE = 'politeknik';
    private const LEVEL_INDEX      = 1; // "Jurusan"

    public function run(): void
    {
        $report = $this->execute();

        $this->command?->info(
            "OrgUnitBackfillSeeder: {$report['created']} org_units dibuat, "
            . "{$report['already_existed']} sudah ada sebelumnya."
        );

        if ($report['mismatches'] !== []) {
            $this->command?->warn(
                'OrgUnitBackfillSeeder: ditemukan ' . count($report['mismatches'])
                . ' ketidakcocokan data quality antara programs.jurusan dan jurusans.name -- lihat log.'
            );
        }
    }

    /**
     * Jalankan backfill dan kembalikan laporan terstruktur, supaya bisa
     * diuji tanpa mem-parsing output console (dipakai
     * OrgUnitBackfillSeederTest).
     *
     * @return array{
     *   created: int,
     *   already_existed: int,
     *   orphaned_programs: string[],
     *   unused_jurusans: string[],
     *   mismatches: string[],
     *   created_names: string[],
     * }
     */
    public function execute(): array
    {
        $jurusanNames = DB::connection(self::CONN)->table('jurusans')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $programJurusans = DB::connection(self::CONN)->table('programs')
            ->whereNotNull('jurusan')
            ->where('jurusan', '<>', '')
            ->distinct()
            ->orderBy('jurusan')
            ->pluck('jurusan')
            ->all();

        // --- Langkah 1: validasi data quality (WAJIB sebelum backfill) ---
        $orphanedPrograms = array_values(array_diff($programJurusans, $jurusanNames));
        $unusedJurusans   = array_values(array_diff($jurusanNames, $programJurusans));
        $mismatches       = array_values(array_unique(array_merge($orphanedPrograms, $unusedJurusans)));

        if ($mismatches !== []) {
            Log::warning('OrgUnitBackfillSeeder: mismatch programs.jurusan vs jurusans.name', [
                'orphaned_programs_jurusan' => $orphanedPrograms,
                'jurusans_without_program'  => $unusedJurusans,
            ]);
        }

        // --- Langkah 2: pastikan org_unit_types "Jurusan" (Politeknik) ada ---
        $orgUnitTypeRepo = app(OrgUnitTypeRepository::class);
        $type = $orgUnitTypeRepo->findByInstitutionTypeAndLevel(self::INSTITUTION_TYPE, self::LEVEL_INDEX);

        if ($type === null) {
            throw new \RuntimeException(
                'OrgUnitBackfillSeeder: baris org_unit_types (politeknik, level 1) tidak ditemukan. '
                . 'Jalankan migration create_org_unit_types_table terlebih dulu.'
            );
        }

        // --- Langkah 3: backfill dari jurusans.name (sumber kebenaran) ---
        $orgUnitRepo    = app(OrgUnitRepository::class);
        $orgUnitService = app(OrgUnitService::class);

        $existingOrgUnitNames = $orgUnitRepo->namesByType((int) $type->id);

        $created      = 0;
        $alreadyThere = 0;
        $createdNames = [];

        foreach ($jurusanNames as $name) {
            if (in_array($name, $existingOrgUnitNames, strict: true)) {
                $alreadyThere++;
                continue;
            }

            $orgUnitService->create((int) $type->id, $name, parentId: null);
            $created++;
            $createdNames[] = $name;
        }

        return [
            'created'           => $created,
            'already_existed'   => $alreadyThere,
            'orphaned_programs' => $orphanedPrograms,
            'unused_jurusans'   => $unusedJurusans,
            'mismatches'        => $mismatches,
            'created_names'     => $createdNames,
        ];
    }
}
