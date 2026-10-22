<?php
namespace App\Services\Transactional\PddiktiImport;

use App\Events\AlumniDataChanged;
use App\Models\Transactional\PddiktiImport;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;
use App\Services\Transactional\TracerStudySubmitService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Impor jawaban tracer study berformat PDDikti, dua tahap:
 *
 *  validate() — membaca seluruh berkas dan menilai tiap baris TANPA menulis
 *               apa pun. Hasilnya (jumlah sah/galat/dilewati, galat per baris,
 *               sebaran per angkatan) disimpan di pddikti_imports untuk layar
 *               pratinjau.
 *  import()   — dijalankan setelah petugas mengonfirmasi. Berkas dibaca dan
 *               DINILAI ULANG, bukan memakai hasil validasi: di antara kedua
 *               tahap alumni bisa saja mengisi online, dan baris itu harus
 *               tetap dilewati.
 *
 * Aturan menulis mengikuti pengisian online (TracerStudySubmitService):
 * alumni di-upsert berdasarkan NIM, jawaban masuk ke kuesioner UMUM angkatan
 * itu (findActiveGlobalForYear, sama dengan ekspor), dan employment/
 * education records dinormalkan dengan fungsi yang sama.
 *
 * Bentrok: alumni yang sudah mengisi kuesioner itu SECARA ONLINE dilewati —
 * jawabannya dipertahankan. Hasil impor sebelumnya (source = 'import') boleh
 * diganti, sehingga unggah ulang berkas perbaikan memperbarui, bukan
 * menggandakan.
 */
class PddiktiImportService
{
    private const CONN = 'oltp';

    /** Batas jumlah galat/peringatan yang disimpan, supaya kolom jsonb tetap wajar. */
    private const MAX_ISSUES = 50000;

    /** Frekuensi menulis kemajuan ke basis data (setiap N baris). */
    private const PROGRESS_EVERY = 50;

    /** @var array<string,int> kode prodi (DIKTI maupun internal, huruf besar) => programs.id */
    private array $programs = [];
    /** @var array<string,object> nim => {id, program_id, graduation_year} */
    private array $alumniByNim = [];
    /** @var array<string,object{status:string,source:string}> "alumni_id:questionnaire_id" => respons */
    private array $responses = [];
    /** @var array<int,?object> tahun lulus => kuesioner umum aktif */
    private array $questionnaireByYear = [];
    /** @var array<int,PddiktiRowValidator> questionnaire_id => validator */
    private array $validators = [];
    private ?RegionLookup $regions = null;

    public function __construct(
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ResponseRepository $responseRepo,
        private readonly AlumniProfileRepository $alumniRepo,
        private readonly TracerStudySubmitService $submitService,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // Tahap 1 — validasi
    // ═══════════════════════════════════════════════════════════

    public function validate(PddiktiImport $import): void
    {
        $reader = $this->openReader($import);
        $this->preload();

        $import->update(['total_rows' => $reader->count(), 'processed_rows' => 0]);

        $issues  = [];
        $cohorts = [];
        $counts  = ['valid' => 0, 'error' => 0, 'skipped' => 0, 'warning' => 0];
        $seenNim = [];
        $done    = 0;

        foreach ($reader->rows() as $row) {
            $done++;
            if (PddiktiFileReader::isBlank($row)) {
                continue;
            }

            $result = $this->evaluate($row, $seenNim);
            $counts[$result['status']]++;
            $counts['warning'] += count(array_filter($result['issues'], fn ($i) => $i['level'] === 'warning'));

            foreach ($result['issues'] as $issue) {
                if (count($issues) < self::MAX_ISSUES) {
                    $issues[] = ['row' => $row['row'], 'nim' => $row['identity']['nim']] + $issue;
                }
            }

            if ($result['year'] !== null) {
                $key = (string) $result['year'];
                $cohorts[$key] ??= [
                    'year' => $result['year'],
                    'questionnaire' => $result['questionnaire']?->title,
                    'valid' => 0, 'skipped' => 0, 'error' => 0,
                ];
                $cohorts[$key][$result['status']]++;
            }

            if ($done % self::PROGRESS_EVERY === 0) {
                $import->update(['processed_rows' => $done]);
            }
        }

        ksort($cohorts);

        $import->update([
            'status'         => 'validated',
            'processed_rows' => $done,
            'valid_rows'     => $counts['valid'],
            'error_rows'     => $counts['error'],
            'skipped_rows'   => $counts['skipped'],
            'warning_count'  => $counts['warning'],
            'cohorts'        => array_values($cohorts),
            'issues'         => $issues,
            'validated_at'   => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Tahap 2 — impor
    // ═══════════════════════════════════════════════════════════

    public function import(PddiktiImport $import): void
    {
        $reader = $this->openReader($import);
        $this->preload();

        $import->update(['status' => 'importing', 'started_at' => now(), 'processed_rows' => 0, 'imported_rows' => 0]);

        $seenNim  = [];
        $touched  = [];
        $imported = 0;
        $skipped  = 0;
        $done     = 0;

        foreach ($reader->rows() as $row) {
            $done++;
            if (!PddiktiFileReader::isBlank($row)) {
                $result = $this->evaluate($row, $seenNim);

                if ($result['status'] === 'valid') {
                    if ($this->write($import->id, $result)) {
                        $imported++;
                        $touched["{$result['program_id']}:{$result['year']}"] = [$result['program_id'], $result['year']];
                    } else {
                        $skipped++;
                    }
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                }
            }

            if ($done % self::PROGRESS_EVERY === 0) {
                $import->update(['processed_rows' => $done, 'imported_rows' => $imported]);
            }
        }

        $import->update([
            'status'         => 'completed',
            'processed_rows' => $done,
            'imported_rows'  => $imported,
            'skipped_rows'   => $skipped,
            'finished_at'    => now(),
        ]);

        $this->announce($touched);
    }

    // ═══════════════════════════════════════════════════════════
    // Pembatalan
    // ═══════════════════════════════════════════════════════════

    /**
     * Menghapus seluruh jawaban yang dibawa satu impor, beserta alumni yang
     * DIBUAT impor itu dan tidak punya respons lain.
     *
     * Alumni yang sudah ada sebelum impor tidak dihapus; perubahan identitasnya
     * (nama, prodi, tahun lulus yang diperbarui berkas) juga tidak dikembalikan
     * — nilai lamanya tidak disimpan. Alumni hasil impor yang sempat mengisi
     * online tetap dipertahankan beserta pengisiannya.
     */
    public function rollback(PddiktiImport $import): void
    {
        $touched = [];

        DB::connection(self::CONN)->transaction(function () use ($import, &$touched) {
            $db = DB::connection(self::CONN);

            $rows = $db->table('responses as r')
                ->join('alumni_profiles as a', 'a.id', '=', 'r.alumni_id')
                ->where('r.import_batch_id', $import->id)
                ->get(['r.id', 'r.alumni_id', 'r.questionnaire_id', 'a.program_id', 'a.graduation_year']);

            foreach ($rows->groupBy('questionnaire_id') as $questionnaireId => $group) {
                $alumniIds = $group->pluck('alumni_id')->all();
                foreach (['employment_records', 'education_records'] as $table) {
                    $db->table($table)
                        ->where('questionnaire_id', $questionnaireId)
                        ->whereIn('alumni_id', $alumniIds)
                        ->delete();
                }
            }
            foreach ($rows as $r) {
                $touched["{$r->program_id}:{$r->graduation_year}"] = [(int) $r->program_id, (int) $r->graduation_year];
            }

            // response_answers ikut terhapus lewat cascade FK.
            $db->table('responses')->where('import_batch_id', $import->id)->delete();

            $db->table('alumni_profiles as a')
                ->where('a.import_batch_id', $import->id)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('responses as r')->whereColumn('r.alumni_id', 'a.id'))
                ->delete();

            $import->update(['status' => 'rolled_back', 'finished_at' => now()]);
        });

        $this->announce($touched);
    }

    // ═══════════════════════════════════════════════════════════
    // Penilaian satu baris
    // ═══════════════════════════════════════════════════════════

    /**
     * @param  array<string,int> $seenNim nim => nomor baris pertama (lintas pemanggilan)
     * @return array{status:string, issues:list<array>, answers:array, identity:array,
     *               program_id:?int, year:?int, questionnaire:?object, alumni:?object}
     */
    private function evaluate(array $row, array &$seenNim): array
    {
        $id     = $row['identity'];
        $issues = $this->identityIssues($id);

        $nim  = $id['nim'];
        $year = self::year($id['tahun_lulus']);

        if ($nim !== null && $nim !== '') {
            if (isset($seenNim[$nim])) {
                $issues[] = self::error('Nomor Mhs', "NIM sama dengan baris {$seenNim[$nim]}. Setiap alumni hanya boleh muncul sekali.");
            } else {
                $seenNim[$nim] = $row['row'];
            }
        }

        $programId = $this->programs[strtoupper(trim((string) $id['kode_prodi']))] ?? null;
        if ($id['kode_prodi'] !== null && $id['kode_prodi'] !== '' && $programId === null) {
            $issues[] = self::error('Kode Prodi', "Kode prodi '{$id['kode_prodi']}' tidak dikenali. Gunakan kode PDDikti atau kode prodi di Master Data.");
        }

        $questionnaire = $year !== null ? $this->questionnaireFor($year) : null;
        if ($year !== null && $questionnaire === null) {
            $issues[] = self::error('Tahun Lulus', "Belum ada kuesioner umum yang aktif untuk Lulusan {$year}. Buat atau terbitkan kuesionernya dulu.");
        }

        $alumni   = $nim !== null ? ($this->alumniByNim[$nim] ?? null) : null;
        $existing = $alumni !== null && $questionnaire !== null
            ? ($this->responses["{$alumni->id}:{$questionnaire->id}"] ?? null)
            : null;

        // Sudah mengisi online: baris ini dilewati apa pun isinya, jadi galat
        // isiannya tidak dilaporkan — hanya menambah derau di pratinjau.
        if ($existing !== null && $existing->source === 'online' && in_array($existing->status, ['submitted', 'verified'], true)) {
            return [
                'status' => 'skipped',
                'issues' => [['level' => 'skipped', 'column' => '-', 'message' => 'Dilewati: alumni sudah mengisi kuesioner ini secara online. Jawaban online dipertahankan.']],
                'answers' => [], 'identity' => $id, 'program_id' => $programId, 'year' => $year,
                'questionnaire' => $questionnaire, 'alumni' => $alumni,
            ];
        }

        $answers = [];
        if ($questionnaire !== null) {
            $checked = $this->validatorFor($questionnaire->id)->validate($row['answers']);
            $answers = $checked['answers'];
            array_push($issues, ...$checked['issues']);
        }

        $status = self::hasError($issues) ? 'error' : 'valid';

        if ($status === 'valid' && $alumni !== null) {
            if ($existing !== null && $existing->source === 'online') {
                // Draf autosave yang belum dikirim tidak dilindungi, tetapi
                // penggantiannya tidak bisa dibatalkan — petugas perlu tahu
                // sebelum mengonfirmasi.
                $issues[] = self::warning('-', 'Alumni punya draf online yang belum dikirim; draf itu akan digantikan data berkas dan tidak kembali bila impor dibatalkan.');
            }

            if ($status === 'valid' && ((int) $alumni->graduation_year !== $year || (int) $alumni->program_id !== $programId)) {
                $issues[] = self::warning('Tahun Lulus', 'Prodi atau tahun lulus berbeda dengan data alumni yang sudah terdaftar; data alumni akan diperbarui mengikuti berkas.');
            }
        }

        return [
            'status' => $status, 'issues' => $issues, 'answers' => $answers, 'identity' => $id,
            'program_id' => $programId, 'year' => $year, 'questionnaire' => $questionnaire, 'alumni' => $alumni,
        ];
    }

    /** @return list<array> */
    private function identityIssues(array $id): array
    {
        $validator = Validator::make($id, [
            'nim'         => ['required', 'string', 'max:30'],
            'nama'        => ['required', 'string', 'max:150'],
            'tahun_lulus' => ['required', 'regex:/^(19|20)\d{2}$/'],
            'kode_prodi'  => ['required', 'string'],
            'email'       => ['nullable', 'email', 'max:150'],
            'hp'          => ['nullable', 'string', 'max:30'],
            'kode_pt'     => ['nullable', 'string', 'max:10'],
            'nik'         => ['nullable', 'string', 'max:20'],
            'npwp'        => ['nullable', 'string', 'max:25'],
        ], [
            'required'          => 'Wajib diisi.',
            'max'               => 'Terlalu panjang (maksimal :max karakter).',
            'email'             => 'Format email tidak sah.',
            'tahun_lulus.regex' => 'Tahun lulus harus 4 digit, mis. 2024.',
        ]);

        $issues = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $issues[] = self::error(PddiktiRules::IDENTITY_LABELS[$field], $messages[0]);
        }

        return $issues;
    }

    // ═══════════════════════════════════════════════════════════
    // Menulis satu baris
    // ═══════════════════════════════════════════════════════════

    /** @return bool false bila baris ternyata dilewati saat ditulis */
    private function write(int $batchId, array $result): bool
    {
        $id            = $result['identity'];
        $questionnaire = $result['questionnaire'];

        return DB::connection(self::CONN)->transaction(function () use ($batchId, $result, $id, $questionnaire) {
            $alumniId = $this->upsertAlumni($batchId, $result);

            // Diperiksa ulang di dalam transaksi: pratinjau bisa saja sudah
            // usang bila alumni mengisi online di antara validasi dan impor.
            $existing = DB::connection(self::CONN)->table('responses')
                ->where('questionnaire_id', $questionnaire->id)
                ->where('alumni_id', $alumniId)
                ->lockForUpdate()
                ->first(['id', 'status', 'source']);

            if ($existing !== null) {
                if ($existing->source === 'online' && in_array($existing->status, ['submitted', 'verified'], true)) {
                    return false;
                }
                // Draf online yang belum dikirim, atau hasil impor sebelumnya.
                DB::connection(self::CONN)->table('responses')->where('id', $existing->id)->delete();
            }

            $responseId = $this->responseRepo->createImportedResponse($questionnaire->id, $alumniId, $batchId);
            $this->responseRepo->bulkInsertAnswers($responseId, array_map(
                fn ($code, $value) => ['question_code' => $code, 'answer_text' => $value],
                array_keys($result['answers']),
                $result['answers'],
            ));

            $this->submitService->persistStatusRecords($result['answers'], $alumniId, $questionnaire->id);

            $this->alumniByNim[$id['nim']] = (object) [
                'id' => $alumniId, 'program_id' => $result['program_id'], 'graduation_year' => $result['year'],
            ];

            return true;
        });
    }

    private function upsertAlumni(int $batchId, array $result): int
    {
        $id = $result['identity'];

        $data = [
            'name'            => $id['nama'],
            'program_id'      => $result['program_id'],
            'graduation_year' => $result['year'],
        ];
        // Isian opsional hanya ditulis bila ada: berkas yang tidak memuat
        // email tidak boleh menghapus email yang sudah terdaftar.
        $optional = [
            'email'   => $id['email'],
            'phone'   => PhoneNumber::normalize($id['hp']),
            'kode_pt' => $id['kode_pt'],
            'nik'     => $id['nik'],
            'npwp'    => $id['npwp'],
        ];
        foreach ($optional as $column => $value) {
            if ($value !== null && $value !== '' && $value !== '-') {
                $data[$column] = $value;
            }
        }

        if ($this->alumniRepo->findByNim($id['nim']) === null) {
            $data['import_batch_id'] = $batchId;
        }

        return $this->alumniRepo->upsertByNim($id['nim'], $data);
    }

    // ═══════════════════════════════════════════════════════════
    // Data pendukung
    // ═══════════════════════════════════════════════════════════

    private function openReader(PddiktiImport $import): PddiktiFileReader
    {
        return new PddiktiFileReader(Storage::disk('local')->path($import->file_path));
    }

    /**
     * Dimuat sekali per tahap, bukan per baris: berkas bisa berisi ribuan
     * alumni, dan dua kueri per baris membuat validasi berjalan menit-menitan.
     */
    private function preload(): void
    {
        $db = DB::connection(self::CONN);

        // Sama dengan AlumniImport: kode PDDikti dan kode internal sama-sama
        // diterima; kode internal ditulis belakangan sehingga menang bila bentrok.
        $programs = $db->table('programs')->get(['id', 'code', 'dikti_code']);
        foreach ($programs as $p) {
            if (trim((string) $p->dikti_code) !== '') {
                $this->programs[strtoupper(trim($p->dikti_code))] = (int) $p->id;
            }
        }
        foreach ($programs as $p) {
            $this->programs[strtoupper(trim($p->code))] = (int) $p->id;
        }

        $this->alumniByNim = $db->table('alumni_profiles')
            ->get(['id', 'nim', 'program_id', 'graduation_year'])
            ->keyBy('nim')
            ->all();

        $this->responses = [];
        $global = $db->table('questionnaires')->whereNull('program_id')->pluck('id');
        foreach ($db->table('responses')->whereIn('questionnaire_id', $global)->get(['alumni_id', 'questionnaire_id', 'status', 'source']) as $r) {
            $this->responses["{$r->alumni_id}:{$r->questionnaire_id}"] = $r;
        }

        $this->regions = new RegionLookup(
            $db->table('provinces')->get(['id', 'code', 'name']),
            $db->table('cities')->get(['id', 'code', 'name', 'province_code']),
        );
        $this->questionnaireByYear = [];
        $this->validators          = [];
    }

    private function questionnaireFor(int $year): ?object
    {
        if (!array_key_exists($year, $this->questionnaireByYear)) {
            $this->questionnaireByYear[$year] = $this->questionnaireRepo->findActiveGlobalForYear($year);
        }

        return $this->questionnaireByYear[$year];
    }

    private function validatorFor(int $questionnaireId): PddiktiRowValidator
    {
        return $this->validators[$questionnaireId] ??= new PddiktiRowValidator($this->catalog($questionnaireId), $this->regions);
    }

    /**
     * Definisi pertanyaan kuesioner untuk PddiktiRowValidator: tipe, opsi
     * aktif, skala, dan show_if — dari basis data, supaya nilai yang sah
     * selalu sama dengan formulir online.
     */
    private function catalog(int $questionnaireId): array
    {
        $db = DB::connection(self::CONN);

        $questions = $db->table('questionnaire_questions')
            ->where('questionnaire_id', $questionnaireId)
            ->get(['id', 'code', 'question_type', 'metadata']);

        $options = $db->table('questionnaire_options')
            ->whereIn('question_id', $questions->pluck('id'))
            ->where('is_active', true)
            ->orderBy('order_no')
            ->get(['question_id', 'option_code', 'option_label'])
            ->groupBy('question_id');

        $catalog = [];
        foreach ($questions as $q) {
            $meta = is_string($q->metadata) ? (json_decode($q->metadata, true) ?: []) : (array) ($q->metadata ?? []);
            $def  = ['type' => $q->question_type, 'show_if' => $meta['show_if'] ?? null];

            if ($q->question_type === 'single_choice') {
                $def['options'] = ($options[$q->id] ?? collect())->pluck('option_label', 'option_code')->all();
            }
            if ($q->question_type === 'number' && isset($meta['scale_min'], $meta['scale_max'])) {
                $def['scale'] = [(int) $meta['scale_min'], (int) $meta['scale_max'], $meta['scale_labels'] ?? []];
            }
            $catalog[$q->code] = $def;
        }

        return $catalog;
    }

    /** Hitung ulang ambang respons per (prodi, angkatan) — sekali, bukan per baris. */
    private function announce(array $touched): void
    {
        foreach ($touched as [$programId, $year]) {
            event(new AlumniDataChanged(programId: (int) $programId, graduatedYear: (int) $year));
        }
    }

    private static function year(?string $value): ?int
    {
        return $value !== null && preg_match('/^(19|20)\d{2}$/', trim($value)) ? (int) trim($value) : null;
    }

    private static function hasError(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue['level'] === 'error') {
                return true;
            }
        }

        return false;
    }

    private static function error(string $column, string $message): array
    {
        return ['level' => 'error', 'column' => $column, 'message' => $message];
    }

    private static function warning(string $column, string $message): array
    {
        return ['level' => 'warning', 'column' => $column, 'message' => $message];
    }
}
