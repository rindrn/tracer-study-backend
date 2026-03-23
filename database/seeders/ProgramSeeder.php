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
            ['name' => 'Teknik Informatika',   'code' => 'TI', 'degree' => 'S1'],
            ['name' => 'Sistem Informasi',      'code' => 'SI', 'degree' => 'S1'],
            ['name' => 'Manajemen Informatika', 'code' => 'MI', 'degree' => 'D3'],
            ['name' => 'Teknik Elektro',        'code' => 'TE', 'degree' => 'S1'],
            ['name' => 'Akuntansi',             'code' => 'AK', 'degree' => 'S1'],
        ];
 
        foreach ($programs as $data) {
            Program::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
