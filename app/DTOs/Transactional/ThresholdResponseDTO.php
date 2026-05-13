<?php

namespace App\DTOs\Transactional;

class ThresholdResponseDTO
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly float   $value,
        public readonly string  $unit,
        public readonly string  $operator,
        public readonly int     $lamVersionId,
        public readonly int     $lamVersionYear,
        public readonly ?string $createdAt,   // ← nullable
    ) {}

    public static function fromRow(object $row): self
    {
        return new self(
            id:             $row->threshold_id,
            name:           $row->threshold_name,
            value:          (float) $row->threshold_value,
            unit:           $row->threshold_unit     ?? '%',
            operator:       $row->threshold_operator ?? '>=',
            lamVersionId:   $row->lam_version_id,
            lamVersionYear: (int) $row->lam_version_year,
            createdAt:      $row->created_at ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'value'       => $this->value,
            'unit'        => $this->unit,
            'operator'    => $this->operator,
            'lam_version' => [
                'id'   => $this->lamVersionId,
                'year' => $this->lamVersionYear,
            ],
            'created_at'  => $this->createdAt,
        ];
    }
}