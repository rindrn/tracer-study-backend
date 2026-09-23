<?php

namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\InsightDashboardItem;
use App\Models\Transactional\User;
use Illuminate\Support\Facades\DB;

/**
 * InsightBoardService — "Dashboard Saya" halaman Insight.
 *
 * Setiap pengguna punya satu papan berisi pertanyaan tersimpan yang ia
 * sematkan. Papan hanya menyimpan RUJUKAN ke pertanyaan; tiap kartu
 * menjalankan query-nya sendiri di FE dengan scope pengguna itu.
 */
class InsightBoardService
{
    public function __construct(
        private readonly InsightQuestionService $questions,
    ) {}

    /** Kartu berurutan. Pertanyaan yang tak lagi dibagikan kepadanya tidak ditampilkan. */
    public function list(User $user): array
    {
        return InsightDashboardItem::query()
            ->where('user_id', $user->id)
            ->whereHas('question', fn ($q) => $q->visibleTo($user->id))
            ->with('question.user:id,name')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (InsightDashboardItem $item) => $this->present($item, $user))
            ->all();
    }

    /** Menyematkan dua kali tidak membuat kartu kembar — kartu yang ada dikembalikan. */
    public function pin(User $user, int $questionId, string $size = 'sm'): array
    {
        $this->assertSize($size);
        $question = $this->questions->findVisible($user, $questionId);

        $item = InsightDashboardItem::query()
            ->where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->first();

        if ($item === null) {
            $next = (int) InsightDashboardItem::query()->where('user_id', $user->id)->max('position') + 1;

            $item = InsightDashboardItem::create([
                'user_id'     => $user->id,
                'question_id' => $question->id,
                'position'    => $next,
                'size'        => $size,
            ]);
        }

        return $this->present($item->load('question.user:id,name'), $user);
    }

    public function resize(User $user, int $itemId, string $size): array
    {
        $this->assertSize($size);

        $item = $this->findOwn($user, $itemId);
        $item->update(['size' => $size]);

        return $this->present($item->load('question.user:id,name'), $user);
    }

    public function unpin(User $user, int $itemId): void
    {
        $this->findOwn($user, $itemId)->delete();
    }

    /**
     * @param int[] $itemIds seluruh kartu milik pengguna, dalam urutan baru
     */
    public function reorder(User $user, array $itemIds): void
    {
        $own = InsightDashboardItem::query()->where('user_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids = array_map('intval', $itemIds);

        // Urutan parsial akan membuat posisi kartu yang tidak disebut jadi
        // ambigu (bisa bertabrakan) — minta daftar lengkap.
        sort($own);
        $sorted = $ids;
        sort($sorted);
        if ($sorted !== $own) {
            throw new BusinessException('Urutan harus memuat seluruh kartu di Dashboard Saya, tepat satu kali.', 422);
        }

        DB::connection('oltp')->transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                InsightDashboardItem::query()->whereKey($id)->update(['position' => $position + 1]);
            }
        });
    }

    // ──────────────────────────────────────────────────────────────

    private function findOwn(User $user, int $itemId): InsightDashboardItem
    {
        $item = InsightDashboardItem::query()->where('user_id', $user->id)->find($itemId);

        if ($item === null) {
            throw new BusinessException('Kartu tidak ditemukan di Dashboard Saya.', 404);
        }

        return $item;
    }

    private function assertSize(string $size): void
    {
        if (!in_array($size, InsightDashboardItem::SIZES, true)) {
            throw new BusinessException('Ukuran kartu harus sm atau lg.', 422);
        }
    }

    private function present(InsightDashboardItem $item, User $viewer): array
    {
        return [
            'id'       => $item->id,
            'position' => $item->position,
            'size'     => $item->size,
            'question' => $this->questions->present($item->question, $viewer),
        ];
    }
}
