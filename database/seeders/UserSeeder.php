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
        // ── Akun Sistem (role global, program_id NULL) ───────
        // Demo akun untuk coba login 5 role di luar kaprodi.
        // Semua pakai password seragam: "password123".
        $systemUsers = [
            ['name' => 'Admin Sistem',         'email' => 'admin@test.com',        'role' => User::ROLE_ADMIN],
            ['name' => 'Petugas P2MPP',        'email' => 'p2mpp@test.com',        'role' => User::ROLE_P2MPP],
            ['name' => 'Kepala Tracer Study',  'email' => 'head.tracer@test.com',  'role' => User::ROLE_HEAD_TRACER],
            ['name' => 'Tim Tracer 1',         'email' => 'tracer1@test.com',      'role' => User::ROLE_TRACER_TEAM],
            ['name' => 'Tim Tracer 2',         'email' => 'tracer2@test.com',      'role' => User::ROLE_TRACER_TEAM],
            ['name' => 'Wakil Direktur',       'email' => 'wadir@test.com',        'role' => User::ROLE_WADIR],
        ];

        foreach ($systemUsers as $data) {
            User::updateOrCreate(['email' => $data['email']], array_merge($data, [
                'program_id' => null,
                'password'   => Hash::make('password123'),
            ]));
        }

        // ── Akun Kaprodi (1 per program studi) ───────────────
        // role = 'kaprodi' (sebelumnya 'prodi'), sinkron dengan FE RBAC.
        $programs = Program::all();

        foreach ($programs as $program) {
            $emailSlug = Str::lower(str_replace([' ', '&', '/'], ['', '', ''], $program->code));
            $email = "prodi.{$emailSlug}@test.com";

            User::updateOrCreate(['email' => $email], [
                'name'       => "Kaprodi {$program->name}",
                'email'      => $email,
                'role'       => User::ROLE_KAPRODI,
                'program_id' => $program->id,
                'password'   => Hash::make('password123'),
            ]);
        }
    }
}
