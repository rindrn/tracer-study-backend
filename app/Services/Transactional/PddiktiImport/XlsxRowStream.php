<?php
namespace App\Services\Transactional\PddiktiImport;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Membaca baris lembar .xlsx secara streaming (XMLReader), satu baris setiap
 * kali.
 *
 * PhpSpreadsheet memuat seluruh berkas ke memori: ekspor 1.912 alumni × 85
 * kolom sudah memakan ±106 MB dan 7 detik sebelum baris pertama bisa dinilai,
 * sehingga progress bar diam di 0% lalu melompat ke selesai. Di sini memori
 * hanya sebesar tabel shared string, dan baris pertama tersedia seketika.
 *
 * Yang didukung adalah yang ditulis Excel, LibreOffice, dan PhpSpreadsheet:
 * shared string, inline string, angka, boolean, dan string hasil rumus (nilai
 * tersimpan, rumusnya tidak dihitung ulang).
 */
final class XlsxRowStream
{
    private string $sheetEntry;
    /** @var list<string> */
    private array $sharedStrings = [];

    public function __construct(private readonly string $path, string $preferredSheet)
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas bukan .xlsx yang sah.');
        }

        try {
            $this->sheetEntry    = $this->locateSheet($zip, $preferredSheet);
            $this->sharedStrings = $this->readSharedStrings($zip);
        } finally {
            $zip->close();
        }
    }

    /** Berkas .xlsx = arsip zip berisi xl/workbook.xml. */
    public static function supports(string $path): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();

        return $ok;
    }

    /**
     * @return \Generator<int, list<string|null>> nomor baris Excel => sel (indeks kolom dari 0)
     */
    public function rows(): \Generator
    {
        $xml = new XMLReader();
        if (!$xml->open('zip://' . $this->path . '#' . $this->sheetEntry, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Lembar kerja tidak dapat dibaca.');
        }

        try {
            while ($xml->read()) {
                if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'row') {
                    $number = (int) $xml->getAttribute('r');
                    $cells  = $xml->isEmptyElement ? [] : $this->readRow($xml);
                    yield $number => $cells;
                }
            }
        } finally {
            $xml->close();
        }
    }

    /** @return list<string|null> */
    private function readRow(XMLReader $xml): array
    {
        $cells = [];
        $next  = 0;

        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'row') {
                break;
            }
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'c') {
                continue;
            }

            $ref   = $xml->getAttribute('r');
            $index = $ref !== null ? self::columnIndex($ref) : $next;
            $type  = $xml->getAttribute('t') ?? 'n';
            $value = $xml->isEmptyElement ? null : $this->readCellValue($xml, $type);

            $cells[$index] = $value;
            $next = $index + 1;
        }

        if ($cells === []) {
            return [];
        }
        $row = array_fill(0, max(array_keys($cells)) + 1, null);
        foreach ($cells as $i => $v) {
            $row[$i] = $v;
        }

        return $row;
    }

    private function readCellValue(XMLReader $xml, string $type): ?string
    {
        $raw = null;
        $inline = '';

        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'c') {
                break;
            }
            if ($xml->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($xml->localName === 'v') {
                $raw = $xml->readString();
            } elseif ($xml->localName === 't' && $type === 'inlineStr') {
                $inline .= $xml->readString();
            }
        }

        return match ($type) {
            's'         => $raw === null ? null : ($this->sharedStrings[(int) $raw] ?? null),
            'inlineStr' => $inline,
            'b'         => $raw === null ? null : ($raw === '1' ? '1' : '0'),
            default     => $raw,
        };
    }

    /** "AB12" → 27 (indeks dari 0). */
    private static function columnIndex(string $ref): int
    {
        $letters = strtoupper(preg_replace('/\d+/', '', $ref));
        $index   = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return $index - 1;
    }

    private function locateSheet(ZipArchive $zip, string $preferred): string
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels     = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($workbook === false || $rels === false) {
            throw new RuntimeException('Struktur workbook tidak dikenali.');
        }

        $targets = [];
        foreach ($rels->Relationship as $rel) {
            $targets[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $chosen = null;
        foreach ($workbook->sheets->sheet as $sheet) {
            $rid    = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = $targets[$rid] ?? null;
            if ($target === null) {
                continue;
            }
            $chosen ??= $target;
            if (strcasecmp((string) $sheet['name'], $preferred) === 0) {
                $chosen = $target;
                break;
            }
        }
        if ($chosen === null) {
            throw new RuntimeException('Berkas tidak memiliki lembar kerja.');
        }

        // Target relatif terhadap xl/ ("worksheets/sheet1.xml") atau absolut ("/xl/worksheets/…").
        return str_starts_with($chosen, '/') ? ltrim($chosen, '/') : 'xl/' . $chosen;
    }

    /** @return list<string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = new XMLReader();
        $xml->open('zip://' . $this->path . '#xl/sharedStrings.xml', null, LIBXML_NONET | LIBXML_COMPACT);

        $strings = [];
        $current = null;
        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si') {
                $current = '';
                if ($xml->isEmptyElement) {
                    $strings[] = '';
                    $current   = null;
                }
            } elseif ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 't' && $current !== null) {
                // Teks kaya (<r><t>…</t></r>) terdiri dari beberapa potongan.
                $current .= $xml->readString();
            } elseif ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'rPh') {
                // Petunjuk fonetik (Jepang) bukan bagian teks sel.
                $xml->next();
            } elseif ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'si') {
                $strings[] = $current ?? '';
                $current   = null;
            }
        }
        $xml->close();

        return $strings;
    }
}
