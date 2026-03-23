<?php
namespace App\DTOs\Transactional;
 
use App\Models\Transactional\Program;
 
// ── Response ─────────────────────────────────────────────────
class ProgramResponseDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string $degree,
        public readonly bool   $isActive,
        public readonly string $createdAt,
    ) {}
 
    public static function fromModel(Program $model): self
    {
        return new self(
            id:        $model->id,
            name:      $model->name,
            code:      $model->code,
            degree:    $model->degree,
            isActive:  (bool) $model->is_active,
            createdAt: $model->created_at->toISOString(),
        );
    }
 
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'degree'     => $this->degree,
            'is_active'  => $this->isActive,
            'created_at' => $this->createdAt,
        ];
    }
}
