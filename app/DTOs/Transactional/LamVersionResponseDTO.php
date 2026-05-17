<?php
// app/DTOs/Transactional/LamVersionResponseDTO.php
namespace App\DTOs\Transactional;

class LamVersionResponseDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $lamId,
        public readonly string $lamName,
        public readonly int    $year,
        public readonly ?string $versionName,
        public readonly bool   $isActive,
    ) {}

    public static function fromModel(object $row): self
    {
        return new self(
            id:          $row->id,
            lamId:       $row->lam_id,
            lamName:     $row->lam_name ?? '',
            year:        $row->year,
            versionName: $row->version_name,
            isActive:    (bool) $row->is_active,
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'lam_id'       => $this->lamId,
            'lam_name'     => $this->lamName,
            'year'         => $this->year,
            'version_name' => $this->versionName,
            'is_active'    => $this->isActive,
        ];
    }
}