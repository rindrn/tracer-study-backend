<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Isi peringkat akreditasi awal tiap prodi (programs.accreditation +
 * accredited_until).
 *
 * SUMBER: halaman resmi akreditasi P2MPP Polban
 * (p2mpp.polban.ac.id/spme/akreditasi-program-studi-tingkat-nasional) — tiap
 * baris di sana menautkan PDF SK dan periode berlakunya. Nilai di bawah =
 * baris TERBARU yang aktif per prodi menurut halaman itu.
 *
 * OVERRIDE (SK lebih baru yang belum tercatat di P2MPP, sudah diverifikasi):
 *   - id 23 D-4 Teknik Informatika -> Unggul, LAM INFOKOM, 15 Agu 2025–2030
 *   - id 38 S-2 Rekayasa Infrastruktur -> Unggul, LAM Teknik SK 0699/2025
 *
 * Skala campur disengaja ('Unggul'/'Baik Sekali' skala baru; 'A'/'B' skala
 * lama BAN-PT yang belum re-akreditasi) — persis seperti di halaman P2MPP.
 *
 * Konvensi accredited_until: tanggal pasti dipakai apa adanya; bila hanya
 * tahun periode yang diketahui -> YYYY-12-31 (perkiraan akhir tahun); tak
 * diketahui -> null. Beberapa sertifikat SUDAH LEWAT / mungkin dalam proses
 * perpanjangan (ditandai di komentar) — lengkapi lewat Master Data saat SK
 * baru terbit.
 *
 * Idempotent & non-clobber: hanya mengisi baris yang accreditation-nya NULL,
 * jadi aman diulang dan tidak menimpa editan Master Data.
 */
class ProgramAccreditationSeeder extends Seeder
{
    /** id program => [peringkat, berlaku s.d. (Y-m-d|null)] — sumber P2MPP Polban */
    private const DATA = [
        1  => ['A',           '2028-03-07'], // D-3 T. Konstruksi Gedung (2024–2028)
        2  => ['Unggul',      '2026-12-31'], // D-3 T. Konstruksi Sipil (2021–2026). paste klaim 2031 (SK baru?) — belum terverifikasi
        3  => ['Unggul',      '2027-12-31'], // D-4 Perancangan Jalan & Jembatan
        4  => ['Unggul',      '2027-12-31'], // D-4 Perawatan & Perbaikan Gedung
        5  => ['B',           '2026-12-31'], // D-3 Teknik Mesin (2021–2026). paste klaim Unggul 2031 — TIDAK ada SK, dikoreksi ke B
        6  => ['Unggul',      '2027-12-31'], // D-4 Perancangan & Konstruksi Mesin
        7  => ['Baik Sekali', '2028-10-10'], // D-4 Proses Manufaktur
        8  => ['Unggul',      '2028-12-31'], // D-3 Pendingin & Tata Udara
        9  => ['B',           '2025-12-31'], // D-4 Pendingin & Tata Udara (2020–2025). paste klaim Unggul 2029 — belum terverifikasi
        10 => ['Baik Sekali', '2029-12-31'], // D-3 Teknik Konversi Energi
        11 => ['Baik Sekali', '2028-12-31'], // D-4 Teknik Konservasi Energi
        12 => ['Unggul',      '2028-12-31'], // D-3 Teknik Elektronika
        13 => ['Unggul',      '2027-12-31'], // D-4 Teknik Elektronika
        14 => ['A',           '2024-12-31'], // D-3 Teknik Listrik (2019–2024) ⚠️ SUDAH LEWAT; paste klaim Unggul — belum terverifikasi
        15 => ['A',           '2025-12-31'], // D-3 Teknik Telekomunikasi (2020–2025); paste klaim Unggul — belum terverifikasi
        16 => ['Unggul',      '2027-12-31'], // D-4 Teknik Telekomunikasi
        17 => ['B',           '2026-12-31'], // D-4 Teknik Otomasi Industri (2021–2026) ⚠️ hampir habis
        18 => ['Baik Sekali', '2028-10-31'], // D-4 Teknologi Pembangkit Tenaga Listrik
        19 => ['Unggul',      '2027-12-31'], // D-3 Teknik Kimia
        20 => ['Unggul',      '2027-12-31'], // D-3 Analis Kimia
        21 => ['Unggul',      '2027-12-31'], // D-4 Teknik Kimia Produksi Bersih
        22 => ['Unggul',      '2028-12-31'], // D-3 Teknik Informatika
        23 => ['Unggul',      '2030-08-15'], // D-4 Teknik Informatika — OVERRIDE: LAM INFOKOM 2025–2030
        24 => ['Baik Sekali', '2028-12-31'], // D-3 Akuntansi
        25 => ['A',           '2025-12-31'], // D-3 Keuangan & Perbankan (2020–2025); paste klaim Baik Sekali/LAMEMBA 2030 — belum terverifikasi
        26 => ['Baik Sekali', '2028-12-31'], // D-4 Akuntansi
        27 => ['A',           '2026-12-31'], // D-4 Akuntansi Manaj. Pemerintahan (2021–2026). paste klaim Unggul — dikoreksi ke A
        28 => ['A',           '2026-12-31'], // D-4 Keuangan Syariah (2021–2026). paste klaim Unggul — dikoreksi ke A
        29 => ['Unggul',      '2026-12-31'], // D-4 Manajemen Aset (2021–2026)
        30 => ['Baik Sekali', '2028-12-31'], // D-3 Administrasi Bisnis
        31 => ['Unggul',      '2024-12-31'], // D-3 Manajemen Pemasaran (2021–2024) ⚠️ SUDAH LEWAT
        32 => ['A',           '2026-12-31'], // D-3 Usaha Perjalanan Wisata (2021–2026). paste klaim Unggul — dikoreksi ke A
        33 => ['Unggul',      '2027-12-31'], // D-4 Administrasi Bisnis
        34 => ['Unggul',      '2027-12-31'], // D-4 Manajemen Pemasaran
        35 => ['Baik Sekali', '2027-12-31'], // D-3 Bahasa Inggris
        36 => ['Unggul',      '2027-12-31'], // D-3 Teknik Aeronautika
        37 => ['B',           '2024-12-31'], // S-2 Keuangan & Perbankan Syariah (2019–2024) ⚠️ SUDAH LEWAT; paste klaim Baik Sekali — belum terverifikasi
        38 => ['Unggul',      null],         // S-2 Rekayasa Infrastruktur — OVERRIDE: LAM Teknik SK 0699/2025 (tgl akhir SK belum dipastikan)
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::DATA as $id => [$peringkat, $berlaku]) {
            DB::connection('oltp')
                ->table('programs')
                ->where('id', $id)
                ->whereNull('accreditation')   // non-clobber: hanya isi yang kosong
                ->update([
                    'accreditation'    => $peringkat,
                    'accredited_until' => $berlaku,
                    'updated_at'       => $now,
                ]);
        }
    }
}
