<?php
namespace App\Services\Transactional\PddiktiImport;

use App\Exceptions\BusinessException;
use App\Exports\Sheets\MinistrySheetExport;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Membaca berkas impor PDDikti menjadi baris identitas + jawaban.
 *
 * Judul kolom yang dikenali:
 *   - identitas: lihat PddiktiRules::IDENTITY_HEADERS ("Nomor Mhs", "nimhsmsmh", …);
 *   - pertanyaan: kode telanjang ("f8") — ekspor format=code — atau teks
 *     berakhiran kode dalam kurung ("Jelaskan status Anda saat ini? (f8)") —
 *     ekspor format=label. Judul identitas berbentuk "Nama (nmmhsmsmh)" ikut
 *     dikenali lewat isi kurungnya.
 *
 * Kolom lain dibiarkan. Lembar "Data Kementrian" dipakai bila ada (berkas
 * ekspor bisa memuat lembar prodi juga), selain itu lembar pertama.
 *
 * Berkas .xlsx dibaca streaming (XlsxRowStream): memori kecil dan tetap,
 * baris pertama tersedia seketika sehingga progress bar bergerak sejak awal.
 * Berkas .xls lama memakai PhpSpreadsheet yang memuat semuanya ke memori.
 */
final class PddiktiFileReader
{
    private const SHEET_NAME = 'Data Kementrian';

    /** @var array<int,array{kind:string,key:string}> indeks kolom => pemetaan */
    private array $columns = [];
    /** Nomor baris Excel terakhir yang berisi data (baris kosong di ekor dibuang). */
    private int $lastRow = 1;
    private int $headerRow = 1;
    private ?XlsxRowStream $stream = null;
    /** @var array<int,list<mixed>>|null hanya untuk .xls */
    private ?array $legacyRows = null;

    public function __construct(string $path)
    {
        if (XlsxRowStream::supports($path)) {
            $this->stream = new XlsxRowStream($path, self::SHEET_NAME);
        } else {
            $this->legacyRows = self::loadLegacy($path);
        }

        // Lintasan pertama: judul dan baris data terakhir, untuk total progres.
        $header = null;
        foreach ($this->source() as $number => $cells) {
            if ($header === null) {
                $header          = $cells;
                $this->headerRow = $number;
                continue;
            }
            if (!self::allEmpty($cells)) {
                $this->lastRow = $number;
            }
        }
        $this->lastRow = max($this->lastRow, $this->headerRow);

        $this->mapHeader($header ?? []);
        $this->assertRequiredColumns();
    }

    /** Jumlah baris data (termasuk baris kosong di tengah), tanpa ekor kosong. */
    public function count(): int
    {
        return $this->lastRow - $this->headerRow;
    }

    /**
     * @return \Generator<int, array{row:int, identity:array<string,?string>, answers:array<string,?string>}>
     *         row = nomor baris di Excel
     */
    public function rows(): \Generator
    {
        $expected = $this->headerRow + 1;
        foreach ($this->source() as $number => $cells) {
            if ($number <= $this->headerRow) {
                continue;
            }
            if ($number > $this->lastRow) {
                break;
            }
            // Baris yang tidak tercatat sama sekali di berkas (benar-benar
            // kosong) tetap dihitung, supaya progres sampai 100%.
            for (; $expected < $number; $expected++) {
                yield $this->row($expected, []);
            }
            $expected = $number + 1;
            yield $this->row($number, $cells);
        }
    }

    /** Baris yang seluruh selnya kosong — sisa format di bawah data, bukan galat. */
    public static function isBlank(array $row): bool
    {
        foreach ([...$row['identity'], ...$row['answers']] as $value) {
            if ($value !== null && $value !== '' && $value !== '-') {
                return false;
            }
        }

        return true;
    }

    /** @return iterable<int, list<mixed>> nomor baris Excel => sel */
    private function source(): iterable
    {
        return $this->stream?->rows() ?? $this->legacyRows;
    }

    private function row(int $number, array $cells): array
    {
        $identity = array_fill_keys(array_keys(PddiktiRules::IDENTITY_HEADERS), null);
        $answers  = [];
        foreach ($this->columns as $index => $col) {
            $value = self::cellToString($cells[$index] ?? null);
            if ($col['kind'] === 'identity') {
                // Ekspor "Data Kementerian" menulis '-' untuk identitas
                // yang kosong (Hp, Email, NIK, NPWP).
                $identity[$col['key']] = $value === '' || $value === '-' ? null : $value;
            } else {
                $answers[$col['key']] = $value;
            }
        }

        return ['row' => $number, 'identity' => $identity, 'answers' => $answers];
    }

    /** @return array<int, list<mixed>> */
    private static function loadLegacy(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book  = $reader->load($path);
        $sheet = $book->getSheetByName(self::SHEET_NAME) ?? $book->getSheet(0);
        $rows  = [];
        foreach ($sheet->toArray(null, true, false, false) as $i => $cells) {
            $rows[$i + 1] = $cells;
        }
        $book->disconnectWorksheets();

        return $rows;
    }

    private function mapHeader(array $header): void
    {
        $aliases = [];
        foreach (PddiktiRules::IDENTITY_HEADERS as $key => $names) {
            foreach ($names as $name) {
                $aliases[$name] = $key;
            }
        }
        $questionCodes = array_flip(MinistrySheetExport::MINISTRY_QUESTION_CODES);

        foreach ($header as $index => $raw) {
            $title = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $raw)));
            if ($title === '') {
                continue;
            }
            $inner = preg_match('/\(([^()]+)\)\s*$/', $title, $m) ? trim($m[1]) : null;

            foreach ([$title, $inner] as $candidate) {
                if ($candidate === null) {
                    continue;
                }
                if (isset($aliases[$candidate])) {
                    $this->columns[$index] ??= ['kind' => 'identity', 'key' => $aliases[$candidate]];
                    break;
                }
                if (isset($questionCodes[$candidate])) {
                    $this->columns[$index] ??= ['kind' => 'question', 'key' => $candidate];
                    break;
                }
            }
        }
    }

    /**
     * Kolom identitas wajib dan kolom pertanyaan yang selalu wajib harus ADA
     * di judul. Tanpa ini setiap baris gagal dengan pesan "wajib diisi" yang
     * sama — ribuan galat untuk satu masalah: judul kolom salah.
     */
    private function assertRequiredColumns(): void
    {
        $present = [];
        foreach ($this->columns as $col) {
            $present[$col['key']] = true;
        }

        $missing = [];
        foreach (PddiktiRules::REQUIRED_IDENTITY as $key) {
            if (!isset($present[$key])) {
                $missing[] = PddiktiRules::IDENTITY_LABELS[$key];
            }
        }
        foreach (PddiktiRules::REQUIRED as $code => $condition) {
            if ($condition === null && !isset($present[$code])) {
                $missing[] = $code;
            }
        }

        if ($missing !== []) {
            throw new BusinessException(
                'Kolom wajib tidak ditemukan di baris judul: ' . implode(', ', $missing)
                . '. Gunakan templat impor atau hasil ekspor "Data Kementerian".',
                422,
            );
        }
    }

    private static function cellToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) && $value == (int) $value && abs($value) < PHP_INT_MAX) {
            // NIM/kode tersimpan sebagai angka di Excel: 211511004.0 → "211511004".
            return (string) (int) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $string = trim((string) $value);
        // Angka dari XML tersimpan apa adanya: "211511004", "4.5", kadang "2024.0".
        if (preg_match('/^-?\d+\.0+$/', $string)) {
            return preg_replace('/\.0+$/', '', $string);
        }

        return $string;
    }

    private static function allEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
