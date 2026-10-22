<?php
namespace App\Services\Transactional\PddiktiImport;

/**
 * Aturan WAJIB impor PDDikti, disalin dari lembar instrumen kementerian
 * ("data pertanyaan dari kementrian.pdf").
 *
 * Hanya pertanyaan bertanda merah "*(Wajib diisi)" yang wajib. Catatan merah
 * miring "(Jika memilih Bekerja / Jika f8 adalah 1)" adalah syarat TAMPIL,
 * bukan syarat wajib — f505, f5a1, f1101, f5b misalnya tampil bagi yang
 * bekerja tetapi boleh kosong. f14 dan f15 bertanda asteris hitam tanpa tulisan
 * merah dan diperlakukan tidak wajib.
 *
 * Sengaja TIDAK membaca questionnaire_questions.is_required. Kolom itu
 * mengatur formulir online dan lebih ketat dari lembar kementerian (f14, f15,
 * f21–f27, f301, f1001 wajib di sana). Berkas impor berasal dari luar sistem
 * dan diukur dengan standar pelaporannya sendiri.
 */
final class PddiktiRules
{
    /**
     * question_code => syarat wajibnya. null = selalu wajib;
     * [kode => [nilai, ...]] = wajib bila kode itu bernilai salah satunya.
     *
     * @var array<string, array<string, int[]>|null>
     */
    public const REQUIRED = [
        'f8'    => null,
        'f1201' => null,
        'f1761' => null, 'f1762' => null, 'f1763' => null, 'f1764' => null,
        'f1765' => null, 'f1766' => null, 'f1767' => null, 'f1768' => null,
        'f1769' => null, 'f1770' => null, 'f1771' => null, 'f1772' => null,
        'f1773' => null, 'f1774' => null,

        'f502'  => ['f8' => [1, 3]],
        'f5c'   => ['f8' => [3]],
        // Tampil bagi yang bekerja maupun berwiraswasta, wajib hanya bagi
        // yang berwiraswasta: "(Jika f8 adalah 1) & *(Wajib diisi) (Jika f8 adalah 3)".
        'f5d'   => ['f8' => [3]],
        'f18a'  => ['f8' => [4]],
        'f18b'  => ['f8' => [4]],
        'f18c'  => ['f8' => [4]],
        'f18d'  => ['f8' => [4]],

        'f1102' => ['f1101' => [5]],
        'f1202' => ['f1201' => [7]],
        'f302'  => ['f301' => [1]],
        'f303'  => ['f301' => [2]],
        'f416'  => ['f415' => [1]],
        'f1002' => ['f1001' => [5]],
        'f1614' => ['f1613' => [1]],
    ];

    /**
     * Kolom identitas: kunci internal => judul kolom yang diterima. Judul di
     * berkas dicocokkan tanpa membedakan huruf besar, dan judul berbentuk
     * "Nama (nmmhsmsmh)" ikut dikenali lewat kode di dalam kurungnya.
     *
     * Baris pertama tiap daftar adalah judul yang ditulis ekspor "Data
     * Kementerian"; sisanya nama field instrumen kementerian dan sebutan umum.
     *
     * @var array<string, string[]>
     */
    public const IDENTITY_HEADERS = [
        'kode_pt'     => ['kode pt', 'kdptimsmh'],
        'kode_prodi'  => ['kode prodi', 'kdpstmsmh', 'kode pddikti/prodi'],
        'nim'         => ['nomor mhs', 'nimhsmsmh', 'nim'],
        'nama'        => ['nama', 'nmmhsmsmh'],
        'hp'          => ['hp', 'telpomsmh', 'nomor telepon/hp', 'no. hp'],
        'email'       => ['email', 'emailmsmh', 'alamat email', 'surel'],
        'tahun_lulus' => ['tahun lulus', 'tahun_lulus'],
        'nik'         => ['nik'],
        'npwp'        => ['npwp'],
    ];

    /** Identitas yang wajib: kunci mencocokkan alumni dan menentukan angkatannya. */
    public const REQUIRED_IDENTITY = ['nim', 'nama', 'tahun_lulus', 'kode_prodi'];

    /** Judul kolom yang ditampilkan di pesan galat. */
    public const IDENTITY_LABELS = [
        'kode_pt' => 'Kode PT', 'kode_prodi' => 'Kode Prodi', 'nim' => 'Nomor Mhs',
        'nama' => 'Nama', 'hp' => 'Hp', 'email' => 'Email', 'tahun_lulus' => 'Tahun Lulus',
        'nik' => 'NIK', 'npwp' => 'NPWP',
    ];

    /** Kolom yang menyimpan kunci baris tabel wilayah, bukan kode kuesioner. */
    public const PROVINCE_CODE = 'f5a1';
    public const CITY_CODE     = 'f5a2';
}
