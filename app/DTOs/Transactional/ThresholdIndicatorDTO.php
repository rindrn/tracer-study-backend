<?php
// app/DTOs/Transactional/ThresholdIndicatorDTO.php
namespace App\DTOs\Transactional;

class ThresholdIndicatorDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $key,
        public readonly string $name,
        public readonly string $unit,
        public readonly string $operator,
        public readonly ?string $description,
        public readonly ?string $dynamicParamUnit,
        public readonly bool $isSystemCalculated,
    ) {}

    public static function fromRow(object $row): self
    {
        return new self(
            id:          $row->id,
            key:         $row->key,
            name:        $row->name,
            unit:        $row->unit,
            operator:    $row->operator,
            description: $row->description ?? null,
            dynamicParamUnit: $row->dynamic_param_unit ?? null,
            isSystemCalculated: (bool) $row->is_system_calculated,
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'key'         => $this->key,
            'name'        => $this->name,
            'unit'        => $this->unit,
            'operator'    => $this->operator,
            'description' => $this->description,
            'dynamic_param_unit' => $this->dynamicParamUnit,
            'is_system_calculated' => $this->isSystemCalculated,
        ];
    }
}