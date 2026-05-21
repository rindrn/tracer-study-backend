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
            ['name' => 'Teknik Konstruksi Gedung',               'code' => 'TKG',   'dikti_code' => '22403', 'degree' => 'D3', 'jurusan' => 'Teknik Sipil'],
            ['name' => 'Teknik Konstruksi Sipil',                'code' => 'TKS',   'dikti_code' => '22402', 'degree' => 'D3', 'jurusan' => 'Teknik Sipil'],
            ['name' => 'Teknik Perancangan Jalan & Jembatan',    'code' => 'TPJJ',  'dikti_code' => '22301', 'degree' => 'D4', 'jurusan' => 'Teknik Sipil'],
            ['name' => 'Teknik Perawatan & Perbaikan Gedung',    'code' => 'TPPG',  'dikti_code' => '22303', 'degree' => 'D4', 'jurusan' => 'Teknik Sipil'],

            // ── Jurusan Teknik Mesin ──────────────────────────
            ['name' => 'Teknik Mesin',                           'code' => 'TM',    'dikti_code' => '21401', 'degree' => 'D3', 'jurusan' => 'Teknik Mesin'],
            ['name' => 'Teknik Perancangan & Konstruksi Mesin',  'code' => 'TPKM',  'dikti_code' => '36301', 'degree' => 'D4', 'jurusan' => 'Teknik Mesin'],
            ['name' => 'Proses Manufaktur',                      'code' => 'PM',    'dikti_code' => '21307', 'degree' => 'D4', 'jurusan' => 'Teknik Mesin'],

            // ── Jurusan Teknik Refrigerasi & Tata Udara ───────
            ['name' => 'Teknik Pendingin & Tata Udara',          'code' => 'TPTU3', 'dikti_code' => '21405', 'degree' => 'D3', 'jurusan' => 'Teknik Refrigerasi & Tata Udara'],
            ['name' => 'Teknik Pendingin & Tata Udara',          'code' => 'TPTU4', 'dikti_code' => '21305', 'degree' => 'D4', 'jurusan' => 'Teknik Refrigerasi & Tata Udara'],

            // ── Jurusan Teknik Konversi Energi ────────────────
            ['name' => 'Teknik Konversi Energi',                 'code' => 'TKE3',  'dikti_code' => '21406', 'degree' => 'D3', 'jurusan' => 'Teknik Konversi Energi'],
            ['name' => 'Teknik Konservasi Energi',               'code' => 'TKE4',  'dikti_code' => '21308', 'degree' => 'D4', 'jurusan' => 'Teknik Konversi Energi'],

            // ── Jurusan Teknik Elektro ────────────────────────
            ['name' => 'Teknik Elektronika',                     'code' => 'TEL3',  'dikti_code' => '20401', 'degree' => 'D3', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'Teknik Elektronika',                     'code' => 'TEL4',  'dikti_code' => '20301', 'degree' => 'D4', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'Teknik Listrik',                         'code' => 'TL',    'dikti_code' => '20403', 'degree' => 'D3', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'Teknik Telekomunikasi',                  'code' => 'TELKOM3','dikti_code' => '20402', 'degree' => 'D3', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'Teknik Telekomunikasi',                  'code' => 'TELKOM4','dikti_code' => '20304', 'degree' => 'D4', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'Teknik Otomasi Industri',                'code' => 'TOI',   'dikti_code' => '36304', 'degree' => 'D4', 'jurusan' => 'Teknik Elektro'],
            ['name' => 'Teknologi Pembangkit Tenaga Listrik',    'code' => 'TPTL',  'dikti_code' => '20303', 'degree' => 'D4', 'jurusan' => 'Teknik Elektro'],

            // ── Jurusan Teknik Kimia ──────────────────────────
            ['name' => 'Teknik Kimia',                           'code' => 'TK3',   'dikti_code' => '24401', 'degree' => 'D3', 'jurusan' => 'Teknik Kimia'],
            ['name' => 'Analis Kimia',                           'code' => 'AK3',   'dikti_code' => '24402', 'degree' => 'D3', 'jurusan' => 'Teknik Kimia'],
            ['name' => 'Teknik Kimia Produksi Bersih',           'code' => 'TKPB',  'dikti_code' => '24301', 'degree' => 'D4', 'jurusan' => 'Teknik Kimia'],

            // ── Jurusan Teknik Komputer & Informatika ─────────
            ['name' => 'Teknik Informatika',                     'code' => 'TI3',   'dikti_code' => '56401', 'degree' => 'D3', 'jurusan' => 'Teknik Komputer & Informatika'],
            ['name' => 'Teknik Informatika',                     'code' => 'TI',    'dikti_code' => '55301', 'degree' => 'D4', 'jurusan' => 'Teknik Komputer & Informatika'],

            // ── Jurusan Akuntansi ─────────────────────────────
            ['name' => 'Akuntansi',                              'code' => 'AKT3',  'dikti_code' => '62401', 'degree' => 'D3', 'jurusan' => 'Akuntansi'],
            ['name' => 'Keuangan & Perbankan',                   'code' => 'KP',    'dikti_code' => '61406', 'degree' => 'D3', 'jurusan' => 'Akuntansi'],
            ['name' => 'Akuntansi',                              'code' => 'AKT4',  'dikti_code' => '62306', 'degree' => 'D4', 'jurusan' => 'Akuntansi'],
            ['name' => 'Akuntansi Manajemen Pemerintahan',       'code' => 'AMP',   'dikti_code' => '62301', 'degree' => 'D4', 'jurusan' => 'Akuntansi'],
            ['name' => 'Keuangan Syariah',                       'code' => 'KS',    'dikti_code' => '61306', 'degree' => 'D4', 'jurusan' => 'Akuntansi'],
            ['name' => 'Manajemen Aset',                         'code' => 'MA',    'dikti_code' => '61307', 'degree' => 'D4', 'jurusan' => 'Akuntansi'],

            // ── Jurusan Administrasi Niaga ─────────────────────
            ['name' => 'Administrasi Bisnis',                    'code' => 'AB3',   'dikti_code' => '63411', 'degree' => 'D3', 'jurusan' => 'Administrasi Niaga'],
            ['name' => 'Manajemen Pemasaran',                    'code' => 'MP3',   'dikti_code' => '61404', 'degree' => 'D3', 'jurusan' => 'Administrasi Niaga'],
            ['name' => 'Usaha Perjalanan Wisata',                'code' => 'UPW',   'dikti_code' => '93401', 'degree' => 'D3', 'jurusan' => 'Administrasi Niaga'],
            ['name' => 'Administrasi Bisnis',                    'code' => 'AB4',   'dikti_code' => '63311', 'degree' => 'D4', 'jurusan' => 'Administrasi Niaga'],
            ['name' => 'Manajemen Pemasaran',                    'code' => 'MP4',   'dikti_code' => '61304', 'degree' => 'D4', 'jurusan' => 'Administrasi Niaga'],

            // ── Jurusan Bahasa Inggris ─────────────────────────
            ['name' => 'Bahasa Inggris',                         'code' => 'BIG',   'dikti_code' => '79402', 'degree' => 'D3', 'jurusan' => 'Bahasa Inggris'],

            // ── Jurusan Teknik Aeronautika ─────────────────────
            ['name' => 'Teknik Aeronautika',                     'code' => 'TA',    'dikti_code' => '40402', 'degree' => 'D3', 'jurusan' => 'Teknik Aeronautika'],
        ];

        foreach ($programs as $data) {
            Program::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
