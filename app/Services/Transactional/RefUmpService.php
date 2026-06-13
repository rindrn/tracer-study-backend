<?php
// app/Services/Transactional/RefUmpService.php
//
// Orchestrator utama untuk semua operasi UMP:
//   - List data per tahun
//   - Preview fetch BPS
//   - Preview import Excel
//   - Bulk save (setelah preview)
//   - Edit manual satu baris
//   - Generate template Excel

namespace App\Services\Transactional;

use App\DTOs\Transactional\UmpRowDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\RefUmpRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class RefUmpService
{
    public function __construct(
        private readonly RefUmpRepository $repo,
        private readonly UmpBpsService    $bpsService,
        private readonly UmpImportService $importService,
    ) {}

    // ── READ ──────────────────────────────────────────────────

    /** Tahun yang sudah punya data UMP */
    public function availableYears(): array
    {
        return $this->repo->availableYears();
    }

    /**
     * Data UMP untuk satu tahun.
     * Kalau tahun belum ada di DB → return 34 baris kosong (provinsi dari master).
     *
     * @return array{
     *     tahun: int,
     *     sudah_tersimpan: bool,
     *     rows: array
     * }
     */
    public function getByTahun(int $tahun): array
    {
        $saved = $this->repo->byTahun($tahun);

        if ($saved->isEmpty()) {
            // Tahun baru — kembalikan baris kosong dari master provinsi
            $rows = $this->repo->allProvinces()->map(
                fn($p) => UmpRowDTO::preview(
                    tahun:        $tahun,
                    idProvinsi:   $p->id,
                    namaProvinsi: $p->name,
                    nilaiUmp:     null,
                    sumber:       'KOSONG',
                )->toArray()
            );

            return [
                'tahun'          => $tahun,
                'sudah_tersimpan'=> false,
                'rows'           => $rows->values()->toArray(),
            ];
        }

        return [
            'tahun'          => $tahun,
            'sudah_tersimpan'=> true,
            'rows'           => $saved->map(
                fn($r) => UmpRowDTO::fromModel($r)->toArray()
            )->values()->toArray(),
        ];
    }

    // ── FETCH BPS ─────────────────────────────────────────────

    /**
     * Fetch UMP dari BPS dan return preview (belum simpan).
     * FE menampilkan preview → admin review → kirim ke bulkSave.
     *
     * @return array{
     *     tahun: int,
     *     ok_count: int,
     *     fail_count: int,
     *     rows: array
     * }
     */
    public function previewBps(int $tahun): array
    {
        $rows = $this->bpsService->previewByTahun($tahun);

        return [
            'tahun'      => $tahun,
            'ok_count'   => $rows->where('sumber', 'BPS_API')->count(),
            'fail_count' => $rows->where('sumber', 'GAGAL')->count(),
            'rows'       => $rows->map->toArray()->values()->toArray(),
        ];
    }

    // ── IMPORT EXCEL ──────────────────────────────────────────

    /**
     * Parse file Excel/CSV dan return preview (belum simpan).
     *
     * @return array{
     *     tahun: int|null,
     *     ok_count: int,
     *     unrecognized: string[],
     *     rows: array
     * }
     */
    public function previewImport(UploadedFile $file): array
    {
        $result = $this->importService->parseFile($file);

        return [
            'tahun'        => $result['tahun'],
            'ok_count'     => $result['rows']->count(),
            'unrecognized' => $result['unrecognized'],
            'rows'         => $result['rows']->map->toArray()->values()->toArray(),
        ];
    }

    // ── BULK SAVE ─────────────────────────────────────────────

    /**
     * Simpan bulk rows ke DB (setelah admin review preview).
     * Baris dengan nilai_ump = null → dilewati (tidak disimpan).
     *
     * @param  int    $tahun
     * @param  array  $rows  — array of { id_provinsi, nilai_ump, sumber }
     * @return array{
     *     tahun: int,
     *     saved_count: int,
     *     skipped_count: int
     * }
     */
    public function bulkSave(int $tahun, array $rows): array
    {
        // Validasi tahun masuk akal
        if ($tahun < 2000 || $tahun > 2100) {
            throw new BusinessException("Tahun tidak valid: {$tahun}.", 422);
        }

        // Lookup provinsi untuk validasi id_provinsi dan ambil nama_provinsi
        $provinces = $this->repo->allProvinces()->keyBy('id');

        $prepared = [];
        $skipped  = 0;

        foreach ($rows as $row) {
            $idProvinsi = (int) ($row['id_provinsi'] ?? 0);
            $nilaiUmp   = isset($row['nilai_ump']) && $row['nilai_ump'] !== null && $row['nilai_ump'] !== ''
                ? (int) $row['nilai_ump']
                : null;

            // Skip kalau nilai kosong
            if ($nilaiUmp === null) {
                $skipped++;
                continue;
            }

            // Validasi id_provinsi ada di master
            $province = $provinces->get($idProvinsi);
            if (! $province) {
                throw new BusinessException("id_provinsi={$idProvinsi} tidak ditemukan di master provinces.", 422);
            }

            // Validasi nilai masuk akal (min 500rb, max 50jt)
            if ($nilaiUmp < 500_000 || $nilaiUmp > 50_000_000) {
                throw new BusinessException(
                    "Nilai UMP untuk {$province->name} tidak masuk akal: Rp " . number_format($nilaiUmp),
                    422
                );
            }

            $prepared[] = [
                'tahun'         => $tahun,
                'id_provinsi'   => $idProvinsi,
                'nama_provinsi' => $province->name,  // selalu dari master
                'nilai_ump'     => $nilaiUmp,
                'sumber'        => $row['sumber'] ?? 'MANUAL',
            ];
        }

        $savedCount = $this->repo->bulkUpsert($prepared);

        return [
            'tahun'         => $tahun,
            'saved_count'   => $savedCount,
            'skipped_count' => $skipped,
        ];
    }

    // ── EDIT MANUAL ───────────────────────────────────────────

    /**
     * Edit satu baris UMP (inline edit dari tabel FE).
     * Kalau baris belum ada (tahun baru, provinsi belum pernah diisi)
     * → insert baru via upsert.
     */
    public function updateSingle(int $tahun, int $idProvinsi, int $nilaiUmp): UmpRowDTO
    {
        if ($nilaiUmp < 500_000 || $nilaiUmp > 50_000_000) {
            throw new BusinessException("Nilai UMP tidak masuk akal: Rp " . number_format($nilaiUmp), 422);
        }

        $provinces = $this->repo->allProvinces()->keyBy('id');
        $province  = $provinces->get($idProvinsi);

        if (! $province) {
            throw new BusinessException("id_provinsi={$idProvinsi} tidak ditemukan.", 422);
        }

        $saved = $this->repo->upsert(
            tahun:        $tahun,
            idProvinsi:   $idProvinsi,
            namaProvinsi: $province->name,
            nilaiUmp:     $nilaiUmp,
            sumber:       'MANUAL',
        );

        return UmpRowDTO::fromModel($saved);
    }

    // ── TEMPLATE ──────────────────────────────────────────────

    /**
     * Generate isi template CSV — 34 nama provinsi, kolom tahun & nilai_ump kosong.
     * Controller yang handle download response-nya.
     */
    public function generateTemplateCsv(): string
    {
        $provinces = $this->repo->allProvinces();
        $lines     = ["tahun,nama_provinsi,nilai_ump"];

        foreach ($provinces as $p) {
            $lines[] = "," . $p->name . ",";
        }

        return implode("\n", $lines);
    }
}