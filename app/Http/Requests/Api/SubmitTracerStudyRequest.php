<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SubmitTracerStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            // Identitas (selalu wajib)
            'nim' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'tahun_lulus' => ['required'],
            // Kode Prodi dikirim lewat dropdown referensi, jadi nilainya wajib
            // ada di tabel programs. Ini juga menutup celah lama: kode ketikan
            // yang tidak dikenal dulu lolos dan baru ketahuan saat ekspor
            // Kemdikbud.
            'kdpstmsmh' => ['required', 'string', 'exists:programs,code'],
            'kode_pt' => ['nullable', 'string', 'max:10'],
            'nik' => ['nullable', 'string', 'max:20'],
            'npwp' => ['nullable', 'string', 'max:25'],
            'questionnaire_ids' => ['nullable', 'array'],
            'questionnaire_ids.*' => ['integer'],
        ];

        // Build dynamic rules from questionnaire questions
        $qIds = $this->input('questionnaire_ids', []);
        if (!empty($qIds)) {
            $dynamicRules = $this->buildDynamicRules($qIds);
            $rules = array_merge($rules, $dynamicRules);
        }

        return $rules;
    }

    /**
     * Pertanyaan milik kuesioner yang sedang dikirim, diambil sekali saja.
     *
     * rules() dan withValidator() sama-sama membutuhkannya, dan keduanya
     * dipanggil pada satu daur permintaan yang sama.
     */
    private ?\Illuminate\Support\Collection $questionCache = null;

    private function questions(): \Illuminate\Support\Collection
    {
        if ($this->questionCache !== null) {
            return $this->questionCache;
        }

        $qIds = $this->input('questionnaire_ids', []);

        return $this->questionCache = empty($qIds)
            ? collect()
            : DB::connection('oltp')->table('questionnaire_questions')
                ->whereIn('questionnaire_id', $qIds)
                ->select('code', 'question_type', 'is_required', 'metadata')
                ->get();
    }

    private function buildDynamicRules(array $questionnaireIds): array
    {
        $conn = DB::connection('oltp');

        $questions = $this->questions();

        // Get options keyed by question code
        $options = $conn->table('questionnaire_options as o')
            ->join('questionnaire_questions as q', 'o.question_id', '=', 'q.id')
            ->whereIn('q.questionnaire_id', $questionnaireIds)
            ->select('q.code as question_code', 'o.option_code')
            ->get()
            ->groupBy('question_code');

        $rules = [];
        $seen = [];
        // Identity fields already have hardcoded rules — skip them
        $identityKeys = ['nim', 'name', 'email', 'phone', 'tahun_lulus', 'kdpstmsmh', 'kode_pt', 'nik', 'npwp',
            'nimhsmsmh', 'kdptimsmh', 'nmmhsmsmh', 'telpomsmh', 'emailmsmh', 'questionnaire_ids'];

        foreach ($questions as $q) {
            if (isset($seen[$q->code]) || in_array($q->code, $identityKeys, true)) continue;
            $seen[$q->code] = true;

            // Wajib BERSYARAT tidak dinyatakan di sini. Pertanyaan seperti f5c
            // hanya wajib bila alumni memilih wiraswasta; menandainya `required`
            // akan menolak alumni yang memilih bekerja, padahal pertanyaan itu
            // tidak pernah muncul di layarnya. Aturannya ditegakkan
            // withValidator(), yang bisa membaca jawaban pemicunya.
            $rule = ($q->is_required && $this->requiredCondition($q->metadata) === null)
                ? ['required']
                : ['nullable'];

            // Isian lookup (f5a1/f5a2/kdpstmsmh) tetap bertipe short_text di
            // skema, tapi isinya kunci baris tabel referensi — bukan teks
            // bebas. Divalidasi dengan exists supaya kiriman yang tidak lewat
            // dropdown tidak menyelipkan wilayah karangan yang nanti gagal
            // dicocokkan ETL.
            $lookupRule = $this->lookupRule($q->metadata);
            if ($lookupRule !== null) {
                $rules[$q->code] = array_merge($rule, $lookupRule);
                continue;
            }

            switch ($q->question_type) {
                case 'single_choice':
                    $rule[] = 'string';
                    $validOptions = ($options[$q->code] ?? collect())->pluck('option_code')->toArray();
                    if (!empty($validOptions)) {
                        $rule[] = 'in:' . implode(',', $validOptions);
                    }
                    break;

                case 'multiple_choice':
                    // Can be array or string
                    $rule = ($q->is_required && $this->requiredCondition($q->metadata) === null)
                        ? ['required']
                        : ['nullable'];
                    break;

                case 'number':
                    $metadata = $q->metadata ? json_decode($q->metadata, true) : null;
                    $rule[] = 'numeric';
                    if ($metadata && isset($metadata['scale_min'], $metadata['scale_max'])) {
                        $rule[] = "between:{$metadata['scale_min']},{$metadata['scale_max']}";
                    }
                    break;

                case 'boolean':
                    $rule[] = 'in:0,1,true,false';
                    break;

                case 'short_text':
                    $rule[] = 'string';
                    $rule[] = 'max:500';
                    // Isian bertanda format tetap bertipe short_text di skema —
                    // penandanya ada di metadata, sama pola dengan `lookup` di
                    // atas. Disetel Tim Tracer lewat borang penyunting.
                    match ($this->formatOf($q->metadata)) {
                        'email' => $rule[] = 'email',
                        'url'   => $rule[] = 'url',
                        // Cerminan aturan di peramban: 9-15 angka, pemisah
                        // apa pun diabaikan.
                        'phone' => $rule[] = 'regex:/^\D*(\d\D*){9,15}$/',
                        default => null,
                    };
                    break;

                case 'long_text':
                    $rule[] = 'string';
                    $rule[] = 'max:5000';
                    break;

                case 'date':
                    $rule[] = 'date';
                    break;

                default:
                    $rule[] = 'string';
                    break;
            }

            $rules[$q->code] = $rule;
        }

        return $rules;
    }

    /**
     * Syarat yang membuat sebuah pertanyaan wajib diisi, atau null bila
     * kewajibannya tidak bersyarat.
     *
     * Bentuknya `['f8' => [1, 3]]` — baca: wajib bila jawaban f8 bernilai 1
     * atau 3. Beberapa kunci sekaligus berarti seluruhnya harus terpenuhi.
     *
     * Umumnya syarat wajib sama persis dengan syarat tampil, jadi `show_if`
     * dipakai apa adanya dan tidak perlu ditulis dua kali. Yang berbeda cukup
     * menuliskan `required_if` sendiri: f5d misalnya tampil bagi yang bekerja
     * maupun berwiraswasta, tetapi lembar kementerian hanya mewajibkannya bagi
     * yang berwiraswasta.
     */
    private function requiredCondition(?string $rawMetadata): ?array
    {
        $meta = $rawMetadata ? json_decode($rawMetadata, true) : null;
        if (!is_array($meta)) {
            return null;
        }

        $condition = $meta['required_if'] ?? $meta['show_if'] ?? null;

        return (is_array($condition) && $condition !== []) ? $condition : null;
    }

    /**
     * Apakah seluruh syarat pada $condition terpenuhi oleh jawaban yang masuk.
     *
     * Syarat atas pertanyaan yang tidak terjawab dianggap TIDAK terpenuhi.
     * Dengan begitu alumni yang mengosongkan pemicunya tidak ikut dituntut
     * mengisi turunannya — kekosongan pemicu sudah dipersoalkan oleh aturan
     * `required` milik pemicu itu sendiri.
     */
    private function conditionMet(array $condition): bool
    {
        foreach ($condition as $depCode => $allowed) {
            $value = $this->answerFor((string) $depCode);
            if ($value === null) {
                return false;
            }

            $allowed = array_map('strval', (array) $allowed);
            $given   = array_map('strval', is_array($value) ? $value : [$value]);

            if (array_intersect($given, $allowed) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Jawaban atas satu kode pertanyaan, termasuk yang datang terbungkus
     * kelompok checkbox.
     *
     * f416 wajib diisi bila alumni mencentang f415, tetapi f415 tidak pernah
     * tiba sebagai kunci tersendiri: peramban mengirim seluruh kelompoknya
     * sebagai satu larik di bawah kode kelompok (`q16_cara_cari_kerja`), dan
     * pemekarannya menjadi 0/1 per kode baru dikerjakan
     * TracerStudySubmitService — sesudah validasi. Keanggotaan larik itulah
     * yang dibaca di sini, sehingga syaratnya tetap dapat dinilai tanpa
     * mengubah bentuk masukan.
     */
    private function answerFor(string $code): mixed
    {
        $value = $this->input($code);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $group = $this->groupCodeOf($code);
        if ($group === null) {
            return null;
        }

        $selected = $this->input($group);

        return is_array($selected)
            ? (in_array($code, array_map('strval', $selected), true) ? '1' : '0')
            : null;
    }

    /** Kode kelompok checkbox yang menaungi sebuah kode pertanyaan, bila ada. */
    private function groupCodeOf(string $code): ?string
    {
        $meta = $this->questions()->firstWhere('code', $code)?->metadata;
        $meta = $meta ? json_decode($meta, true) : null;

        return is_array($meta) && is_string($meta['group_code'] ?? null)
            ? $meta['group_code']
            : null;
    }

    /** Format isian yang ditandai metadata pertanyaan (email, phone, url). */
    private function formatOf(?string $metadata): ?string
    {
        if (!$metadata) return null;
        $meta = json_decode($metadata, true);

        return is_array($meta) ? ($meta['format'] ?? null) : null;
    }

    /** Tabel referensi yang boleh dirujuk metadata `lookup`. */
    private const LOOKUP_TABLES = [
        'province' => 'provinces',
        'city'     => 'cities',
        'program'  => 'programs',
    ];

    /**
     * Aturan validasi untuk isian lookup, atau null bila pertanyaan ini bukan
     * lookup. Kolom yang dirujuk mengikuti `lookup_value` — default `id`
     * (dipakai wilayah), sedangkan Kode Prodi menyimpan `code`.
     */
    private function lookupRule(?string $rawMetadata): ?array
    {
        $meta   = $rawMetadata ? json_decode($rawMetadata, true) : null;
        $source = $meta['lookup'] ?? null;

        if (!is_string($source) || !isset(self::LOOKUP_TABLES[$source])) {
            return null;
        }

        $column = ($meta['lookup_value'] ?? 'id') === 'code' ? 'code' : 'id';
        $table  = self::LOOKUP_TABLES[$source];

        // `bail` + pemeriksaan bentuk WAJIB mendahului `exists`. Kolom id
        // bertipe bigint di PostgreSQL: membandingkannya dengan teks bebas
        // ("Jawa Barat") membuat query exists gagal di tingkat basis data dan
        // permintaan berakhir 500, bukan 422 yang bisa dibaca alumni.
        return $column === 'code'
            ? ['bail', 'string', 'exists:' . $table . ',code']
            : ['bail', 'integer', 'exists:' . $table . ',id'];
    }

    /**
     * Pesan galat berbahasa Indonesia.
     *
     * Sejak frontend menampilkan galat dari server apa adanya di bawah tiap
     * pertanyaan dan di dalam toast, pesan bawaan Laravel yang berbahasa
     * Inggris dan menyebut kode mentah ("The selected f5c is invalid") tidak
     * layak dibaca alumni. Kode pertanyaan diganti label yang bermakna lewat
     * attributes().
     */
    public function messages(): array
    {
        return [
            'required' => 'Pertanyaan :attribute wajib diisi.',
            'numeric'  => 'Jawaban :attribute harus berupa angka, tanpa titik atau koma.',
            'in'       => 'Pilihan pada :attribute tidak dikenali. Silakan pilih salah satu opsi yang tersedia.',
            'date'     => 'Tanggal pada :attribute tidak valid.',
            'email'    => 'Format email tidak valid. Contoh: nama@email.com',
            'max'      => 'Jawaban :attribute terlalu panjang.',
            'between'  => 'Nilai :attribute harus berada di antara :min sampai :max.',
            'exists'   => 'Pilihan pada :attribute tidak ada dalam daftar referensi. Silakan pilih dari daftar.',
            'integer'  => 'Jawaban :attribute harus dipilih dari daftar yang tersedia.',
        ];
    }

    /**
     * Ganti kode pertanyaan dengan teks pertanyaannya, supaya pesan galat
     * menyebut hal yang dikenali alumni alih-alih kode internal seperti f505.
     */
    public function attributes(): array
    {
        $qIds = $this->input('questionnaire_ids', []);
        if (empty($qIds)) {
            return [];
        }

        return DB::connection('oltp')->table('questionnaire_questions')
            ->whereIn('questionnaire_id', $qIds)
            ->pluck('question_text', 'code')
            ->map(fn ($text) => \Illuminate\Support\Str::limit(strip_tags((string) $text), 70))
            ->all();
    }

    /**
     * Validasi silang antar-pertanyaan — tidak bisa dinyatakan lewat rules()
     * biasa karena membandingkan beberapa jawaban sekaligus.
     *
     * Corong pencarian kerja secara logis harus mengecil:
     *   f6  = jumlah perusahaan yang dilamar
     *   f7  = jumlah yang merespons        (tidak mungkin > f6)
     *   f7a = jumlah yang mengundang wawancara (tidak mungkin > f7)
     *
     * Ketidakkonsistenan semacam ini lolos dari aturan `numeric`, padahal
     * merusak analisis efektivitas pencarian kerja di lapisan analitik.
     * Perbandingan hanya dilakukan bila kedua nilainya benar-benar diisi.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            $this->validateConditionallyRequired($v);
        });

        $validator->after(function (\Illuminate\Validation\Validator $v) {
            $numeric = function (string $code): ?float {
                $value = $this->input($code);
                return ($value === null || $value === '' || !is_numeric($value))
                    ? null
                    : (float) $value;
            };

            $applied   = $numeric('f6');
            $responded = $numeric('f7');
            $interviewed = $numeric('f7a');

            if ($applied !== null && $responded !== null && $responded > $applied) {
                $v->errors()->add('f7', sprintf(
                    'Jumlah perusahaan yang merespons (%s) tidak boleh lebih banyak daripada jumlah lamaran yang dikirim (%s).',
                    (int) $responded, (int) $applied,
                ));
            }

            if ($responded !== null && $interviewed !== null && $interviewed > $responded) {
                $v->errors()->add('f7a', sprintf(
                    'Jumlah undangan wawancara (%s) tidak boleh lebih banyak daripada jumlah perusahaan yang merespons (%s).',
                    (int) $interviewed, (int) $responded,
                ));
            }

            // Tetap diperiksa walau f7 kosong, supaya f7a tidak bisa melebihi f6.
            if ($responded === null && $applied !== null && $interviewed !== null && $interviewed > $applied) {
                $v->errors()->add('f7a', sprintf(
                    'Jumlah undangan wawancara (%s) tidak boleh lebih banyak daripada jumlah lamaran yang dikirim (%s).',
                    (int) $interviewed, (int) $applied,
                ));
            }

            // Kontak penilai selalu berpasangan nama + surel. Separuh pasangan
            // akan dilewati TracerStudySubmitService saat menyimpan ke
            // stakeholder_contacts, jadi tanpa pemeriksaan ini isian alumni
            // hilang tanpa satu pun pemberitahuan.
            foreach ([1, 2, 3] as $no) {
                $name  = trim((string) $this->input("stk{$no}_nama", ''));
                $email = trim((string) $this->input("stk{$no}_email", ''));

                if ($name !== '' && $email === '') {
                    $v->errors()->add("stk{$no}_email", 'Surel penilai wajib diisi bila namanya sudah dituliskan.');
                } elseif ($email !== '' && $name === '') {
                    $v->errors()->add("stk{$no}_nama", 'Nama penilai wajib diisi bila surelnya sudah dituliskan.');
                }
            }
        });
    }

    /**
     * Tegakkan kewajiban bersyarat — pasangan dari requiredCondition().
     *
     * Lembar kementerian mewajibkan sejumlah pertanyaan hanya pada cabang
     * jawaban tertentu: f502 bagi yang bekerja atau berwiraswasta, f18a-f18d
     * bagi yang melanjutkan pendidikan, f1202 bagi yang memilih sumber dana
     * lainnya. Berkas ekspor yang mengosongkannya ditolak portal pelaporan,
     * sementara menandainya wajib tanpa syarat akan menolak alumni yang
     * cabangnya berbeda — pertanyaannya memang tidak pernah muncul di layar
     * mereka. Keduanya dihindari dengan menilai syaratnya lebih dulu.
     *
     * Peramban sudah menerapkan aturan yang sama: checkSection() melewati
     * pertanyaan yang tersembunyi sebelum memeriksa `required`. Pemeriksaan di
     * sini menutup jalur yang tidak lewat peramban.
     */
    private function validateConditionallyRequired(\Illuminate\Validation\Validator $v): void
    {
        foreach ($this->questions() as $q) {
            if (!$q->is_required) {
                continue;
            }

            $condition = $this->requiredCondition($q->metadata);
            if ($condition === null || !$this->conditionMet($condition)) {
                continue;
            }

            $answer = $this->input($q->code);
            $empty  = $answer === null
                || $answer === ''
                || (is_array($answer) && $answer === []);

            if ($empty && !$v->errors()->has($q->code)) {
                $v->errors()->add($q->code, 'Pertanyaan ini wajib diisi.');
            }
        }
    }

    /**
     * Return all input so dynamic question codes pass through to the service.
     */
    public function validated($key = null, $default = null)
    {
        if ($key) {
            return data_get($this->all(), $key, $default);
        }
        return $this->all();
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        \Log::warning('[TracerStudy] Validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input_keys' => array_keys($this->all()),
        ]);

        parent::failedValidation($validator);
    }
}
