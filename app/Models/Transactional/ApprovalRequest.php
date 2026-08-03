<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'approval_requests';
    protected $fillable   = ['requester_id', 'approver_id', 'type', 'payload', 'status', 'note', 'resolved_at'];
    protected $casts      = ['payload' => 'array', 'resolved_at' => 'datetime'];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_ADD_QUESTIONNAIRE    = 'add_questionnaire';
    public const TYPE_DELETE_QUESTIONNAIRE  = 'delete_questionnaire';

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
