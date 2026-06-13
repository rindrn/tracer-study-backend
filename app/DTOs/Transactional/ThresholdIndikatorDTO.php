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
        ];
    }
}