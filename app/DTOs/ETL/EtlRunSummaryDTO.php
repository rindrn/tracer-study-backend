<?php

namespace App\DTOs\ETL;

/**
 * DTO ringkasan eksekusi ETL satu snapshot penuh. Tidak menyimpan data
 * -- hanya angka untuk laporan/log.
 */
class EtlRunSummaryDTO
{
    /** @var array<int, array{stage: string, processed: int, inserted: int, skipped: int}> */
    private array $stages = [];

    public int $idWaktu = 0;

    public function addStage(string $stage, int $processed, int $inserted, int $skipped): void
    {
        $this->stages[] = [
            'stage'     => $stage,
            'processed' => $processed,
            'inserted'  => $inserted,
            'skipped'   => $skipped,
        ];
    }

    public function toTableRows(): array
    {
        return array_map(
            fn ($s) => [$s['stage'], $s['processed'], $s['inserted'], $s['skipped']],
            $this->stages
        );
    }

    public function toArray(): array
    {
        return ['id_waktu' => $this->idWaktu, 'stages' => $this->stages];
    }
}