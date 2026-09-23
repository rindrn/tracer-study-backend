<?php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pertanyaan tersimpan halaman Insight. `query` berbentuk sama dengan
 * ExplorerQueryInput di FE: { cube, measures, rowDims, colDim, filters }.
 */
class InsightQuestion extends Model
{
    protected $connection = 'oltp';

    protected $fillable = ['user_id', 'title', 'description', 'query', 'chart_measure', 'is_shared'];

    protected $casts = [
        'query'     => 'array',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Milik sendiri atau dibagikan — yang boleh dilihat seorang pengguna. */
    public function scopeVisibleTo(Builder $q, int $userId): Builder
    {
        return $q->where(fn (Builder $w) => $w->where('user_id', $userId)->orWhere('is_shared', true));
    }
}
