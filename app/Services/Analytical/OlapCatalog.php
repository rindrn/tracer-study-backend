<?php

namespace App\Services\Analytical;

use App\Exceptions\BusinessException;

/**
 * OlapCatalog
 *
 * Pembaca + penjaga config/olap_catalog.php.
 *
 * Seluruh input pengguna di OLAP Explorer melewati kelas ini sebelum menyentuh
 * Cube.js. Yang dijaga bukan cuma "nama member-nya ada atau tidak", tapi juga
 * apakah kombinasinya masuk akal secara dimensional:
 *
 *   - measure harus milik cube yang dipilih (tidak boleh measure FactMultiSelect
 *     dipasang pada query FactTracerStudy),
 *   - dimensi harus berasal dari dimension cube yang PUNYA JOIN ke cube itu.
 *
 * Aturan kedua yang mencegah fan-out lintas fact: tanpa itu pengguna bisa
 * meminta avg_skor (grain: per alumni per indikator) dipecah oleh dimensi yang
 * hanya ter-join ke fact lain, dan angkanya jadi salah tanpa galat apa pun.
 *
 * Kelas ini murni — tidak menyentuh database, HTTP, maupun session — sehingga
 * bisa diuji tanpa infrastruktur apa pun.
 */
class OlapCatalog
{
    /** @var array<string, mixed> */
    private array $catalog;

    public function __construct(?array $catalog = null)
    {
        $this->catalog = $catalog ?? config('olap_catalog');
    }

    // ──────────────────────────────────────────────────────────────
    //  PEMBACAAN
    // ──────────────────────────────────────────────────────────────

    /**
     * Katalog dalam bentuk siap kirim ke frontend.
     *
     * Dimensi sengaja dikirim SUDAH TERSUSUN PER CUBE (bukan sebagai daftar
     * global + peta join terpisah). FE tinggal menampilkan apa yang diterima
     * begitu pengguna memilih sumber data, tanpa perlu tahu aturan join apa
     * pun — dan tidak ada kesempatan FE salah menyusunnya sendiri.
     *
     * @return array<string, mixed>
     */
    public function forFrontend(): array
    {
        $cubes = [];

        foreach ($this->catalog['cubes'] as $cubeName => $cube) {
            $dimensionGroups = [];

            foreach ($cube['dimensions'] as $dimCube) {
                $def = $this->catalog['dimensions'][$dimCube];

                $dimensionGroups[] = [
                    'group'   => $def['group'],
                    'members' => collect($def['members'])
                        ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                        ->values()
                        ->all(),
                ];
            }

            $cubes[] = [
                'key'         => $cubeName,
                'label'       => $cube['label'],
                'description' => $cube['description'],
                'measures'    => collect($cube['measures'])
                    ->map(fn ($m, $key) => [
                        'key'    => $key,
                        'label'  => $m['label'],
                        'format' => $m['format'],
                    ])
                    ->values()
                    ->all(),
                'dimension_groups' => $dimensionGroups,
            ];
        }

        return [
            'cubes'  => $cubes,
            'limits' => $this->catalog['limits'],
        ];
    }

    public function limits(): array
    {
        return $this->catalog['limits'];
    }

    /**
     * Semua dimensi yang boleh dipakai sebagai filter, lintas cube.
     * Dipakai endpoint dimension-values, yang tidak terikat satu cube.
     *
     * @return array<string>
     */
    public function allDimensionKeys(): array
    {
        $keys = [];

        foreach ($this->catalog['dimensions'] as $def) {
            foreach (array_keys($def['members']) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    // ──────────────────────────────────────────────────────────────
    //  VALIDASI
    // ──────────────────────────────────────────────────────────────

    /**
     * Pastikan seluruh isi permintaan explorer sah.
     *
     * @param  array{cube: string, measures: array<string>, dimensions: array<string>, filters: array<array{member: string, values: array}>}  $request
     * @throws BusinessException 422 dengan pesan yang bisa langsung ditampilkan
     */
    public function assertValidRequest(array $request): void
    {
        $cube = $request['cube'];

        if (! isset($this->catalog['cubes'][$cube])) {
            throw new BusinessException("Sumber data '{$cube}' tidak dikenal.", 422);
        }

        $limits = $this->catalog['limits'];

        $measures   = $request['measures']   ?? [];
        $dimensions = $request['dimensions'] ?? [];
        $filters    = $request['filters']    ?? [];

        if (count($measures) < 1) {
            throw new BusinessException('Pilih minimal satu measure.', 422);
        }

        if (count($measures) > $limits['max_measures']) {
            throw new BusinessException(
                "Maksimal {$limits['max_measures']} measure per query.", 422
            );
        }

        if (count($dimensions) > $limits['max_dimensions']) {
            throw new BusinessException(
                "Maksimal {$limits['max_dimensions']} dimensi per query.", 422
            );
        }

        if (count($dimensions) !== count(array_unique($dimensions))) {
            throw new BusinessException('Ada dimensi yang dipilih lebih dari sekali.', 422);
        }

        foreach ($measures as $measure) {
            $this->assertMeasureBelongsTo($cube, $measure);
        }

        foreach ($dimensions as $dimension) {
            $this->assertDimensionJoinable($cube, $dimension);
        }

        // Filter tunduk pada aturan join yang SAMA dengan dimensi. Menyaring
        // lewat dimensi yang tidak ter-join ke fact terpilih sama saja
        // memaksakan join yang tidak ada — Cube.js akan menolaknya, atau
        // memakai jalur join tak terduga. Lebih baik ditolak di sini dengan
        // pesan yang jelas.
        foreach ($filters as $filter) {
            $this->assertDimensionJoinable($cube, $filter['member']);
        }
    }

    public function assertMeasureBelongsTo(string $cube, string $measure): void
    {
        if (! isset($this->catalog['cubes'][$cube]['measures'][$measure])) {
            throw new BusinessException(
                "Measure '{$measure}' tidak tersedia pada sumber data '{$cube}'.", 422
            );
        }
    }

    public function assertDimensionJoinable(string $cube, string $dimension): void
    {
        $dimCube = $this->dimensionCubeOf($dimension);

        if ($dimCube === null) {
            throw new BusinessException("Dimensi '{$dimension}' tidak dikenal.", 422);
        }

        if (! in_array($dimCube, $this->catalog['cubes'][$cube]['dimensions'], true)) {
            throw new BusinessException(
                "Dimensi '{$dimension}' tidak tersedia pada sumber data '{$cube}'.", 422
            );
        }
    }

    public function assertKnownDimension(string $dimension): void
    {
        if ($this->dimensionCubeOf($dimension) === null) {
            throw new BusinessException("Dimensi '{$dimension}' tidak dikenal.", 422);
        }
    }

    /**
     * Cube pemilik sebuah dimensi, atau null kalau dimensi itu tidak ada di
     * katalog. Sengaja dicari lewat katalog dan bukan dengan memotong teks di
     * titik pertama — member yang tidak terdaftar (mis. DimWaktu.tahun_snapshot)
     * harus tetap dianggap tidak dikenal walaupun formatnya benar.
     */
    public function dimensionCubeOf(string $dimension): ?string
    {
        foreach ($this->catalog['dimensions'] as $dimCube => $def) {
            if (array_key_exists($dimension, $def['members'])) {
                return $dimCube;
            }
        }

        return null;
    }

    /**
     * Label measure/dimensi untuk dipakai di judul kolom hasil.
     */
    public function labelOf(string $cube, string $member): string
    {
        if (isset($this->catalog['cubes'][$cube]['measures'][$member])) {
            return $this->catalog['cubes'][$cube]['measures'][$member]['label'];
        }

        $dimCube = $this->dimensionCubeOf($member);

        return $dimCube !== null
            ? $this->catalog['dimensions'][$dimCube]['members'][$member]
            : $member;
    }

    public function formatOf(string $cube, string $measure): string
    {
        return $this->catalog['cubes'][$cube]['measures'][$measure]['format'] ?? 'decimal';
    }
}
