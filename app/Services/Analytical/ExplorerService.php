<?php

namespace App\Services\Analytical;

use App\Repositories\Analytical\ExplorerRepository;

/**
 * ExplorerService
 *
 * Orkestrasi OLAP Explorer: validasi katalog -> susun query -> jalankan ->
 * bentuk hasil yang enak dipakai frontend.
 *
 * Urutannya penting. Validasi katalog dijalankan LEBIH DULU, sebelum apa pun
 * menyentuh Cube.js, supaya permintaan yang tidak sah tidak pernah jadi query
 * sama sekali.
 */
class ExplorerService
{
    public function __construct(
        private readonly OlapCatalog $catalog,
        private readonly ExplorerQueryBuilder $builder,
        private readonly ExplorerRepository $repo,
    ) {}

    /**
     * Katalog untuk frontend.
     *
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return $this->catalog->forFrontend();
    }

    /**
     * Nilai unik satu dimensi untuk dropdown filter.
     *
     * @param  array<string, mixed>  $scopedParams
     * @return array<string>
     */
    public function dimensionValues(string $dimension, array $scopedParams): array
    {
        $this->catalog->assertKnownDimension($dimension);

        return $this->repo->dimensionValues(
            $dimension,
            $this->repo->scopeFilters($scopedParams),
        );
    }

    /**
     * Jalankan satu query explorer.
     *
     * @param  array{cube: string, measures: array<string>, dimensions: array<string>, filters: array, limit?: int}  $request
     * @param  array<string, mixed>  $scopedParams  hasil EnforcesProdiScope
     * @return array<string, mixed>
     */
    public function query(array $request, array $scopedParams): array
    {
        $this->catalog->assertValidRequest($request);

        $limits = $this->catalog->limits();

        $cubeQuery = $this->builder->build(
            request:      $request,
            scopeFilters: $this->repo->scopeFilters($scopedParams),
            maxRows:      $limits['max_rows'],
        );

        $rows = $this->repo->run($cubeQuery);

        $cube       = $request['cube'];
        $measures   = array_values($request['measures']);
        $dimensions = array_values($request['dimensions'] ?? []);

        return [
            'cube'       => $cube,
            'measures'   => array_map(fn ($m) => [
                'key'    => $m,
                'label'  => $this->catalog->labelOf($cube, $m),
                'format' => $this->catalog->formatOf($cube, $m),
            ], $measures),
            'dimensions' => array_map(fn ($d) => [
                'key'   => $d,
                'label' => $this->catalog->labelOf($cube, $d),
            ], $dimensions),
            'rows'      => $this->normalizeRows($rows->all(), $measures, $dimensions),
            'row_count' => $rows->count(),

            // Cube.js memotong hasil diam-diam saat mengenai limit. Tanpa
            // penanda ini pengguna melihat pivot yang tampak lengkap padahal
            // sebagian kelompok hilang.
            'truncated' => $rows->count() >= $cubeQuery['limit'],
        ];
    }

    /**
     * Cube.js mengembalikan measure numerik sebagai string (avg dan sum lewat
     * driver Postgres jadi "4.6153846153846154"). Dibiarkan begitu, frontend
     * akan mengurutkan dan menjumlahkannya sebagai teks. Dikonversi di sini,
     * satu kali, bukan di setiap komponen chart.
     *
     * Dimensi dibiarkan apa adanya sebagai string — nilainya label, bukan
     * angka, termasuk tahun lulus yang memang disimpan sebagai varchar.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string>  $measures
     * @param  array<string>  $dimensions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows, array $measures, array $dimensions): array
    {
        return array_map(function (array $row) use ($measures, $dimensions) {
            $out = [];

            foreach ($dimensions as $d) {
                $value = $row[$d] ?? null;
                $out[$d] = $value === null ? null : (string) $value;
            }

            foreach ($measures as $m) {
                $value = $row[$m] ?? null;
                $out[$m] = ($value === null || $value === '') ? null : (float) $value;
            }

            return $out;
        }, $rows);
    }
}
