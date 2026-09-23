<?php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kartu di "Dashboard Saya" halaman Insight. size: 'sm' (setengah lebar) | 'lg' (penuh). */
class InsightDashboardItem extends Model
{
    protected $connection = 'oltp';

    public const SIZES = ['sm', 'lg'];

    protected $fillable = ['user_id', 'question_id', 'position', 'size'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(InsightQuestion::class, 'question_id');
    }
}
