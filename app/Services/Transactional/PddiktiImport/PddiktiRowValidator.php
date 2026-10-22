<?php
namespace App\Services\Transactional\PddiktiImport;

use Carbon\Carbon;

/**
 * Memeriksa dan menormalkan jawaban SATU baris berkas impor PDDikti.
 *
 * Murni (tanpa basis data): definisi pertanyaan dan tabel wilayah diberikan
 * pemanggil, sehingga setiap aturan bisa diuji sendiri-sendiri.
 *
 * Tiga langkah, berurutan (relevansi SEBELUM wajib — lihat validate()):
 *  1. Normalisasi — tiap isian diubah ke bentuk simpanan formulir online:
 *     kode opsi ("1"), angka, "1"/"0" untuk centang, tanggal Y-m-d, id
 *     wilayah. Berkas berformat label ("Bekerja", "Sangat Tinggi", "Ya",
 *     nama provinsi) diterima karena ekspor sistem ini sendiri bisa
 *     menghasilkannya. Isian yang tidak bisa dibaca = GALAT.
 *  2. Relevansi — isian yang syarat tampilnya (metadata show_if) tidak
 *     terpenuhi DIBUANG dengan PERINGATAN. Contoh: f18a diisi padahal f8 = 1.
 *     Formulir online tidak pernah menyimpan jawaban seperti itu, jadi impor
 *     pun tidak.
 *  3. Wajib — menurut PddiktiRules::REQUIRED, dinilai atas nilai yang tersisa.
 *     Kosong padahal wajib = GALAT.
 *
 * Definisi pertanyaan ($catalog) per question_code:
 *   type     single_choice | number | boolean | date | short_text | ...
 *   options  [option_code => option_label]          (single_choice)
 *   scale    [min, max, labels[]] | null             (number berskala)
 *   show_if  [kode => [nilai, ...]] | null
 */
final class PddiktiRowValidator
{
    /** @param array<string,array> $catalog */
    public function __construct(
        private readonly array $catalog,
        private readonly RegionLookup $regions,
    ) {}

    /**
     * @param  array<string,string|null> $raw question_code => isian mentah
     * @return array{answers: array<string,string>, issues: list<array{level:string,column:string,message:string}>}
     */
    public function validate(array $raw): array
    {
        $issues  = [];
        $answers = [];

        // 1. Normalisasi
        foreach ($raw as $code => $value) {
            $value = self::blankToNull($value);
            if ($value === null || !isset($this->catalog[$code]) || $this->isZeroPlaceholder($code, $value)) {
                continue;
            }

            $result = $this->normalize($code, $value);
            if ($result['error'] !== null) {
                $issues[] = self::error($code, $result['error']);
                continue;
            }
            $answers[$code] = $result['value'];
        }

        // 2. Relevansi — sebelum pemeriksaan wajib, supaya pemicu yang ikut
        // terbuang tidak lagi mewajibkan apa pun (f8 = 4 dengan f1101 = 5
        // tidak mewajibkan f1102). Diulang sampai stabil karena syaratnya
        // berantai: f1102 bergantung f1101, f1101 bergantung f8.
        do {
            $dropped = false;
            foreach ($answers as $code => $value) {
                $showIf = $this->catalog[$code]['show_if'] ?? null;
                if (!is_array($showIf) || $showIf === []) {
                    continue;
                }
                $trigger = array_key_first($showIf);
                if (!$this->conditionMet($answers, $trigger, (array) $showIf[$trigger])) {
                    unset($answers[$code]);
                    $dropped = true;
                    // Angka 0 di isian yang tidak berlaku adalah penanda
                    // "tidak diisi" berkas PDDikti, bukan salah isi.
                    if ($value !== '0') {
                        $issues[] = self::warning($code, "Diabaikan: hanya berlaku bila {$trigger} = " . $this->describeList($trigger, (array) $showIf[$trigger]) . '.');
                    }
                }
            }
        } while ($dropped);

        // Kota yang bukan bagian dari provinsinya: kemungkinan besar salah
        // ketik salah satunya. Dibiarkan masuk (keduanya sah sendiri-sendiri)
        // tetapi diberi peringatan supaya petugas bisa memeriksanya.
        if (isset($answers[PddiktiRules::PROVINCE_CODE], $answers[PddiktiRules::CITY_CODE])) {
            $provinceCode = $this->regions->provinceCode((int) $answers[PddiktiRules::PROVINCE_CODE]);
            $cityProvince = $this->regions->cityProvinceCode((int) $answers[PddiktiRules::CITY_CODE]);
            if ($provinceCode !== null && $cityProvince !== null && $provinceCode !== $cityProvince) {
                $issues[] = self::warning(PddiktiRules::CITY_CODE, 'Kabupaten/kota tidak berada di provinsi yang diisi pada f5a1.');
            }
        }

        // 3. Wajib
        foreach (PddiktiRules::REQUIRED as $code => $condition) {
            if (isset($answers[$code])) {
                continue;
            }
            // Isian yang gagal dinormalkan sudah dilaporkan; jangan dilaporkan
            // dua kali sebagai "kosong".
            if (self::hasErrorFor($issues, $code)) {
                continue;
            }
            if ($condition === null) {
                $issues[] = self::error($code, 'Wajib diisi.');
                continue;
            }
            [$trigger, $values] = [array_key_first($condition), reset($condition)];
            if ($this->conditionMet($answers, $trigger, $values)) {
                $issues[] = self::error($code, "Wajib diisi karena {$trigger} = {$this->describe($trigger, $answers[$trigger])}.");
            }
        }

        return ['answers' => $answers, 'issues' => $issues];
    }

    /** @return array{value:?string,error:?string} */
    private function normalize(string $code, string $value): array
    {
        if ($code === PddiktiRules::PROVINCE_CODE) {
            $id = $this->regions->province($value);

            return $id === null
                ? self::fail("Provinsi '{$value}' tidak dikenali. Gunakan kode atau nama dari master provinsi.")
                : self::ok((string) $id);
        }

        if ($code === PddiktiRules::CITY_CODE) {
            $city = $this->regions->city($value);

            return $city === null
                ? self::fail("Kabupaten/kota '{$value}' tidak dikenali. Gunakan kode atau nama dari master kabupaten/kota.")
                : self::ok((string) $city['id']);
        }

        $def = $this->catalog[$code];

        return match ($def['type']) {
            'single_choice' => $this->choice($def['options'] ?? [], $value),
            'boolean'       => self::boolean($value),
            'date'          => self::date($value),
            'number'        => isset($def['scale']) ? self::scale($def['scale'], $value) : self::number($value),
            default         => mb_strlen($value) > 500
                ? self::fail('Isian terlalu panjang (maksimal 500 karakter).')
                : self::ok($value),
        };
    }

    /** @param array<string,string> $options option_code => option_label */
    private function choice(array $options, string $value): array
    {
        $asCode = self::intLike($value);
        if (array_key_exists($asCode, $options)) {
            return self::ok((string) $asCode);
        }
        foreach ($options as $optionCode => $label) {
            if (mb_strtolower(trim($label)) === mb_strtolower($value)) {
                return self::ok((string) $optionCode);
            }
        }

        return self::fail("Nilai '{$value}' bukan pilihan yang sah (" . implode(', ', array_keys($options)) . ').');
    }

    /** @param array{0:int,1:int,2:string[]} $scale */
    private static function scale(array $scale, string $value): array
    {
        [$min, $max, $labels] = $scale;

        if (is_numeric($value) && (float) $value == (int) $value) {
            $n = (int) $value;

            return $n >= $min && $n <= $max
                ? self::ok((string) $n)
                : self::fail("Nilai '{$value}' di luar skala {$min}–{$max}.");
        }
        foreach ($labels as $i => $label) {
            if (mb_strtolower($label) === mb_strtolower($value)) {
                return self::ok((string) ($min + $i));
            }
        }

        return self::fail("Nilai '{$value}' bukan skala {$min}–{$max}.");
    }

    private static function number(string $value): array
    {
        $clean = preg_replace('/^rp\.?\s*/i', '', str_replace(' ', '', $value));
        // Pemisah ribuan ("4.500.000" / "4,500,000") dibuang; selain itu koma
        // dianggap desimal.
        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $clean)) {
            $clean = str_replace(['.', ','], '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        if (!is_numeric($clean) || (float) $clean < 0) {
            return self::fail("'{$value}' bukan angka yang sah.");
        }

        return self::ok(self::intLike($clean));
    }

    private static function boolean(string $value): array
    {
        return match (mb_strtolower($value)) {
            '1', 'ya', 'true', 'y'      => self::ok('1'),
            '0', 'tidak', 'false', 'n'  => self::ok('0'),
            default                     => self::fail("Nilai '{$value}' harus 0 atau 1."),
        };
    }

    private static function date(string $value): array
    {
        // Sel tanggal Excel terbaca sebagai nomor seri hari sejak 1899-12-30.
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return self::ok(Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d'));
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y-m-d H:i:s', 'd/m/y'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return self::ok($date->format('Y-m-d'));
            }
        }

        return self::fail("Tanggal '{$value}' tidak dikenali. Gunakan format dd/mm/yyyy.");
    }

    private function conditionMet(array $answers, string $trigger, array $values): bool
    {
        if (!isset($answers[$trigger])) {
            return false;
        }

        return in_array((int) $answers[$trigger], array_map('intval', $values), strict: true);
    }

    /** "1 (Bekerja)" untuk pilihan, "dipilih" untuk centang. */
    private function describe(string $code, string $value): string
    {
        $def = $this->catalog[$code] ?? [];
        if (($def['type'] ?? null) === 'boolean') {
            return $value === '1' ? '1 (dipilih)' : $value;
        }
        $label = $def['options'][$value] ?? null;

        return $label !== null ? "{$value} ({$label})" : $value;
    }

    private function describeList(string $code, array $values): string
    {
        return implode(' atau ', array_map(fn ($v) => $this->describe($code, (string) $v), $values));
    }

    /**
     * Berkas PDDikti (dan data lama hasil impornya) menulis 0 untuk isian
     * yang tidak dijawab — f18d bernilai "0" pada ribuan baris. Pada pertanyaan
     * yang 0-nya tidak bermakna (pilihan tanpa opsi 0, tanggal, skala mulai 1,
     * teks) nilai itu diperlakukan kosong. Angka biasa (masa tunggu 0 bulan)
     * dan centang (0 = tidak dipilih) tetap memakai 0 apa adanya.
     */
    private function isZeroPlaceholder(string $code, string $value): bool
    {
        if ($value !== '0' && $value !== '0.0') {
            return false;
        }
        $def = $this->catalog[$code];

        return match ($def['type']) {
            'single_choice' => !array_key_exists('0', $def['options'] ?? []),
            'number'        => isset($def['scale']) && $def['scale'][0] > 0,
            'boolean'       => false,
            // teks, tanggal, dan wilayah (f5a1/f5a2): 0 bukan isian yang sah
            default         => true,
        };
    }

    private static function intLike(string $value): string
    {
        return is_numeric($value) && (float) $value == (int) $value ? (string) (int) $value : $value;
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return $value === '' || $value === '-' ? null : $value;
    }

    private static function hasErrorFor(array $issues, string $code): bool
    {
        foreach ($issues as $issue) {
            if ($issue['level'] === 'error' && $issue['column'] === $code) {
                return true;
            }
        }

        return false;
    }

    private static function ok(string $value): array
    {
        return ['value' => $value, 'error' => null];
    }

    private static function fail(string $message): array
    {
        return ['value' => null, 'error' => $message];
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
