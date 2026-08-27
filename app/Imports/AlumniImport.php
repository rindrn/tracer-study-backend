<?php

namespace App\Imports;

use App\Repositories\Transactional\AlumniProfileRepository;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AlumniImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    private array $errors = [];
    private int $importedCount = 0;

    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
    ) {}

    /**
     * Hanya lembar pertama yang berisi data alumni.
     *
     * Tanpa pembatasan ini Laravel Excel memanggil collection() untuk SETIAP
     * lembar, termasuk "Referensi Kode Prodi" bawaan templat. Ke-34 baris
     * referensi itu lalu divalidasi sebagai baris alumni dan menghasilkan
     * puluhan galat "The nim field is required" yang tidak ada hubungannya
     * dengan berkas yang diunggah petugas — cukup untuk menenggelamkan galat
     * sungguhan di antaranya.
     *
     * Indeks, bukan nama: petugas kerap menyalin templat ke berkas baru dan
     * mengganti nama lembarnya, sementara urutannya tidak pernah berubah.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        $validRows = [];

        // Satu peta untuk DUA jenis kode. Kolom "Kode PDDIKTI/Prodi" di
        // templat menerima kode PDDIKTI (mis. 24301) maupun singkatan
        // internal kampus (mis. TKPB): berkas alumni datang dari dua arah --
        // salinan data PDDIKTI dan ekspor sistem ini sendiri -- dan memaksa
        // satu bentuk saja berarti salah satunya harus disalin ulang kolom
        // per kolom sebelum bisa diunggah.
        //
        // Kunci diseragamkan huruf besar supaya "tkpb" ikut dikenali. Kode
        // internal ditulis BELAKANGAN, jadi bila ada kode PDDIKTI milik prodi
        // lain yang kebetulan sama persis, kode internal yang menang.
        $programs = DB::connection('oltp')->table('programs')->select('id', 'code', 'dikti_code')->get();

        $programCodes = [];
        foreach ($programs as $p) {
            $dikti = strtoupper(trim((string) $p->dikti_code));
            if ($dikti !== '') {
                $programCodes[$dikti] = $p->id;
            }
        }
        foreach ($programs as $p) {
            $programCodes[strtoupper(trim((string) $p->code))] = $p->id;
        }
        $existingNims = DB::connection('oltp')->table('alumni_profiles')->pluck('nim')->toArray();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // heading = row 1

            // Baris yang seluruh selnya kosong bukan kesalahan petugas —
            // biasanya sisa baris berformat di bawah data. Melaporkannya
            // sebagai galat hanya menambah derau.
            if ($row->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
                continue;
            }

            // Judul kolom Email→Surel dan Telepon→No. HP berganti pada
            // penyeragaman DATA-03, dan WithHeadingRow menurunkan kunci
            // larik dari judul itu. Nama lama tetap diterima supaya berkas
            // templat yang sudah beredar tidak mendadak terbaca kosong --
            // kolom yang tidak dikenal tidak menghasilkan galat, hanya
            // nilai kosong, sehingga kerusakannya tidak kelihatan.
            $data = [
                'nim'        => trim($row['nim'] ?? ''),
                'name'       => trim($row['nama'] ?? ''),
                'email'      => trim($row['surel'] ?? $row['email'] ?? ''),
                'phone'      => trim($row['no_hp'] ?? $row['telepon'] ?? ''),
                'graduation_year' => $row['tahun_lulus'] ?? null,
                // Judul kolomnya kini "Kode PDDIKTI/Prodi" -- WithHeadingRow
                // menyulapnya jadi kunci 'kode_pddiktiprodi'. Nama lama tetap
                // dibaca supaya templat yang sudah beredar tidak mendadak
                // kehilangan kolom prodinya.
                'kode_prodi' => trim($row['kode_pddiktiprodi'] ?? $row['kode_prodi'] ?? ''),
                'kode_pt'    => trim($row['kode_pt'] ?? ''),
                'nik'        => trim($row['nik'] ?? ''),
                'npwp'       => trim($row['npwp'] ?? ''),
            ];

            // Panjang maksimum mengikuti kolom alumni_profiles dan aturan
            // yang sudah dipakai SubmitTracerStudyRequest. Format NIK/NPWP
            // sengaja tidak dipaksa 16 digit: 504 baris yang sudah ada
            // belum tentu patuh, dan menolak impor karenanya akan menahan
            // pekerjaan yang sah.
            $validator = Validator::make($data, [
                'nim'        => 'required|string|min:5',
                'name'       => 'required|string',
                'kode_prodi' => 'required|string',
                'email'      => 'nullable|email',
                'kode_pt'    => 'nullable|string|max:10',
                'nik'        => 'nullable|string|max:20',
                'npwp'       => 'nullable|string|max:25',
            ]);

            if ($validator->fails()) {
                $this->errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            $kodeProdi = strtoupper($data['kode_prodi']);
            if (!isset($programCodes[$kodeProdi])) {
                $this->errors[] = "Baris {$rowNum}: Kode PDDIKTI/Prodi '{$data['kode_prodi']}' tidak dikenali. "
                    . "Lihat lembar Referensi Kode Prodi di templat.";
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
                'program_id'      => $programCodes[$kodeProdi],
                'kode_pt'         => $data['kode_pt'] ?: null,
                'nik'             => $data['nik'] ?: null,
                'npwp'            => $data['npwp'] ?: null,
                // Kolom Status dihapus dari templat: alumni hasil impor
                // selalu aktif.
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

    /**
     * Aturannya dipindahkan ke App\Support\PhoneNumber supaya jalur impor,
     * jalur pengisian oleh alumni, dan jalur penyuntingan oleh staf memakai
     * definisi yang sama persis (DATA-09). Pembungkus ini dipertahankan agar
     * pemanggilan di dalam kelas ini tidak perlu berubah.
     */
    private function normalizePhone(?string $phone): ?string
    {
        return PhoneNumber::normalize($phone);
    }
}
