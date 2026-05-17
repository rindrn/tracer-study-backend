<?php

namespace App\DTOs\Transactional;

class ThresholdResponseDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly float  $value,
        public readonly string $level,
        public readonly int    $indicatorId,
        public readonly string $indicatorKey,
        public readonly string $indicatorName,
        public readonly string $indicatorUnit,
        public readonly string $indicatorOperator,
        public readonly int    $lamVersionId,
        public readonly int    $lamVersionYear,
        public readonly string $createdAt,
    ) {}

    public static function fromRow(object $row): self
    {
        return new self(
            id:                $row->threshold_id,
            value:             (float) $row->threshold_value,
            level:             $row->threshold_level,
            indicatorId:       $row->indicator_id,
            indicatorKey:      $row->indicator_key,
            indicatorName:     $row->indicator_name,
            indicatorUnit:     $row->indicator_unit,
            indicatorOperator: $row->indicator_operator,
            lamVersionId:      $row->lam_version_id,
            lamVersionYear:    (int) $row->lam_version_year,
            createdAt:         $row->created_at,
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'value'     => $this->value,
            'level'     => $this->level,
            'indicator' => [
                'id'       => $this->indicatorId,
                'key'      => $this->indicatorKey,
                'name'     => $this->indicatorName,
                'unit'     => $this->indicatorUnit,
                'operator' => $this->indicatorOperator,
            ],
            'lam_version' => [
                'id'   => $this->lamVersionId,
                'year' => $this->lamVersionYear,
            ],
            'created_at' => $this->createdAt,
        ];
    }
}