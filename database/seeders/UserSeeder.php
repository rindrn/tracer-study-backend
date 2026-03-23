<?php
// database/seeders/UserSeeder.php
namespace Database\Seeders;
 
use App\Models\Transactional\Program;
use App\Models\Transactional\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
 
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil ID prodi dari DB untuk foreign key
        $ti = Program::where('code', 'TI')->first();
        $si = Program::where('code', 'SI')->first();
 
        $users = [
            ['name'=>'Admin Sistem',             'email'=>'admin@test.com',    'role'=>'admin', 'program_id'=>null],
            ['name'=>'Petugas P2MPP',            'email'=>'p2mpp@test.com',    'role'=>'p2mpp', 'program_id'=>null],
            ['name'=>'Kaprodi Teknik Informatika','email'=>'prodi.ti@test.com', 'role'=>'prodi', 'program_id'=>$ti?->id],
            ['name'=>'Kaprodi Sistem Informasi',  'email'=>'prodi.si@test.com', 'role'=>'prodi', 'program_id'=>$si?->id],
        ];
 
        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], array_merge($data, [
                'password' => Hash::make('password123'),
            ]));
        }
    }
}
