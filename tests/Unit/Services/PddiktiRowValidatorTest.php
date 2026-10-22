<?php

namespace Tests\Unit\Services;

use App\Services\Transactional\PddiktiImport\PddiktiRowValidator;
use App\Services\Transactional\PddiktiImport\RegionLookup;
use Tests\TestCase;

/**
 * Aturan wajib impor PDDikti mengikuti lembar instrumen kementerian: hanya
 * "*(Wajib diisi)" merah yang wajib, catatan "(Jika f8 adalah …)" hanya
 * syarat tampil. Definisi pertanyaan di bawah meniru kuesioner umum di basis
 * data (tipe, opsi, skala, show_if).
 */
class PddiktiRowValidatorTest extends TestCase
{
    private function catalog(): array
    {
        $choice = fn (array $options, ?array $showIf = null) => ['type' => 'single_choice', 'options' => $options, 'show_if' => $showIf];
        $number = fn (?array $showIf = null) => ['type' => 'number', 'show_if' => $showIf];
        $text   = fn (?array $showIf = null) => ['type' => 'short_text', 'show_if' => $showIf];
        $likert = ['type' => 'number', 'scale' => [1, 5, ['Sangat Rendah', 'Rendah', 'Cukup', 'Tinggi', 'Sangat Tinggi']]];

        $catalog = [
            'f8'    => $choice(['1' => 'Bekerja (full time / part time)', '2' => 'Belum memungkinkan bekerja', '3' => 'Wiraswasta', '4' => 'Melanjutkan Pendidikan', '5' => 'Tidak kerja tetapi sedang mencari kerja']),
            'f502'  => $number(['f8' => [1, 3]]),
            'f505'  => $number(['f8' => [1, 3]]),
            'f5a1'  => $text(['f8' => [1, 3]]),
            'f5a2'  => $text(['f8' => [1, 3]]),
            'f1101' => $choice(['1' => 'Instansi pemerintah', '2' => 'Organisasi non-profit', '3' => 'Perusahaan swasta', '4' => 'Wiraswasta', '5' => 'Lainnya', '6' => 'BUMN/BUMD', '7' => 'Multilateral'], ['f8' => [1]]),
            'f1102' => $text(['f1101' => [5]]),
            'f5b'   => $text(['f8' => [1]]),
            'f5c'   => $choice(['1' => 'Founder', '2' => 'Co-Founder', '3' => 'Staff', '4' => 'Freelance'], ['f8' => [3]]),
            'f5d'   => $choice(['1' => 'Lokal', '2' => 'Nasional', '3' => 'Multinasional'], ['f8' => [1, 3]]),
            'f18a'  => $choice(['1' => 'Biaya Sendiri', '2' => 'Beasiswa', '3' => 'Lainnya', '4' => 'X'], ['f8' => [4]]),
            'f18b'  => $text(['f8' => [4]]),
            'f18c'  => $text(['f8' => [4]]),
            'f18d'  => ['type' => 'date', 'show_if' => ['f8' => [4]]],
            'f1201' => $choice(array_combine(['1', '2', '3', '4', '5', '6', '7'], ['Biaya Sendiri/Keluarga', 'ADIK', 'BIDIKMISI', 'PPA', 'AFIRMASI', 'Perusahaan/Swasta', 'Lainnya'])),
            'f1202' => $text(['f1201' => [7]]),
            'f14'   => $choice(['1' => 'Sangat Erat', '2' => 'Erat', '3' => 'Cukup Erat', '4' => 'Kurang Erat', '5' => 'Tidak Sama Sekali'], ['f8' => [1]]),
            'f15'   => $choice(['1' => 'Lebih Tinggi', '2' => 'Sama', '3' => 'Lebih Rendah', '4' => 'Tidak Perlu'], ['f8' => [1]]),
            'f301'  => $choice(['1' => 'Sebelum lulus', '2' => 'Sesudah lulus', '3' => 'Tidak mencari']),
            'f302'  => $number(['f301' => [1]]),
            'f303'  => $number(['f301' => [2]]),
            'f415'  => ['type' => 'boolean'],
            'f416'  => $text(['f415' => [1]]),
            'f401'  => ['type' => 'boolean'],
            'f6'    => $number(),
            'f1001' => $choice(['1' => 'Tidak', '2' => 'Menunggu', '3' => 'Ya, 2 minggu', '4' => 'Ya, belum pasti', '5' => 'Lainnya']),
            'f1002' => $text(['f1001' => [5]]),
            'f1613' => ['type' => 'boolean'],
            'f1614' => $text(['f1613' => [1]]),
        ];
        foreach (range(1761, 1774) as $n) {
            $catalog["f{$n}"] = $likert;
        }
        foreach (range(21, 27) as $n) {
            $catalog["f{$n}"] = ['type' => 'number', 'scale' => [1, 5, ['Sangat Besar', 'Besar', 'Cukup Besar', 'Kurang Besar', 'Tidak Sama Sekali']]];
        }

        return $catalog;
    }

    private function validator(): PddiktiRowValidator
    {
        $regions = new RegionLookup(
            [
                (object) ['id' => 7, 'code' => '10000', 'name' => 'Prov. D.K.I. Jakarta'],
                (object) ['id' => 8, 'code' => '20000', 'name' => 'Prov. Jawa Barat'],
            ],
            [
                (object) ['id' => 70, 'code' => '16000', 'name' => 'Kota Jakarta Pusat', 'province_code' => '10000'],
                (object) ['id' => 80, 'code' => '20800', 'name' => 'Kota Bandung', 'province_code' => '20000'],
            ],
        );

        return new PddiktiRowValidator($this->catalog(), $regions);
    }

    /** Baris minimal yang sah: alumni yang bekerja. */
    private function row(array $overrides = []): array
    {
        $row = ['f8' => '1', 'f502' => '3', 'f1201' => '1'];
        foreach (range(1761, 1774) as $n) {
            $row["f{$n}"] = '4';
        }

        return array_merge($row, $overrides);
    }

    private function errors(array $result): array
    {
        return array_values(array_map(
            fn ($i) => "{$i['column']}: {$i['message']}",
            array_filter($result['issues'], fn ($i) => $i['level'] === 'error'),
        ));
    }

    private function errorColumns(array $result): array
    {
        return array_column(array_filter($result['issues'], fn ($i) => $i['level'] === 'error'), 'column');
    }

    public function test_baris_bekerja_yang_lengkap_lolos_tanpa_galat(): void
    {
        $result = $this->validator()->validate($this->row());

        $this->assertSame([], $this->errors($result));
        $this->assertSame('1', $result['answers']['f8']);
    }

    public function test_f8_f1201_dan_seluruh_kompetensi_selalu_wajib(): void
    {
        $row = $this->row(['f8' => '', 'f1201' => '-', 'f1765' => null]);

        $result = $this->validator()->validate($row);

        $this->assertEqualsCanonicalizing(['f8', 'f1201', 'f1765'], $this->errorColumns($result));
    }

    public function test_f502_wajib_bagi_yang_bekerja_dengan_pesan_yang_menyebut_alasannya(): void
    {
        $result = $this->validator()->validate($this->row(['f502' => '']));

        $this->assertSame(['f502: Wajib diisi karena f8 = 1 (Bekerja (full time / part time)).'], $this->errors($result));
    }

    public function test_wiraswasta_wajib_f502_f5c_dan_f5d(): void
    {
        $result = $this->validator()->validate($this->row(['f8' => '3', 'f502' => '']));

        $this->assertEqualsCanonicalizing(['f502', 'f5c', 'f5d'], $this->errorColumns($result));
    }

    public function test_f5d_tidak_wajib_bagi_yang_bekerja(): void
    {
        $this->assertNotContains('f5d', $this->errorColumns($this->validator()->validate($this->row())));
    }

    public function test_lanjut_studi_wajib_f18a_sampai_f18d_dan_tidak_wajib_f502(): void
    {
        $result = $this->validator()->validate($this->row(['f8' => '4', 'f502' => '']));

        $this->assertEqualsCanonicalizing(['f18a', 'f18b', 'f18c', 'f18d'], $this->errorColumns($result));
    }

    public function test_f18d_menerima_berbagai_format_tanggal_dan_disimpan_y_m_d(): void
    {
        $base = ['f8' => '4', 'f502' => '', 'f18a' => '1', 'f18b' => 'ITB', 'f18c' => 'Informatika'];

        foreach (['30/08/2021', '2021-08-30', '44438'] as $input) {
            $result = $this->validator()->validate($this->row($base + ['f18d' => $input]));
            $this->assertSame([], $this->errors($result), $input);
            $this->assertSame('2021-08-30', $result['answers']['f18d'], $input);
        }

        $bad = $this->validator()->validate($this->row($base + ['f18d' => '31/02/2021']));
        $this->assertSame(['f18d'], $this->errorColumns($bad));
    }

    public function test_isian_lainnya_wajib_bila_pilihan_lainnya_dipilih(): void
    {
        $result = $this->validator()->validate($this->row([
            'f1101' => '5', 'f1201' => '7', 'f301' => '1', 'f415' => '1', 'f1001' => '5', 'f1613' => '1',
        ]));

        $this->assertEqualsCanonicalizing(['f1102', 'f1202', 'f302', 'f416', 'f1002', 'f1614'], $this->errorColumns($result));
    }

    public function test_f303_wajib_bila_mulai_mencari_sesudah_lulus(): void
    {
        $this->assertSame(['f303'], $this->errorColumns($this->validator()->validate($this->row(['f301' => '2']))));
    }

    public function test_pertanyaan_tanpa_tanda_wajib_merah_boleh_kosong(): void
    {
        // f14/f15 (asteris hitam), f21–f27, f301, f1001 wajib di formulir
        // online tetapi tidak di lembar kementerian.
        $result = $this->validator()->validate($this->row());

        foreach (['f14', 'f15', 'f21', 'f27', 'f301', 'f1001', 'f505', 'f5a1', 'f1101', 'f5b'] as $code) {
            $this->assertNotContains($code, $this->errorColumns($result), $code);
        }
    }

    public function test_pemicu_yang_tidak_relevan_tidak_mewajibkan_turunannya(): void
    {
        // f1101 hanya berlaku bagi yang bekerja. Pada lanjut studi f1101 = 5
        // dibuang, sehingga f1102 tidak ikut diwajibkan.
        $row = $this->row(['f8' => '4', 'f502' => '', 'f18a' => '1', 'f18b' => 'ITB', 'f18c' => 'TI', 'f18d' => '01/09/2024', 'f1101' => '5']);

        $result = $this->validator()->validate($row);

        $this->assertSame([], $this->errors($result));
        $this->assertArrayNotHasKey('f1101', $result['answers']);
    }

    public function test_isian_yang_tidak_berlaku_dibuang_dengan_peringatan(): void
    {
        $result = $this->validator()->validate($this->row(['f18b' => 'ITB']));

        $this->assertArrayNotHasKey('f18b', $result['answers']);
        $this->assertSame('warning', $result['issues'][0]['level']);
        $this->assertSame('f18b', $result['issues'][0]['column']);
    }

    public function test_nilai_di_luar_pilihan_dan_skala_adalah_galat_tanpa_galat_wajib_ganda(): void
    {
        $result = $this->validator()->validate($this->row(['f8' => '9', 'f1765' => '6']));

        $this->assertEqualsCanonicalizing(['f8', 'f1765'], $this->errorColumns($result));
    }

    public function test_format_label_hasil_ekspor_dibaca_kembali_sebagai_kode(): void
    {
        $result = $this->validator()->validate($this->row([
            'f8'    => 'Bekerja (full time / part time)',
            'f1761' => 'Sangat Tinggi',
            'f21'   => 'cukup besar',
            'f401'  => 'Ya',
            'f5a1'  => 'Prov. Jawa Barat',
            'f5a2'  => 'Kota Bandung',
        ]));

        $this->assertSame([], $this->errors($result));
        $this->assertSame(['1', '5', '3', '1', '8', '80'], [
            $result['answers']['f8'], $result['answers']['f1761'], $result['answers']['f21'],
            $result['answers']['f401'], $result['answers']['f5a1'], $result['answers']['f5a2'],
        ]);
    }

    public function test_kode_wilayah_dicocokkan_tanpa_nol_di_depan_dan_disimpan_sebagai_id(): void
    {
        $result = $this->validator()->validate($this->row(['f5a1' => '010000', 'f5a2' => '16000']));

        $this->assertSame(['7', '70'], [$result['answers']['f5a1'], $result['answers']['f5a2']]);
        $this->assertSame([], $result['issues']);
    }

    public function test_kota_di_luar_provinsinya_diberi_peringatan(): void
    {
        $result = $this->validator()->validate($this->row(['f5a1' => '10000', 'f5a2' => 'Kota Bandung']));

        $this->assertSame([], $this->errors($result));
        $this->assertSame([['level' => 'warning', 'column' => 'f5a2', 'message' => 'Kabupaten/kota tidak berada di provinsi yang diisi pada f5a1.']], $result['issues']);
    }

    public function test_wilayah_yang_tidak_dikenal_adalah_galat(): void
    {
        $this->assertSame(['f5a1'], $this->errorColumns($this->validator()->validate($this->row(['f5a1' => '999']))));
    }

    public function test_nol_penanda_tidak_diisi_diperlakukan_kosong_kecuali_pada_angka_biasa(): void
    {
        // Berkas PDDikti (dan ekspor data lama) menulis 0 untuk isian yang
        // tidak dijawab: f18d "0" muncul di ribuan baris.
        $result = $this->validator()->validate($this->row([
            'f502' => '0', 'f18a' => '0', 'f18d' => '0', 'f1101' => '0', 'f5b' => '0', 'f302' => '0', 'f401' => '0',
        ]));

        $this->assertSame([], $result['issues']);
        $this->assertSame('0', $result['answers']['f502'], 'masa tunggu 0 bulan bermakna');
        $this->assertSame('0', $result['answers']['f401'], 'centang 0 = tidak dipilih');
        foreach (['f18a', 'f18d', 'f1101', 'f5b', 'f302'] as $code) {
            $this->assertArrayNotHasKey($code, $result['answers'], $code);
        }
    }

    public function test_nol_pada_kolom_selalu_wajib_tetap_galat_wajib(): void
    {
        $this->assertSame(['f8: Wajib diisi.'], $this->errors($this->validator()->validate($this->row(['f8' => '0']))));
    }

    public function test_angka_dengan_pemisah_ribuan_dan_rupiah_dibersihkan(): void
    {
        foreach (['Rp 4.500.000' => '4500000', '4,500,000' => '4500000', '3.5' => '3.5', '12' => '12'] as $input => $expected) {
            $result = $this->validator()->validate($this->row(['f505' => $input]));
            $this->assertSame($expected, $result['answers']['f505'], $input);
        }

        $this->assertSame(['f6'], $this->errorColumns($this->validator()->validate($this->row(['f6' => 'banyak']))));
    }
}
