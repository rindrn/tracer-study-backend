<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu question_code (dalam satu questionnaire) yang dipetakan
 * ke satu semantic_role, di titik waktu tertentu. Versioned forward-only via
 * is_active -- lihat 002_semantic_mapping_schema.sql untuk constraint A/B
 * (unique partial index per grain).
 *
 * TIDAK ada relasi Eloquent ke Questionnaire -- codebase ini tidak punya
 * model Eloquent untuk tabel `questionnaires` (QuestionnaireRepository murni
 * query builder, lihat app/Repositories/Transactional/QuestionnaireRepository.php),
 * jadi questionnaire_id dibiarkan sebagai atribut biasa, bukan relasi.
 */
class QuestionSemanticMapping extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'question_semantic_mapping';

    protected $fillable = [
        'questionnaire_id', 'question_code', 'question_text_snapshot',
        'semantic_role', 'grain', 'effective_date', 'is_active',
        'mapped_by', 'deactivated_at', 'deactivated_by',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'effective_date' => 'date',
        'deactivated_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(SemanticRoleRegistry::class, 'semantic_role', 'role_key');
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }
}
