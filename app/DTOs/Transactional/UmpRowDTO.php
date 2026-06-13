<?php
// app/DTOs/Transactional/UmpRowDTO.php
//
// Representasi satu baris UMP (satu provinsi, satu tahun).
// Dipakai di semua response: list, preview BPS, dan hasil import.

namespace App\DTOs\Transactional;

class UmpRowDTO
{
    public function __construct(
        public readonly ?int    $id,            // null kalau belum tersimpan (preview)
        public readonly int     $tahun,
        public readonly int     $idProvinsi,
        public readonly string  $namaProvinsi,
        public readonly ?int    $nilaiUmp,      // null = belum ada data
        public readonly string  $sumber,        // BPS_API | IMPORT | MANUAL | GAGAL | KOSONG
        public readonly ?string $errorMsg,      // diisi kalau sumber = GAGAL
    ) {}

    public static function fromModel(object $row): self
    {
        return new self(
            id:           (int) $row->id,
            tahun:        (int) $row->tahun,
            idProvinsi:   (int) $row->province_id,
            namaProvinsi: $row->nama_provinsi,
            nilaiUmp:     $row->nilai_ump !== null ? (int) $row->nilai_ump : null,
            sumber:       $row->sumber,
            errorMsg:     null,
        );
    }

    /** Buat preview row (belum tersimpan, dari fetch BPS atau import) */
    public static function preview(
        int     $tahun,
        int     $idProvinsi,
        string  $namaProvinsi,
        ?int    $nilaiUmp,
        string  $sumber,
        ?string $errorMsg = null,
    ): self {
        return new self(
            id:           null,
            tahun:        $tahun,
            idProvinsi:   $idProvinsi,
            namaProvinsi: $namaProvinsi,
            nilaiUmp:     $nilaiUmp,
            sumber:       $sumber,
            errorMsg:     $errorMsg,
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'tahun'         => $this->tahun,
            'id_provinsi'   => $this->idProvinsi,
            'nama_provinsi' => $this->namaProvinsi,
            'nilai_ump'     => $this->nilaiUmp,
            'sumber'        => $this->sumber,
            'error_msg'     => $this->errorMsg,
        ];
    }
}