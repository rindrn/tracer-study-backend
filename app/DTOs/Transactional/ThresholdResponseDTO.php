<?php
namespace App\DTOs\Transactional;
 
// ── Response ─────────────────────────────────────────────────
class ThresholdResponseDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly float  $value,
        public readonly array  $programs,
        public readonly string $createdAt,
    ) {}
 
    // fromRow: dari raw object hasil query vw_thresholds_with_programs
    // programs kolom sudah berupa JSON string dari JSON_AGG di View
    public static function fromRow(object $row): self
    {
        return new self(
            id:        $row->threshold_id,
            name:      $row->threshold_name,
            value:     (float) $row->threshold_value,
            programs:  json_decode($row->programs ?? '[]', true),
            createdAt: $row->created_at,
        );
    }
 
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'value'      => $this->value,
            'programs'   => $this->programs,
            'created_at' => $this->createdAt,
        ];
    }
}