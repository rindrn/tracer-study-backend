<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registry semantic_role: kontrak tipe data (expected_kind/value_min/max)
 * + kolom OLAP tujuan (target_table/target_column) + kategori KPI untuk
 * pengelompokan dropdown admin. Single source of truth pengganti hardcode
 * question_code->kolom OLAP yang sebelumnya tersebar di beberapa Service ETL.
 */
class SemanticRoleRegistry extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'semantic_role_registry';
    protected $primaryKey = 'role_key';
    public $incrementing  = false;
    protected $keyType    = 'string';
    public $timestamps    = true;

    protected $fillable = [
        'role_key', 'label', 'category', 'description', 'expected_kind',
        'value_min', 'value_max', 'sample_valid_answer', 'target_table',
        'target_column', 'grain', 'is_active',
    ];

    protected $casts = [
        'value_min' => 'decimal:2',
        'value_max' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function mappings(): HasMany
    {
        return $this->hasMany(QuestionSemanticMapping::class, 'semantic_role', 'role_key');
    }
}
