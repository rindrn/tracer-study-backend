<?php
namespace App\DTOs\Transactional;

use App\Models\Transactional\Program;

class ProgramResponseDTO
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly string  $code,
        /** Kode prodi versi PDDIKTI; null selama kampus belum mendatanya. */
        public readonly ?string $diktiCode,
        public readonly string  $degree,
        public readonly ?string $jurusan,
        public readonly ?string $accreditation,
        public readonly ?string $accreditedUntil,
        public readonly bool    $isActive,
        public readonly string  $createdAt,
    ) {}

    public static function fromModel(Program $model): self
    {
        return new self(
            id:        $model->id,
            name:      $model->name,
            code:      $model->code,
            diktiCode: $model->dikti_code,
            degree:    $model->degree,
            jurusan:   $model->jurusan,
            accreditation:   $model->accreditation,
            accreditedUntil: $model->accredited_until?->toDateString(),
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
            'dikti_code' => $this->diktiCode,
            'degree'     => $this->degree,
            'jurusan'    => $this->jurusan,
            'accreditation'    => $this->accreditation,
            'accredited_until' => $this->accreditedUntil,
            'is_active'  => $this->isActive,
            'created_at' => $this->createdAt,
        ];
    }
}
