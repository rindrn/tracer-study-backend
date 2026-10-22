<?php
namespace App\Services\Transactional\PddiktiImport;

use App\Exports\Sheets\MinistrySheetExport;
use App\Models\Transactional\PddiktiImport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas yang diunduh petugas di sekitar impor PDDikti.
 */
final class PddiktiImportFiles
{
    /** Judul kolom identitas templat — sama dengan ekspor "Data Kementerian". */
    private const IDENTITY_TITLES = ['Kode PT', 'Kode Prodi', 'Nomor Mhs', 'Nama', 'Hp', 'Email', 'Tahun Lulus', 'NIK', 'NPWP'];

    /**
     * Templat kosong: judul kolom persis seperti ekspor format kode, ditambah
     * lembar petunjuk aturan wajib. Hasil ekspor sendiri juga bisa langsung
     * dipakai sebagai berkas impor.
     */
    public static function template(): string
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Data Kementrian');
        $sheet->fromArray([array_merge(self::IDENTITY_TITLES, MinistrySheetExport::MINISTRY_QUESTION_CODES)]);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $help = $book->createSheet()->setTitle('Petunjuk');
        $lines = [
            ['Petunjuk impor PDDikti'],
            [''],
            ['Kolom identitas wajib: Nomor Mhs, Nama, Tahun Lulus, Kode Prodi (kode PDDikti atau kode prodi di Master Data).'],
            ['Nilai jawaban berupa kode instrumen kementerian (mis. f8 = 1 untuk Bekerja). Hasil ekspor format teks juga diterima.'],
            ['f5a1 / f5a2: kode atau nama provinsi dan kabupaten/kota dari master kementerian.'],
            ['f18d: tanggal dd/mm/yyyy.'],
            [''],
            ['Selalu wajib: f8, f1201, f1761–f1774.'],
            ['Wajib bersyarat:'],
        ];
        foreach (PddiktiRules::REQUIRED as $code => $condition) {
            if ($condition !== null) {
                $trigger  = array_key_first($condition);
                $lines[] = ["  {$code} — bila {$trigger} = " . implode(' atau ', $condition[$trigger])];
            }
        }
        $lines[] = [''];
        $lines[] = ['Alumni yang sudah mengisi kuesioner secara online dilewati; jawaban online-nya dipertahankan.'];
        $help->fromArray($lines);
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $help->getColumnDimension('A')->setWidth(110);

        $book->setActiveSheetIndex(0);

        return self::save($book, 'templat-impor-pddikti');
    }

    /**
     * Baris yang GAGAL dari berkas asli, utuh dengan seluruh kolomnya, plus
     * kolom "Keterangan Galat" di ujung. Petugas memperbaikinya lalu
     * mengunggah berkas ini lagi — kolom keterangan diabaikan pembaca impor.
     */
    public static function errors(PddiktiImport $import): string
    {
        $messages = [];
        foreach ($import->issues ?? [] as $issue) {
            if ($issue['level'] === 'error') {
                $messages[$issue['row']][] = "{$issue['column']}: {$issue['message']}";
            }
        }

        // Hanya judul dan baris galat yang diambil dari berkas asli.
        $data = [];
        foreach (self::sourceRows(Storage::disk('local')->path($import->file_path)) as $number => $cells) {
            if ($data === [] || isset($messages[$number])) {
                $data[$number] = $cells;
            }
        }

        $header   = reset($data) ?: [];
        $noteCol  = count($header) + 1;
        $book     = new Spreadsheet();
        $out      = $book->getActiveSheet()->setTitle('Data Kementrian');

        $write = function (int $row, array $cells) use ($out) {
            foreach ($cells as $i => $value) {
                if ($value !== null && $value !== '') {
                    $out->setCellValueExplicit([$i + 1, $row], (string) $value, DataType::TYPE_STRING);
                }
            }
        };

        $write(1, $header);
        $out->setCellValue([$noteCol, 1], 'Keterangan Galat');
        $out->getStyle('1:1')->getFont()->setBold(true);

        $target = 2;
        foreach ($messages as $rowNumber => $notes) {
            $write($target, $data[$rowNumber] ?? []);
            $out->setCellValue([$noteCol, $target], implode("\n", $notes));
            $target++;
        }

        $noteLetter = Coordinate::stringFromColumnIndex($noteCol);
        $out->getColumnDimension($noteLetter)->setWidth(70);
        $out->getStyle("{$noteLetter}:{$noteLetter}")->getAlignment()->setWrapText(true);
        $out->freezePane('A2');

        return self::save($book, "galat-impor-{$import->id}");
    }

    /** @return iterable<int, list<mixed>> nomor baris Excel => sel */
    private static function sourceRows(string $path): iterable
    {
        if (XlsxRowStream::supports($path)) {
            return (new XlsxRowStream($path, 'Data Kementrian'))->rows();
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book  = $reader->load($path);
        $sheet = $book->getSheetByName('Data Kementrian') ?? $book->getSheet(0);
        $rows  = [];
        foreach ($sheet->toArray(null, true, false, false) as $i => $cells) {
            $rows[$i + 1] = $cells;
        }
        $book->disconnectWorksheets();

        return $rows;
    }

    private static function save(Spreadsheet $book, string $name): string
    {
        $path = storage_path('app/' . $name . '-' . uniqid() . '.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
