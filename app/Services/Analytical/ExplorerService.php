<?php

namespace App\Services\Analytical;

use App\Exceptions\BusinessException;
use App\Repositories\Analytical\ExplorerRepository;
use App\Traits\WithCache;
use Illuminate\Support\Facades\Log;

/**
 * ExplorerService
 *
 * Otak halaman Insight (OLAP Explorer):
 *   - catalog()          → apa yang boleh dipilih pengguna
 *   - dimensionValues()  → isi dropdown filter satu dimensi
 *   - query()            → hasil kombinasi ukuran × dimensi yang disusun pengguna
 *   - drillDown()        → daftar alumni di balik satu angka
 *
 * KATALOG = config + model Cube. config/olap_catalog.php berisi ukuran siap
 * pakai dan dimensi; ukuran dari kolom angka ("Rata-rata gaji") dan dimensi
 * rentang ("Gaji per Rp 2 jt") dibangkitkan di model Cube.js dan dibaca dari
 * /meta (lihat cubes()). Keduanya diperlakukan sama oleh validasi.
 *
 * Semua permintaan divalidasi terhadap katalog SEBELUM menyentuh Cube.js.
 * Katalog adalah whitelist: nama member Cube.js yang tidak tercantum ditolak
 * 422, sehingga pengguna tidak bisa meminta NIM, nama alumni, atau cube lain
 * hanya dengan mengubah isi request.
 *
 * TIGA LAPIS UKURAN
 *   1. ukuran dasar  — siap pakai atau "[fungsi] dari [kolom angka]" (Cube)
 *   2. rumus         — dua ukuran digabung: ÷ − + × (dihitung di sini, dari
 *                      hasil agregasi; AVG(gaji)/AVG(UMP) = rasio dua rata-rata)
 *   3. olah hasil    — n minimum (= HAVING COUNT(*) >= n), urutkan & N teratas
 *                      (di sini), persen & selisih kolom (di FE, tampilan saja)
 *
 * Pivot, subtotal, dan chart dikerjakan di FE (src/lib/olapExplorer.ts); di
 * sini hasilnya dikembalikan sebagai baris datar ber-key nama member Cube.js
 * (dan key rumus: rumus_1..n).
 */
class ExplorerService
{
    use WithCache;

    private const TTL      = 3600;
    private const META_TTL = 600;

    /** Scope role yang ikut menentukan hasil — dan karena itu ikut jadi cache key. */
    private const SCOPE_KEYS = ['nama_prodi', 'jenjang', 'jurusan', 'id_prodi_in'];

    private const FILTER_OPERATORS = ['equals', 'notEquals', 'set', 'notSet'];

    private const FORMULA_OPS     = ['div' => '÷', 'sub' => '−', 'add' => '+', 'mul' => '×'];
    private const FORMULA_FORMATS = ['decimal', 'integer', 'currency', 'percent', 'ratio'];

    /** Katalog gabungan config + meta Cube, dihitung sekali per instance. */
    private ?array $resolved = null;

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
                // Hanya yang siap pakai; ukuran dari kolom angka ditawarkan
                // lewat numeric_columns ("[Rata-rata] dari [Gaji]").
                'measures'    => array_map(
                    fn (string $m) => $this->measureMeta($cube, $m),
                    array_keys($cube['measures']),
                ),
                'numeric_columns'  => $cube['numeric_columns'],
                'count_measure'    => $cube['count_measure'] ?? null,
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
    public function dimensionValues(string $dimension, array $scope, ?string $cubeKey = null): array
    {
        $cubeKey ??= $this->cubeOwningDimension($dimension);
        $cube = $cubeKey !== null ? ($this->cubes()[$cubeKey] ?? null) : null;

        if ($cube === null || $this->dimensionLabel($cube, $dimension) === null) {
            throw new BusinessException("Dimensi \"{$dimension}\" tidak tersedia untuk dianalisis.", 422);
        }

        $scope = $this->withSnapshot($this->onlyScope($scope), $cube);

        $values = $this->remember(
            $this->key('explorer:values', ['cube' => $cubeKey, 'dimension' => $dimension, 'scope' => $scope]),
            fn () => $this->repo->distinctValues($cube['base_measure'], $dimension, $scope),
            self::TTL,
            ['analytics-dashboard'],
        );

        return ['dimension' => $dimension, 'values' => $values];
    }

    // ── QUERY ─────────────────────────────────────────────────────

    /**
     * @param array{
     *   cube: string, measures?: string[], dimensions?: string[], filters?: array,
     *   formulas?: array, min_n?: ?int, sort?: ?array, column_dimension?: ?string
     * } $body
     */
    public function query(array $body, array $scope): array
    {
        $q    = $this->parse($body);
        $cube = $this->validate($q);

        $scope   = $this->withSnapshot($this->onlyScope($scope), $cube);
        $maxRows = $this->limits()['max_rows'];

        $cached = $this->remember(
            $this->key('explorer:query', ['q' => $q, 'scope' => $scope]),
            fn () => $this->run($cube, $q, $scope, $maxRows),
            self::TTL,
            ['analytics-dashboard'],
        );

        return [
            'cube'       => $q['cube'],
            'measures'   => [
                ...array_map(fn ($m) => $this->measureMeta($cube, $m), $q['measures']),
                ...array_map(fn ($f) => $this->formulaMeta($cube, $f), $q['formulas']),
            ],
            'dimensions' => array_map(
                fn ($d) => ['key' => $d, 'label' => $this->dimensionLabel($cube, $d)],
                $q['dimensions'],
            ),
            'rows'       => $cached['rows'],
            'row_count'  => count($cached['rows']),
            'truncated'  => $cached['truncated'],
        ];
    }

    /**
     * Alumni di balik satu angka hasil Insight.
     *
     * `filters` = saringan pengguna + nilai dimensi titik yang diklik. Nama dan
     * NIM hanya keluar lewat jalur ini, selalu dalam scope role dan snapshot
     * yang sama dengan angkanya — sama seperti drill-down dashboard lain.
     * Katalog tetap tidak menawarkan nama/NIM sebagai dimensi atau saringan.
     * Untuk rumus, FE mengirim ukuran pembilangnya.
     *
     * @param array{cube: string, measure: string, filters?: array, search?: ?string, page?: int, per_page?: int} $body
     */
    public function drillDown(array $body, array $scope): array
    {
        $q = $this->parse([
            'cube'     => $body['cube'],
            'measures' => [$body['measure']],
            'filters'  => $body['filters'] ?? [],
        ]);
        $cube    = $this->validate($q);
        $measure = $q['measures'][0];

        $scope   = $this->withSnapshot($this->onlyScope($scope), $cube);
        $page    = max(1, (int) ($body['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($body['per_page'] ?? 15)));
        $meta    = $this->measureMeta($cube, $measure);
        $search  = isset($body['search']) ? trim((string) $body['search']) : null;

        $rows = $this->repo->drillDown(
            $measure,
            $meta['format'] === 'integer',
            $q['filters'],
            $scope,
            $search === '' ? null : $search,
            $page,
            $perPage,
            $cube['drill_dimensions'],
        );

        return [
            'measure'    => $meta,
            'data'       => $rows,
            'pagination' => [
                'page'          => $page,
                'per_page'      => $perPage,
                'total_on_page' => count($rows),
            ],
        ];
    }

    /**
     * Validasi susunan pertanyaan berbentuk FE ({ cube, measures, rowDims,
     * colDim, filters, formulas, minN, sort }) — dipakai saat menyimpan
     * pertanyaan, supaya yang tersimpan pasti bisa dijalankan kembali.
     */
    public function assertValidInput(array $input): void
    {
        $colDim = $input['colDim'] ?? null;

        $this->validate($this->parse([
            'cube'             => (string) ($input['cube'] ?? ''),
            'measures'         => $input['measures'] ?? [],
            'dimensions'       => array_values(array_filter([...($input['rowDims'] ?? []), $colDim])),
            'filters'          => $input['filters'] ?? [],
            'formulas'         => $input['formulas'] ?? [],
            'min_n'            => $input['minN'] ?? null,
            'sort'             => $input['sort'] ?? null,
            'column_dimension' => $colDim,
        ]));
    }

    // ──────────────────────────────────────────────────────────────
    //  PERMINTAAN → HASIL
    // ──────────────────────────────────────────────────────────────

    /** Bentuk permintaan yang seragam; isinya belum divalidasi. */
    private function parse(array $body): array
    {
        $sort = $body['sort'] ?? null;

        return [
            'cube'       => (string) ($body['cube'] ?? ''),
            'measures'   => array_values($body['measures'] ?? []),
            'dimensions' => array_values($body['dimensions'] ?? []),
            'filters'    => $this->activeFilters($body['filters'] ?? []),
            'formulas'   => array_values(array_map(fn (array $f) => [
                'key'    => (string) ($f['key'] ?? ''),
                'label'  => trim((string) ($f['label'] ?? '')),
                'left'   => (string) ($f['left'] ?? ''),
                'op'     => (string) ($f['op'] ?? ''),
                'right'  => (string) ($f['right'] ?? ''),
                'format' => (string) ($f['format'] ?? 'decimal'),
            ], $body['formulas'] ?? [])),
            'min_n'      => isset($body['min_n']) && $body['min_n'] !== '' ? (int) $body['min_n'] : null,
            'sort'       => is_array($sort) ? [
                'by'        => (string) ($sort['by'] ?? ''),
                'direction' => (string) ($sort['direction'] ?? 'desc'),
                'limit'     => isset($sort['limit']) && $sort['limit'] !== '' ? (int) $sort['limit'] : null,
            ] : null,
            'column_dimension' => $body['column_dimension'] ?? null,
        ];
    }

    /**
     * Jalankan permintaan yang sudah divalidasi: satu query Cube untuk
     * seluruh ukuran yang dibutuhkan, lalu rumus, n minimum, dan urutan.
     *
     * @return array{rows: array, truncated: bool}
     */
    private function run(array $cube, array $q, array $scope, int $maxRows): array
    {
        $rows = $this->repo
            ->query($this->cubeMeasures($cube, $q, $q['measures']), $q['dimensions'], $q['filters'], $scope, $maxRows + 1)
            ->all();

        // +1 baris di atas hanya untuk tahu apakah hasilnya terpotong.
        $truncated = count($rows) > $maxRows;
        $rows      = $this->applyFormulas(array_slice($rows, 0, $maxRows), $q['formulas']);

        if ($q['min_n'] !== null) {
            $rows = $this->dropSmallGroups($rows, $cube, $q['min_n']);
        }

        if ($q['sort'] !== null) {
            $rows = $this->applySort($rows, $cube, $q, $scope, $maxRows);
        }

        return ['rows' => array_values($rows), 'truncated' => $truncated];
    }

    /**
     * Measure Cube yang benar-benar perlu diminta: yang dipilih, pembilang &
     * penyebut rumus, dan cacah dasar bila ada n minimum. Yang tidak dipilih
     * pengguna tetap ada di baris, tapi tidak muncul di result.measures —
     * FE hanya menampilkan yang tercantum di sana.
     */
    private function cubeMeasures(array $cube, array $q, array $selected): array
    {
        $needed = $selected;

        foreach ($q['formulas'] as $f) {
            $needed[] = $f['left'];
            $needed[] = $f['right'];
        }

        if ($q['min_n'] !== null) {
            $needed[] = $cube['count_measure'];
        }

        return array_values(array_unique($needed));
    }

    private function applyFormulas(array $rows, array $formulas): array
    {
        if ($formulas === []) {
            return $rows;
        }

        return array_map(function (array $row) use ($formulas) {
            foreach ($formulas as $f) {
                $row[$f['key']] = $this->evaluate($f, $this->num($row[$f['left']] ?? null), $this->num($row[$f['right']] ?? null));
            }
            return $row;
        }, $rows);
    }

    /** Nilai kosong atau pembagian dengan nol menghasilkan null — bukan 0 yang terbaca sebagai fakta. */
    private function evaluate(array $f, ?float $a, ?float $b): ?float
    {
        if ($a === null || $b === null) {
            return null;
        }

        $value = match ($f['op']) {
            'div' => $b == 0.0 ? null : $a / $b,
            'sub' => $a - $b,
            'add' => $a + $b,
            'mul' => $a * $b,
        };

        return $value !== null && $f['op'] === 'div' && $f['format'] === 'percent' ? $value * 100 : $value;
    }

    /** = HAVING COUNT(*) >= n: kelompok (sel) dengan responden terlalu sedikit dibuang. */
    private function dropSmallGroups(array $rows, array $cube, int $minN): array
    {
        $count = $cube['count_measure'];

        return array_filter($rows, fn (array $r) => ($this->num($r[$count] ?? null) ?? 0) >= $minN);
    }

    /**
     * Urutkan & ambil N teratas.
     *
     * Tanpa dimensi kolom, setiap baris adalah satu kelompok: diurutkan
     * langsung. Dengan dimensi kolom, satu kelompok baris tersebar di
     * beberapa sel, dan peringkatnya tidak bisa diturunkan dari sel-sel itu
     * (rata-rata dari rata-rata keliru). Jadi dijalankan query kedua yang
     * mengelompokkan menurut dimensi BARIS saja untuk mendapat nilai
     * peringkat yang tepat, lalu baris utama disaring & diurutkan menurutnya.
     */
    private function applySort(array $rows, array $cube, array $q, array $scope, int $maxRows): array
    {
        $sort    = $q['sort'];
        $rowDims = array_values(array_diff($q['dimensions'], [$q['column_dimension']]));

        if ($q['column_dimension'] === null || $rowDims === []) {
            $sorted = $this->sortByValue($rows, $sort['by'], $sort['direction']);
            return $sort['limit'] !== null ? array_slice($sorted, 0, $sort['limit']) : $sorted;
        }

        $formula  = collect($q['formulas'])->firstWhere('key', $sort['by']);
        $ranking  = $this->repo
            ->query($this->cubeMeasures($cube, ['formulas' => $formula ? [$formula] : [], 'min_n' => $q['min_n']], $formula ? [] : [$sort['by']]), $rowDims, $q['filters'], $scope, $maxRows)
            ->all();
        $ranking = $this->applyFormulas($ranking, $formula ? [$formula] : []);

        if ($q['min_n'] !== null) {
            $ranking = $this->dropSmallGroups($ranking, $cube, $q['min_n']);
        }

        $ranking = $this->sortByValue($ranking, $sort['by'], $sort['direction']);
        if ($sort['limit'] !== null) {
            $ranking = array_slice($ranking, 0, $sort['limit']);
        }

        $rank = [];
        foreach (array_values($ranking) as $i => $r) {
            $rank[$this->tupleKey($r, $rowDims)] = $i;
        }

        $kept = array_filter($rows, fn (array $r) => isset($rank[$this->tupleKey($r, $rowDims)]));
        usort($kept, fn (array $a, array $b) => $rank[$this->tupleKey($a, $rowDims)] <=> $rank[$this->tupleKey($b, $rowDims)]);

        return $kept;
    }

    /** Urut stabil; nilai kosong selalu di bawah, apa pun arahnya. */
    private function sortByValue(array $rows, string $by, string $direction): array
    {
        $rows = array_values($rows);
        $indexed = array_map(fn ($r, $i) => [$r, $i], $rows, array_keys($rows));

        usort($indexed, function ($x, $y) use ($by, $direction) {
            $a = $this->num($x[0][$by] ?? null);
            $b = $this->num($y[0][$by] ?? null);

            if ($a === null || $b === null) {
                return [$a === null, $x[1]] <=> [$b === null, $y[1]];
            }

            $cmp = $direction === 'asc' ? $a <=> $b : $b <=> $a;
            return $cmp !== 0 ? $cmp : $x[1] <=> $y[1];
        });

        return array_map(fn ($p) => $p[0], $indexed);
    }

    private function tupleKey(array $row, array $dims): string
    {
        return json_encode(array_map(fn ($d) => $row[$d] ?? null, $dims));
    }

    private function num(mixed $v): ?float
    {
        return $v === null || $v === '' || !is_numeric($v) ? null : (float) $v;
    }

    /**
     * Saringan yang benar-benar berlaku: "sama dengan"/"bukan" tanpa nilai
     * dibuang (belum diisi pengguna); "ada nilainya"/"kosong" tidak butuh nilai.
     */
    private function activeFilters(array $filters): array
    {
        $active = [];

        foreach ($filters as $f) {
            if (!is_array($f)) {
                continue;
            }

            $operator = $f['operator'] ?? 'equals';
            $values   = array_values(array_map('strval', array_filter($f['values'] ?? [], fn ($v) => $v !== null)));

            if (in_array($operator, ['set', 'notSet'], true)) {
                $active[] = ['member' => (string) ($f['member'] ?? ''), 'operator' => $operator, 'values' => []];
            } elseif ($values !== []) {
                $active[] = ['member' => (string) ($f['member'] ?? ''), 'operator' => $operator, 'values' => $values];
            }
        }

        return $active;
    }

    // ──────────────────────────────────────────────────────────────
    //  VALIDASI
    // ──────────────────────────────────────────────────────────────

    /** Tolak apa pun di luar katalog. Pesannya dibaca pengguna non-IT, jadi pakai label. */
    private function validate(array $q): array
    {
        $cube = $this->cubes()[$q['cube']] ?? null;
        if ($cube === null) {
            throw new BusinessException('Sumber data yang dipilih tidak tersedia.', 422);
        }

        $limits     = $this->limits();
        $measures   = $q['measures'];
        $dimensions = $q['dimensions'];
        $formulas   = $q['formulas'];

        if ($measures === [] && $formulas === []) {
            throw new BusinessException('Pilih minimal satu ukuran untuk ditampilkan.', 422);
        }
        if (count($measures) > $limits['max_measures']) {
            throw new BusinessException("Maksimal {$limits['max_measures']} ukuran sekaligus.", 422);
        }
        if (count($formulas) > ($limits['max_formulas'] ?? 3)) {
            throw new BusinessException('Maksimal ' . ($limits['max_formulas'] ?? 3) . ' rumus sekaligus.', 422);
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
            if ($this->measureDef($cube, $m) === null) {
                throw new BusinessException("Ukuran \"{$m}\" tidak tersedia pada sumber data ini.", 422);
            }
        }

        foreach ($dimensions as $d) {
            if ($this->dimensionLabel($cube, $d) === null) {
                throw new BusinessException("Dimensi \"{$d}\" tidak tersedia pada sumber data ini.", 422);
            }
        }

        foreach ($q['filters'] as $f) {
            if ($this->dimensionLabel($cube, $f['member']) === null) {
                throw new BusinessException("Saringan \"{$f['member']}\" tidak tersedia pada sumber data ini.", 422);
            }
            if (!in_array($f['operator'], self::FILTER_OPERATORS, true)) {
                throw new BusinessException('Jenis saringan tidak dikenal.', 422);
            }
        }

        $formulaKeys = [];
        foreach ($formulas as $f) {
            $this->validateFormula($cube, $f);
            $formulaKeys[] = $f['key'];
        }
        if (count($formulaKeys) !== count(array_unique($formulaKeys))) {
            throw new BusinessException('Setiap rumus harus punya kunci berbeda.', 422);
        }

        if ($q['min_n'] !== null) {
            if ($q['min_n'] < 1 || $q['min_n'] > 100000) {
                throw new BusinessException('Jumlah responden minimum harus antara 1 dan 100.000.', 422);
            }
            if (empty($cube['count_measure'])) {
                throw new BusinessException('Sumber data ini tidak mendukung batas jumlah responden.', 422);
            }
        }

        if ($q['sort'] !== null) {
            $sort = $q['sort'];
            if (!in_array($sort['by'], [...$measures, ...$formulaKeys], true)) {
                throw new BusinessException('Urutan harus memakai salah satu ukuran yang ditampilkan.', 422);
            }
            if (!in_array($sort['direction'], ['asc', 'desc'], true)) {
                throw new BusinessException('Arah urutan harus terbesar atau terkecil.', 422);
            }
            if ($sort['limit'] !== null && ($sort['limit'] < 1 || $sort['limit'] > 500)) {
                throw new BusinessException('Jumlah teratas harus antara 1 dan 500.', 422);
            }
        }

        if ($q['column_dimension'] !== null && end($dimensions) !== $q['column_dimension']) {
            throw new BusinessException('Dimensi kolom tidak cocok dengan susunan dimensi.', 422);
        }

        return $cube;
    }

    private function validateFormula(array $cube, array $f): void
    {
        if (!preg_match('/^rumus_[1-9]$/', $f['key'])) {
            throw new BusinessException('Kunci rumus tidak valid.', 422);
        }
        if ($f['label'] === '' || mb_strlen($f['label']) > 150) {
            throw new BusinessException('Beri nama rumus (maksimal 150 huruf).', 422);
        }
        if (!isset(self::FORMULA_OPS[$f['op']])) {
            throw new BusinessException('Rumus hanya boleh memakai ÷, −, +, atau ×.', 422);
        }
        if (!in_array($f['format'], self::FORMULA_FORMATS, true)) {
            throw new BusinessException('Tampilan hasil rumus tidak dikenal.', 422);
        }
        if (in_array($f['format'], ['percent', 'ratio'], true) && $f['op'] !== 'div') {
            throw new BusinessException('Persen dan "berapa kali" hanya untuk rumus pembagian.', 422);
        }
        foreach ([$f['left'], $f['right']] as $m) {
            if ($this->measureDef($cube, $m) === null) {
                throw new BusinessException("Ukuran \"{$m}\" di dalam rumus tidak tersedia pada sumber data ini.", 422);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  KATALOG GABUNGAN
    // ──────────────────────────────────────────────────────────────

    /**
     * config/olap_catalog.php + member yang dibangkitkan di model Cube:
     *   - measure ber-meta kind=agg  → generated_measures + numeric_columns
     *   - dimensi ber-meta kind=bin  → grup dimensi "Rentang angka"
     *
     * Hanya member milik cube itu sendiri yang diambil, dan hanya dua jenis
     * meta tersebut — kolom identitas tidak pernah punya meta seperti ini,
     * jadi tidak bisa masuk lewat jalur otomatis.
     */
    private function cubes(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $meta = collect($this->cubeMeta())->keyBy('name');
        $out  = [];

        foreach (config('olap_catalog.cubes', []) as $key => $cube) {
            $prefix    = $key . '.';
            $generated = [];
            $columns   = [];
            $bins      = [];

            foreach ($meta->get($key)['measures'] ?? [] as $m) {
                $x = $m['meta'] ?? null;
                if (($x['kind'] ?? null) !== 'agg' || !str_starts_with($m['name'], $prefix)) {
                    continue;
                }

                $generated[$m['name']] = [
                    'label'       => $x['fn_label'] . ' ' . $this->lowerFirst($x['column_label']),
                    'format'      => $x['format'] ?? 'decimal',
                    'description' => "{$x['fn_label']} dari {$x['column_label']}.",
                ];

                $columns[$x['column']] ??= [
                    'column'    => $x['column'],
                    'label'     => $x['column_label'],
                    'format'    => $x['format'] ?? 'decimal',
                    'functions' => [],
                ];
                $columns[$x['column']]['functions'][] = ['fn' => $x['fn'], 'label' => $x['fn_label'], 'key' => $m['name']];
            }

            foreach ($meta->get($key)['dimensions'] ?? [] as $d) {
                $x = $d['meta'] ?? null;
                if (($x['kind'] ?? null) !== 'bin' || !str_starts_with($d['name'], $prefix)) {
                    continue;
                }
                // "Masa tunggu kerja (bulan) per 3 bulan" → "Masa tunggu kerja per 3 bulan"
                $column = preg_replace('/\s*\([^)]*\)$/', '', $x['column_label']);
                $bins[$d['name']] = "{$column} {$x['width_label']}";
            }

            $cube['measures']           ??= [];
            $cube['generated_measures']   = $generated;
            $cube['numeric_columns']      = array_values($columns);
            $cube['drill_dimensions']   ??= [];
            if ($bins !== []) {
                $cube['dimension_groups']['Rentang angka'] = $bins;
            }

            $out[$key] = $cube;
        }

        return $this->resolved = $out;
    }

    /**
     * Meta Cube di-cache sebentar. Kalau Cube sedang tidak bisa dihubungi,
     * katalog tetap berfungsi dengan isi config saja (tanpa ukuran dari
     * kolom angka), bukan ikut gagal — dan kegagalan itu tidak di-cache.
     */
    private function cubeMeta(): array
    {
        try {
            return $this->remember('explorer:cube-meta', fn () => $this->repo->cubeMeta(), self::META_TTL, ['analytics-dashboard']);
        } catch (\Throwable $e) {
            Log::warning('ExplorerService: meta Cube tidak terbaca, katalog memakai config saja', ['error' => $e->getMessage()]);
            return [];
        }
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

    private function measureDef(array $cube, string $measure): ?array
    {
        return $cube['measures'][$measure] ?? $cube['generated_measures'][$measure] ?? null;
    }

    private function measureMeta(array $cube, string $measure): array
    {
        $m = $this->measureDef($cube, $measure);

        return [
            'key'         => $measure,
            'label'       => $m['label'],
            'format'      => $m['format'],
            'description' => $m['description'] ?? '',
        ];
    }

    private function formulaMeta(array $cube, array $f): array
    {
        $left  = $this->measureDef($cube, $f['left'])['label'];
        $right = $this->measureDef($cube, $f['right'])['label'];

        return [
            'key'         => $f['key'],
            'label'       => $f['label'],
            'format'      => $f['format'],
            'description' => "{$left} " . self::FORMULA_OPS[$f['op']] . " {$this->lowerFirst($right)}",
            // left_* dipakai FE untuk drill-down: angka rumus diklik → alumni
            // di balik pembilangnya, dengan aturan cacah/nilai milik pembilang.
            'formula'     => [
                'left'        => $f['left'],
                'op'          => $f['op'],
                'right'       => $f['right'],
                'left_label'  => $this->measureDef($cube, $f['left'])['label'],
                'left_format' => $this->measureDef($cube, $f['left'])['format'],
            ],
        ];
    }

    /** "Gaji" → "gaji", tapi "UMP provinsi" tetap "UMP provinsi". */
    private function lowerFirst(string $s): string
    {
        return preg_match('/^\p{Lu}\p{Ll}/u', $s) ? mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1) : $s;
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
