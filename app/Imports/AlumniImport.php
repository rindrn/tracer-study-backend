<?php

namespace App\Imports;

use App\Repositories\Transactional\AlumniProfileRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AlumniImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];
    private int $importedCount = 0;

    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
    ) {}

    public function collection(Collection $rows): void
    {
        $validRows = [];

        $programCodes = DB::connection('oltp')->table('programs')->pluck('id', 'code')->toArray();
        $existingNims = DB::connection('oltp')->table('alumni_profiles')->pluck('nim')->toArray();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // heading = row 1
            $data = [
                'nim'        => trim($row['nim'] ?? ''),
                'name'       => trim($row['nama'] ?? ''),
                'email'      => trim($row['email'] ?? ''),
                'phone'      => trim($row['telepon'] ?? ''),
                'graduation_year' => $row['tahun_lulus'] ?? null,
                'kode_prodi' => trim($row['kode_prodi'] ?? ''),
                'jurusan'    => trim($row['jurusan'] ?? ''),
                'status'     => trim($row['status'] ?? ''),
            ];

            $validator = Validator::make($data, [
                'nim'        => 'required|string|min:5',
                'name'       => 'required|string',
                'kode_prodi' => 'required|string',
                'email'      => 'nullable|email',
            ]);

            if ($validator->fails()) {
                $this->errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            if (!isset($programCodes[$data['kode_prodi']])) {
                $this->errors[] = "Baris {$rowNum}: Kode Prodi '{$data['kode_prodi']}' tidak valid.";
                continue;
            }

            if (in_array($data['nim'], $existingNims, true)) {
                $this->errors[] = "Baris {$rowNum}: NIM '{$data['nim']}' sudah terdaftar.";
                continue;
            }

            $existingNims[] = $data['nim'];
            $validRows[] = [
                'nim'             => $data['nim'],
                'name'            => $data['name'],
                'email'           => $data['email'] ?: null,
                'phone'           => $this->normalizePhone($data['phone']),
                'graduation_year' => $data['graduation_year'] ? (int) $data['graduation_year'] : null,
                'program_id'      => $programCodes[$data['kode_prodi']],
                'is_active'       => true,
            ];
        }

        if (!empty($validRows)) {
            $this->alumniRepo->bulkInsert($validRows);
            $this->importedCount = count($validRows);
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        if (str_starts_with($phone, '08')) {
            return '+62' . substr($phone, 1);
        }
        if (str_starts_with($phone, '62')) {
            return '+' . $phone;
        }
        if (str_starts_with($phone, '+62')) {
            return $phone;
        }
        return $phone;
    }
}
