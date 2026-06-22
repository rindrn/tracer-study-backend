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
use App\Traits\WithCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class RefUmpService
{
    use WithCache;

    private const TTL = 1800; // 30 menit

    public function __construct(
        private readonly RefUmpRepository $repo,
        private readonly UmpBpsService    $bpsService,
        private readonly UmpImportService $importService,
    ) {}

    // ── READ ──────────────────────────────────────────────────

    /** Tahun yang sudah punya data UMP */
    public function availableYears(): array
    {
        return $this->remember('ump:years', function () {
            return $this->repo->availableYears();
        }, self::TTL);
    }

    public function getByTahun(int $tahun): array
    {
        return $this->remember("ump:tahun:{$tahun}", function () use ($tahun) {
            $saved = $this->repo->byTahun($tahun);

            if ($saved->isEmpty()) {
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
                    'tahun'           => $tahun,
                    'sudah_tersimpan' => false,
                    'rows'            => $rows->values()->toArray(),
                ];
            }

            return [
                'tahun'           => $tahun,
                'sudah_tersimpan' => true,
                'rows'            => $saved->map(
                    fn($r) => UmpRowDTO::fromModel($r)->toArray()
                )->values()->toArray(),
            ];
        }, self::TTL);
    }

    // ── FETCH BPS ─────────────────────────────────────────────
    // Preview tidak di-cache — hasilnya fresh dari BPS setiap kali dipanggil

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
    // Preview import juga tidak di-cache — file beda setiap upload

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
        $prepared  = [];
        $skipped   = 0;

        foreach ($rows as $row) {
            $idProvinsi = (int) ($row['id_provinsi'] ?? 0);
            $nilaiUmp   = isset($row['nilai_ump']) && $row['nilai_ump'] !== null && $row['nilai_ump'] !== ''
                ? (int) $row['nilai_ump']
                : null;

            if ($nilaiUmp === null) { $skipped++; continue; }

            // Validasi id_provinsi ada di master
            $province = $provinces->get($idProvinsi);
            if (! $province) {
                throw new BusinessException("id_provinsi={$idProvinsi} tidak ditemukan di master provinces.", 422);
            }

            // Validasi nilai masuk akal (min 500rb, max 50jt)
            if ($nilaiUmp < 500_000 || $nilaiUmp > 50_000_000) {
                throw new BusinessException(
                    "Nilai UMP untuk {$province->name} tidak masuk akal: Rp " . number_format($nilaiUmp), 422
                );
            }

            $prepared[] = [
                'tahun'         => $tahun,
                'id_provinsi'   => $idProvinsi,
                'nama_provinsi' => $province->name,
                'nilai_ump'     => $nilaiUmp,
                'sumber'        => $row['sumber'] ?? 'MANUAL',
            ];
        }

        $savedCount = $this->repo->bulkUpsert($prepared);

        // Bust cache tahun ini dan daftar tahun
        $this->forget("ump:tahun:{$tahun}", 'ump:years');

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

        // Bust cache data tahun ini
        $this->forget("ump:tahun:{$tahun}");

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