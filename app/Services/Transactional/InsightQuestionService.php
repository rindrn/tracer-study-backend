<?php

namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\InsightQuestion;
use App\Models\Transactional\User;
use App\Services\Analytical\ExplorerService;

/**
 * InsightQuestionService
 *
 * Simpan, buka, ubah, dan hapus pertanyaan halaman Insight.
 *
 * Aturan akses:
 *   - lihat  : milik sendiri, atau yang dibagikan (is_shared)
 *   - ubah/hapus : hanya pemiliknya
 *
 * Membagikan pertanyaan aman karena yang dibagikan hanya susunannya; saat
 * dibuka, query dijalankan ulang dengan scope role si pembuka.
 */
class InsightQuestionService
{
    /** Bentuk visualisasi yang boleh disimpan; harus sama dengan `Viz` di FE. */
    private const VIZ = ['auto', 'table', 'bar', 'row', 'line', 'area', 'stacked', 'pie'];

    public function __construct(
        private readonly ExplorerService $explorer,
    ) {}

    /** @return array<int, array> Milik sendiri dulu, lalu yang dibagikan orang lain; terbaru di atas. */
    public function list(User $user): array
    {
        return InsightQuestion::query()
            ->visibleTo($user->id)
            ->with('user:id,name')
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InsightQuestion $q) => $this->present($q, $user))
            ->all();
    }

    public function show(User $user, int $id): array
    {
        return $this->present($this->findVisible($user, $id), $user);
    }

    public function create(User $user, array $data): array
    {
        $this->explorer->assertValidInput($data['query']);

        $question = InsightQuestion::create([
            'user_id'       => $user->id,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'query'         => $this->normalizeQuery($data['query']),
            'chart_measure' => $data['chart_measure'] ?? null,
            'is_shared'     => (bool) ($data['is_shared'] ?? false),
        ]);

        return $this->present($question->load('user:id,name'), $user);
    }

    public function update(User $user, int $id, array $data): array
    {
        $question = $this->findOwned($user, $id);

        if (array_key_exists('query', $data)) {
            $this->explorer->assertValidInput($data['query']);
            $data['query'] = $this->normalizeQuery($data['query']);
        }

        $question->fill(array_intersect_key(
            $data,
            array_flip(['title', 'description', 'query', 'chart_measure', 'is_shared']),
        ))->save();

        return $this->present($question->load('user:id,name'), $user);
    }

    public function delete(User $user, int $id): void
    {
        $this->findOwned($user, $id)->delete();
    }

    /** Dipakai juga oleh dashboard Insight: pertanyaan yang boleh dilihat user. */
    public function findVisible(User $user, int $id): InsightQuestion
    {
        $question = InsightQuestion::query()->visibleTo($user->id)->with('user:id,name')->find($id);

        if ($question === null) {
            throw new BusinessException('Pertanyaan tidak ditemukan atau tidak dibagikan kepada Anda.', 404);
        }

        return $question;
    }

    public function present(InsightQuestion $q, User $viewer): array
    {
        return [
            'id'            => $q->id,
            'title'         => $q->title,
            'description'   => $q->description,
            'query'         => $q->query,
            'chart_measure' => $q->chart_measure,
            'is_shared'     => $q->is_shared,
            'is_mine'       => (int) $q->user_id === (int) $viewer->id,
            'owner_name'    => $q->user?->name,
            'updated_at'    => $q->updated_at?->toIso8601String(),
        ];
    }

    // ──────────────────────────────────────────────────────────────

    private function findOwned(User $user, int $id): InsightQuestion
    {
        $question = $this->findVisible($user, $id);

        if ((int) $question->user_id !== (int) $user->id) {
            throw new BusinessException('Hanya pembuat pertanyaan yang dapat mengubah atau menghapusnya.', 403);
        }

        return $question;
    }

    /**
     * Simpan dalam bentuk yang dipakai FE apa adanya, tanpa kunci lain.
     * `percent`, `diff`, dan `viz` hanya tampilan (diolah di FE), jadi cukup
     * diperiksa bentuknya; sisanya sudah divalidasi assertValidInput().
     */
    private function normalizeQuery(array $query): array
    {
        $sort = $query['sort'] ?? null;
        $diff = $query['diff'] ?? null;

        return [
            'cube'     => (string) $query['cube'],
            'measures' => array_values($query['measures'] ?? []),
            'rowDims'  => array_values($query['rowDims'] ?? []),
            'colDim'   => $query['colDim'] ?? null,
            'filters'  => array_values(array_map(fn (array $f) => [
                'member'   => (string) $f['member'],
                'operator' => in_array($f['operator'] ?? 'equals', ['equals', 'notEquals', 'set', 'notSet'], true)
                    ? ($f['operator'] ?? 'equals')
                    : 'equals',
                'values'   => array_values(array_map('strval', $f['values'] ?? [])),
            ], $query['filters'] ?? [])),
            'formulas' => array_values(array_map(fn (array $f) => [
                'key'    => (string) $f['key'],
                'label'  => (string) $f['label'],
                'left'   => (string) $f['left'],
                'op'     => (string) $f['op'],
                'right'  => (string) $f['right'],
                'format' => (string) ($f['format'] ?? 'decimal'),
            ], $query['formulas'] ?? [])),
            'minN'     => isset($query['minN']) ? (int) $query['minN'] : null,
            'sort'     => is_array($sort) ? [
                'by'        => (string) $sort['by'],
                'direction' => $sort['direction'] === 'asc' ? 'asc' : 'desc',
                'limit'     => isset($sort['limit']) ? (int) $sort['limit'] : null,
            ] : null,
            'percent'  => in_array($query['percent'] ?? 'none', ['none', 'row', 'column', 'all'], true)
                ? ($query['percent'] ?? 'none')
                : 'none',
            'diff'     => is_array($diff) && isset($diff['a'], $diff['b'])
                ? ['a' => (string) $diff['a'], 'b' => (string) $diff['b']]
                : null,
            'viz'      => in_array($query['viz'] ?? 'auto', self::VIZ, true)
                ? ($query['viz'] ?? 'auto')
                : 'auto',
        ];
    }
}
