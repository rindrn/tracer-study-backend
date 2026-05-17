<?php
// database/seeders/ProgramSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transactional\Program;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            // ── Jurusan Teknik Sipil ──────────────────────────
            ['name' => 'D3 Teknik Konstruksi Gedung',               'code' => 'TKG',   'degree' => 'D3', 'jurusan' => 'Teknik Sipil'],
            ['name' => 'D4 Teknik Perancangan Jalan & Jembatan',    'code' => 'TPJJ',  'degree' => 'D4', 'jurusan' => 'Teknik Sipil'],
            ['name' => 'D4 Teknik Perawatan & Perbaikan Gedung',    'code' => 'TPPG',  'degree' => 'D4', 'jurusan' => 'Teknik Sipil'],

            // ── Jurusan Teknik Mesin ──────────────────────────
            ['name' => 'D3 Teknik Mesin',                           'code' => 'TM',    'degree' => 'D3', 'jurusan' => 'Teknik Mesin'],
            ['name' => 'D4 Teknik Perancangan & Konstruksi Mesin',  'code' => 'TPKM',  'degree' => 'D4', 'jurusan' => 'Teknik Mesin'],
            ['name' => 'D4 Proses Manufaktur',                      'code' => 'PM',    'degree' => 'D4', 'jurusan' => 'Teknik Mesin'],

            // ── Jurusan Teknik Refrigerasi & Tata Udara ───────
            ['name' => 'D3 Teknik Pendingin & Tata Udara',          'code' => 'TPTU3', 'degree' => 'D3', 'jurusan' => 'Teknik Refrigerasi & Tata Udara'],
            ['name' => 'D4 Teknik Pendingin & Tata Udara',          'code' => 'TPTU4', 'degree' => 'D4', 'jurusan' => 'Teknik Refrigerasi & Tata Udara'],

            // ── Jurusan Teknik Konversi Energi ────────────────
            ['name' => 'D4 Teknik Konversi Energi',                 'code' => 'TKE',   'degree' => 'D4', 'jurusan' => 'Teknik Konversi Energi'],

            // ── Jurusan Teknik Elektro ────────────────────────
            ['name' => 'D3 Teknik Elektronika',                     'code' => 'TEL3',  'degree' => 'D3', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'D4 Teknik Telekomunikasi',                  'code' => 'TEL4',  'degree' => 'D4', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'D3 Teknik Listrik',                         'code' => 'TL',    'degree' => 'D3', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'D4 Teknik Otomasi Industri',                'code' => 'TOI',   'degree' => 'D4', 'jurusan' => 'Teknik Elektro'],

            // ── Jurusan Teknik Kimia ──────────────────────────
            ['name' => 'D3 Teknik Kimia',                           'code' => 'TK3',   'degree' => 'D3', 'jurusan' => 'Teknik Kimia'],
            ['name' => 'D3 Analis Kimia',                           'code' => 'AK3',   'degree' => 'D3', 'jurusan' => 'Teknik Kimia'],
            ['name' => 'D4 Teknik Kimia Produksi Bersih',           'code' => 'TKPB',  'degree' => 'D4', 'jurusan' => 'Teknik Kimia'],

            // ── Jurusan Teknik Komputer & Informatika ─────────
            ['name' => 'D3 Teknik Informatika',                     'code' => 'TI3',   'degree' => 'D3', 'jurusan' => 'Teknik Komputer & Informatika'],
            ['name' => 'D4 Teknik Informatika',                     'code' => 'TI',    'degree' => 'D4', 'jurusan' => 'Teknik Komputer & Informatika'],

            // ── Jurusan Akuntansi ─────────────────────────────
            ['name' => 'D3 Akuntansi',                              'code' => 'AKT3',  'degree' => 'D3', 'jurusan' => 'Akuntansi'],
            ['name' => 'D3 Keuangan & Perbankan',                   'code' => 'KP',    'degree' => 'D3', 'jurusan' => 'Akuntansi'],
            ['name' => 'D4 Akuntansi Manajemen Pemerintahan',       'code' => 'AMP',   'degree' => 'D4', 'jurusan' => 'Akuntansi'],
            ['name' => 'D4 Keuangan Syariah',                       'code' => 'KS',    'degree' => 'D4', 'jurusan' => 'Akuntansi'],

            // ── Jurusan Administrasi Niaga ─────────────────────
            ['name' => 'D3 Administrasi Bisnis',                    'code' => 'AB',    'degree' => 'D3', 'jurusan' => 'Administrasi Niaga'],
            ['name' => 'D3 Pemasaran',                              'code' => 'PSR',   'degree' => 'D3', 'jurusan' => 'Administrasi Niaga'],
            ['name' => 'D4 Destinasi Pariwisata',                   'code' => 'DP',    'degree' => 'D4', 'jurusan' => 'Administrasi Niaga'],

            // ── Jurusan Bahasa Inggris ─────────────────────────
            ['name' => 'D3 Bahasa Inggris',                         'code' => 'BIG',   'degree' => 'D3', 'jurusan' => 'Bahasa Inggris'],
        ];

        foreach ($programs as $data) {
            Program::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
