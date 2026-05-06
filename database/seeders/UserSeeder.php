<?php
// database/seeders/UserSeeder.php
namespace Database\Seeders;

use App\Models\Transactional\Program;
use App\Models\Transactional\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Akun Sistem ──────────────────────────────────────
        $systemUsers = [
            ['name' => 'Admin Sistem',  'email' => 'admin@test.com', 'role' => 'admin', 'program_id' => null],
            ['name' => 'Petugas P2MPP', 'email' => 'p2mpp@test.com', 'role' => 'p2mpp', 'program_id' => null],
        ];

        foreach ($systemUsers as $data) {
            User::updateOrCreate(['email' => $data['email']], array_merge($data, [
                'password' => Hash::make('password123'),
            ]));
        }

        // ── Akun Admin Prodi (1 per program studi) ───────────
        $programs = Program::all();

        foreach ($programs as $program) {
            $emailSlug = Str::lower(str_replace([' ', '&', '/'], ['', '', ''], $program->code));
            $email = "prodi.{$emailSlug}@test.com";

            User::updateOrCreate(['email' => $email], [
                'name'       => "Kaprodi {$program->name}",
                'email'      => $email,
                'role'       => 'prodi',
                'program_id' => $program->id,
                'password'   => Hash::make('password123'),
            ]);
        }
    }
}
