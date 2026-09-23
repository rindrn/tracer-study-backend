<?php

namespace App\Services\Analytical;

use App\Exceptions\BusinessException;
use App\Repositories\Analytical\ExplorerRepository;
use App\Traits\WithCache;

/**
 * ExplorerService
 *
 * Otak halaman Insight (OLAP Explorer):
 *   - catalog()          → apa yang boleh dipilih pengguna (config/olap_catalog.php)
 *   - dimensionValues()  → isi dropdown filter satu dimensi
 *   - query()            → hasil kombinasi measure × dimensi yang disusun pengguna
 *
 * Semua permintaan divalidasi terhadap katalog SEBELUM menyentuh Cube.js.
 * Katalog adalah whitelist: nama member Cube.js yang tidak tercantum ditolak
 * 422, sehingga pengguna tidak bisa meminta NIM, nama alumni, atau cube lain
 * hanya dengan mengubah isi request.
 *
 * Pivot, subtotal, dan chart dikerjakan di FE (src/lib/olapExplorer.ts); di
 * sini hasilnya dikembalikan sebagai baris datar ber-key nama member Cube.js.
 */
class ExplorerService
{
    use WithCache;

    private const TTL = 3600;

    /** Scope role yang ikut menentukan hasil — dan karena itu ikut jadi cache key. */
    private const SCOPE_KEYS = ['nama_prodi', 'jenjang', 'jurusan', 'id_prodi_in'];

    public function __construct(
        private readonly ExplorerRepository $repo,
    ) {}

    // ── KATALOG ───────────────────────────────────────────────────

    public function catalog(): array
    {
        $cubes = [];

        foreach ($this->cubes() as $key => $cube) {
            $cubes[] = [
                'key'         => $key,
                'label'       => $cube['label'],
                'description' => $cube['description'] ?? '',
                'measures'    => array_map(
                    fn (string $m) => $this->measureMeta($cube, $m),
                    array_keys($cube['measures']),
                ),
                'dimension_groups' => array_map(
                    fn (string $group, array $members) => [
                        'group'   => $group,
                        'members' => array_map(
                            fn (string $k, string $label) => ['key' => $k, 'label' => $label],
                            array_keys($members),
                            $members,
                        ),
                    ],
                    array_keys($cube['dimension_groups']),
                    $cube['dimension_groups'],
                ),
            ];
        }

        return ['cubes' => $cubes, 'limits' => $this->limits()];
    }

    // ── NILAI DIMENSI ─────────────────────────────────────────────

    /** @return array{dimension: string, values: string[]} */
    public function dimensionValues(string $dimension, array $scope): array
    {
        $cubeKey = $this->cubeOwningDimension($dimension);

        if ($cubeKey === null) {
            throw new BusinessException("Dimensi \"{$dimension}\" tidak tersedia untuk dianalisis.", 422);
        }

        $cube  = $this->cubes()[$cubeKey];
        $scope = $this->withSnapshot($this->onlyScope($scope), $cube);

        $values = $this->remember(
            $this->key('explorer:values', ['dimension' => $dimension, 'scope' => $scope]),
            fn () => $this->repo->distinctValues($cube['base_measure'], $dimension, $scope),
            self::TTL,
            ['analytics-dashboard'],
        );

        return ['dimension' => $dimension, 'values' => $values];
    }

    // ── QUERY ─────────────────────────────────────────────────────

    /**
     * @param array{cube: string, measures: string[], dimensions?: string[], filters?: array} $body
     */
    public function query(array $body, array $scope): array
    {
        $cubeKey    = $body['cube'];
        $measures   = array_values($body['measures']);
        $dimensions = array_values($body['dimensions'] ?? []);
        $filters    = array_values(array_filter(
            $body['filters'] ?? [],
            fn (array $f) => !empty($f['values']),
        ));

        $cube = $this->validate($cubeKey, $measures, $dimensions, $filters);

        $scope   = $this->withSnapshot($this->onlyScope($scope), $cube);
        $maxRows = $this->limits()['max_rows'];

        $rows = $this->remember(
            $this->key('explorer:query', compact('cubeKey', 'measures', 'dimensions', 'filters', 'scope')),
            // +1 baris hanya untuk tahu apakah hasilnya terpotong.
            fn () => $this->repo->query($measures, $dimensions, $filters, $scope, $maxRows + 1)->all(),
            self::TTL,
            ['analytics-dashboard'],
        );

        $truncated = count($rows) > $maxRows;
        $rows      = array_slice($rows, 0, $maxRows);

        return [
            'cube'       => $cubeKey,
            'measures'   => array_map(fn ($m) => $this->measureMeta($cube, $m), $measures),
            'dimensions' => array_map(fn ($d) => ['key' => $d, 'label' => $this->dimensionLabel($cube, $d)], $dimensions),
            'rows'       => $rows,
            'row_count'  => count($rows),
            'truncated'  => $truncated,
        ];
    }

    /**
     * Validasi susunan pertanyaan berbentuk FE ({ cube, measures, rowDims,
     * colDim, filters }) — dipakai saat menyimpan pertanyaan, supaya yang
     * tersimpan pasti bisa dijalankan kembali.
     */
    public function assertValidInput(array $input): void
    {
        $dimensions = array_values(array_filter([
            ...($input['rowDims'] ?? []),
            $input['colDim'] ?? null,
        ]));

        $filters = array_values(array_filter(
            $input['filters'] ?? [],
            fn ($f) => is_array($f) && !empty($f['values']),
        ));

        $this->validate(
            (string) ($input['cube'] ?? ''),
            array_values($input['measures'] ?? []),
            $dimensions,
            $filters,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE
    // ──────────────────────────────────────────────────────────────

    /** Tolak apa pun di luar katalog. Pesannya dibaca pengguna non-IT, jadi pakai label. */
    private function validate(string $cubeKey, array $measures, array $dimensions, array $filters): array
    {
        $cube = $this->cubes()[$cubeKey] ?? null;
        if ($cube === null) {
            throw new BusinessException('Sumber data yang dipilih tidak tersedia.', 422);
        }

        $limits = $this->limits();

        if ($measures === []) {
            throw new BusinessException('Pilih minimal satu ukuran untuk ditampilkan.', 422);
        }
        if (count($measures) > $limits['max_measures']) {
            throw new BusinessException("Maksimal {$limits['max_measures']} ukuran sekaligus.", 422);
        }
        if (count($dimensions) > $limits['max_dimensions']) {
            throw new BusinessException("Maksimal {$limits['max_dimensions']} dimensi sekaligus.", 422);
        }
        if (count($dimensions) !== count(array_unique($dimensions))) {
            throw new BusinessException('Dimensi yang sama tidak boleh dipilih dua kali.', 422);
        }
        if (count($measures) !== count(array_unique($measures))) {
            throw new BusinessException('Ukuran yang sama tidak boleh dipilih dua kali.', 422);
        }

        foreach ($measures as $m) {
            if (!isset($cube['measures'][$m])) {
                throw new BusinessException("Ukuran \"{$m}\" tidak tersedia pada sumber data ini.", 422);
            }
        }

        foreach ($dimensions as $d) {
            if ($this->dimensionLabel($cube, $d) === null) {
                throw new BusinessException("Dimensi \"{$d}\" tidak tersedia pada sumber data ini.", 422);
            }
        }

        foreach ($filters as $f) {
            if ($this->dimensionLabel($cube, $f['member']) === null) {
                throw new BusinessException("Saringan \"{$f['member']}\" tidak tersedia pada sumber data ini.", 422);
            }
        }

        return $cube;
    }

    /**
     * Pasang snapshot terbaru sebagai minggu_snapshot — nilai itu dibaca
     * buildGlobalFilters() sebagai DimWaktu.id_waktu. Lihat docblock
     * ExplorerRepository soal kenapa ini wajib.
     */
    private function withSnapshot(array $scope, array $cube): array
    {
        $snapshot = $this->remember(
            'explorer:latest-snapshot:' . md5($cube['base_measure']),
            fn () => $this->repo->latestSnapshotId($cube['base_measure']),
            300,
            ['analytics-dashboard'],
        );

        if ($snapshot === null) {
            throw new BusinessException('Belum ada data tracer study yang selesai diproses.', 404);
        }

        $scope['minggu_snapshot'] = $snapshot;

        return $scope;
    }

    private function onlyScope(array $params): array
    {
        return array_filter(
            array_intersect_key($params, array_flip(self::SCOPE_KEYS)),
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }

    private function cubeOwningDimension(string $dimension): ?string
    {
        foreach ($this->cubes() as $key => $cube) {
            if ($this->dimensionLabel($cube, $dimension) !== null) {
                return $key;
            }
        }

        return null;
    }

    private function dimensionLabel(array $cube, string $dimension): ?string
    {
        foreach ($cube['dimension_groups'] as $members) {
            if (isset($members[$dimension])) {
                return $members[$dimension];
            }
        }

        return null;
    }

    private function measureMeta(array $cube, string $measure): array
    {
        $m = $cube['measures'][$measure];

        return [
            'key'         => $measure,
            'label'       => $m['label'],
            'format'      => $m['format'],
            'description' => $m['description'] ?? '',
        ];
    }

    private function cubes(): array
    {
        return config('olap_catalog.cubes', []);
    }

    private function limits(): array
    {
        return config('olap_catalog.limits');
    }

    private function key(string $prefix, array $params): string
    {
        return $prefix . ':' . md5(json_encode($this->ksortRecursive($params)));
    }

    private function ksortRecursive(array $a): array
    {
        foreach ($a as &$v) {
            if (is_array($v)) {
                $v = $this->ksortRecursive($v);
            }
        }
        if (!array_is_list($a)) {
            ksort($a);
        }

        return $a;
    }
}
