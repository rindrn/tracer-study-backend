<?php

namespace App\DTOs\Transactional;

class LamResponseDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $code,
        public readonly array  $programs,   
        public readonly string $createdAt,
    ) {}

    public static function fromRow(object $lam, array $programs = []): self
    {
        return new self(
            id:        $lam->id,
            name:      $lam->name,
            code:      $lam->code,
            programs:  $programs,
            createdAt: $lam->created_at,
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'programs'   => $this->programs,
            'created_at' => $this->createdAt,
        ];
    }
}