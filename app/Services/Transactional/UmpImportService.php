<?php
// app/Services/Transactional/UmpImportService.php

namespace App\Services\Transactional;

use App\DTOs\Transactional\UmpRowDTO;
use App\Repositories\Transactional\RefUmpRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class UmpImportService
{
    public function __construct(
        private readonly RefUmpRepository $repo,
    ) {}

    public function parseFile(UploadedFile $file): array
    {
        // Raw array tanpa transformation — index 0 = header row
        $sheets  = Excel::toArray([], $file);
        $rawRows = $sheets[0] ?? [];

        if (empty($rawRows)) {
            return ['rows' => collect(), 'unrecognized' => [], 'tahun' => null];
        }

        // Baris pertama = header
        $headers = array_map(
            fn($h) => strtolower(trim((string) $h)),
            $rawRows[0]
        );

        // Validasi kolom wajib ada
        $required = ['tahun', 'nama_provinsi', 'nilai_ump'];
        foreach ($required as $col) {
            if (! in_array($col, $headers)) {
                throw new \RuntimeException("Kolom '{$col}' tidak ditemukan di file. Pastikan menggunakan template yang benar.");
            }
        }

        $provinceLookup = $this->buildProvinceLookup();

        $rows          = collect();
        $unrecognized  = [];
        $detectedTahun = null;

        // Skip baris header (index 0), iterasi dari index 1
        foreach (array_slice($rawRows, 1) as $rawRow) {
            $raw = array_combine($headers, $rawRow);

            $namaRaw  = trim((string) ($raw['nama_provinsi'] ?? ''));
            $nilaiRaw = $raw['nilai_ump'] ?? null;
            $tahunRaw = $raw['tahun'] ?? null;

            // Skip baris kosong
            if ($namaRaw === '' && ($nilaiRaw === null || $nilaiRaw === '')) {
                continue;
            }

            $tahun = $tahunRaw !== null && $tahunRaw !== '' ? (int) $tahunRaw : null;
            if ($tahun) {
                $detectedTahun = $tahun;
            }

            // Match ke master provinsi
            $province = $this->matchProvince($namaRaw, $provinceLookup);

            if (! $province) {
                $unrecognized[] = $namaRaw;
                Log::info("UmpImportService: nama provinsi tidak dikenali: \"{$namaRaw}\"");
                continue;
            }

            $nilaiUmp = $nilaiRaw !== null && $nilaiRaw !== ''
                ? (int) preg_replace('/[^\d]/', '', (string) $nilaiRaw)
                : null;

            $rows->push(UmpRowDTO::preview(
                tahun:        $tahun ?? 0,
                idProvinsi:   $province->id,
                namaProvinsi: $province->name,
                nilaiUmp:     $nilaiUmp ?: null,
                sumber:       'IMPORT',
            ));
        }

        return [
            'rows'         => $rows,
            'unrecognized' => array_unique($unrecognized),
            'tahun'        => $detectedTahun,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function buildProvinceLookup(): array
    {
        $lookup = [];
        foreach ($this->repo->allProvinces() as $p) {
            $lookup[$this->normalize($p->name)] = $p;
        }
        return $lookup;
    }

    private function matchProvince(string $raw, array $lookup): ?object
    {
        return $lookup[$this->normalize($raw)] ?? null;
    }

    private function normalize(string $name): string
    {
        $name = strtolower($name);
        $name = str_replace(['prov.', 'provinsi', 'd.k.i.', 'd.i.', '.'], '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }
}