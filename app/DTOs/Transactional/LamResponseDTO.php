<?php
// app/DTOs/Transactional/LamResponseDTO.php
namespace App\DTOs\Transactional;

class LamResponseDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string $createdAt,
    ) {}

    public static function fromModel(object $row): self
    {
        return new self(
            id:        $row->id,
            name:      $row->name,
            code:      $row->code,
            createdAt: $row->created_at,
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'created_at' => $this->createdAt,
        ];
    }
}