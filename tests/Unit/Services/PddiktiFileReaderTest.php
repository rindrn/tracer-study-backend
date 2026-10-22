<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Services\Transactional\PddiktiImport\PddiktiFileReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PddiktiFileReaderTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);
        parent::tearDown();
    }

    private function file(array $rows, string $sheetTitle = 'Data Kementrian'): string
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('Lembar Lain')->fromArray([['abaikan']]);
        $book->createSheet()->setTitle($sheetTitle)->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'pddikti') . '.xlsx';
        (new Xlsx($book))->save($path);

        return $this->files[] = $path;
    }

    private function requiredCodes(): array
    {
        return array_merge(['f8', 'f1201'], array_map(fn ($n) => "f{$n}", range(1761, 1774)));
    }

    public function test_judul_format_kode_dari_ekspor_dibaca_dari_lembar_data_kementrian(): void
    {
        $header = array_merge(['Kode PT', 'Kode Prodi', 'Nomor Mhs', 'Nama', 'Hp', 'Email', 'Tahun Lulus', 'NIK', 'NPWP'], $this->requiredCodes());
        $row    = array_merge(['-', '22403', 211511004, 'Budi', '-', '-', 2024, '-', '-'], ['1', '1'], array_fill(0, 14, '4'));

        $reader = new PddiktiFileReader($this->file([$header, $row]));
        $rows   = iterator_to_array($reader->rows());

        $this->assertSame(1, $reader->count());
        $this->assertSame(2, $rows[0]['row']);
        $this->assertSame('211511004', $rows[0]['identity']['nim'], 'NIM angka tidak boleh jadi 211511004.0');
        $this->assertSame('2024', $rows[0]['identity']['tahun_lulus']);
        $this->assertNull($rows[0]['identity']['email'], "'-' dari ekspor berarti kosong");
        $this->assertSame('1', $rows[0]['answers']['f8']);
    }

    public function test_judul_format_label_dan_nama_field_kementerian_ikut_dikenali(): void
    {
        $header = array_merge(
            ['kdpstmsmh', 'Nomor Mhs (nimhsmsmh)', 'Nama', 'Tahun Lulus', 'Kolom Tambahan'],
            ['Jelaskan status Anda saat ini? (f8)', 'Sumber dana (f1201)'],
            array_map(fn ($c) => "Kompetensi ({$c})", array_slice($this->requiredCodes(), 2)),
        );
        $row = array_merge(['TKG', 'X1', 'Ani', '2023', 'abaikan', 'Bekerja (full time / part time)', 'Biaya Sendiri/Keluarga'], array_fill(0, 14, 'Tinggi'));

        $rows = iterator_to_array((new PddiktiFileReader($this->file([$header, $row])))->rows());

        $this->assertSame('TKG', $rows[0]['identity']['kode_prodi']);
        $this->assertSame('X1', $rows[0]['identity']['nim']);
        $this->assertSame('Bekerja (full time / part time)', $rows[0]['answers']['f8']);
        $this->assertArrayNotHasKey('Kolom Tambahan', $rows[0]['answers']);
    }

    public function test_kolom_wajib_yang_tidak_ada_ditolak_sekali_untuk_seluruh_berkas(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Kolom wajib tidak ditemukan di baris judul: Tahun Lulus, f1201');

        $header = array_merge(['Kode Prodi', 'Nomor Mhs', 'Nama', 'f8'], array_slice($this->requiredCodes(), 2));
        new PddiktiFileReader($this->file([$header]));
    }

    public function test_baris_kosong_di_ekor_dibuang_dan_di_tengah_dikenali_kosong(): void
    {
        $header = array_merge(['Kode Prodi', 'Nomor Mhs', 'Nama', 'Tahun Lulus'], $this->requiredCodes());
        $data   = array_merge(['TKG', 'A1', 'Ani', '2024', '1', '1'], array_fill(0, 14, '4'));
        $blank  = array_fill(0, count($header), null);

        $reader = new PddiktiFileReader($this->file([$header, $data, $blank, $data, $blank, $blank]));
        $rows   = iterator_to_array($reader->rows());

        $this->assertSame(3, $reader->count());
        $this->assertTrue(PddiktiFileReader::isBlank($rows[1]));
        $this->assertSame(4, $rows[2]['row']);
    }
}
