<?php
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
        $pw = Hash::make('password123');

        // ── 1. Super Admin / Ketua Tracer — 1 akun ──────────
        User::updateOrCreate(['email' => 'head.tracer@test.com'], [
            'name' => 'Kepala Tracer Study', 'role' => User::ROLE_HEAD_TRACER,
            'program_id' => null, 'jurusan' => null, 'password' => $pw,
        ]);

        // ── 2. Admin / Tim Tracer — 2 akun ──────────────────
        for ($i = 1; $i <= 2; $i++) {
            User::updateOrCreate(['email' => "tracer{$i}@test.com"], [
                'name' => "Tim Tracer {$i}", 'role' => User::ROLE_TRACER_TEAM,
                'program_id' => null, 'jurusan' => null, 'password' => $pw,
            ]);
        }

        // ── 3. Pimpinan (Wadir) — 2 akun ────────────────────
        $pimpinan = ['Wakil Direktur 1', 'Wakil Direktur 2'];
        foreach ($pimpinan as $name) {
            $slug = Str::slug($name, '.');
            User::updateOrCreate(['email' => "{$slug}@test.com"], [
                'name' => $name, 'role' => User::ROLE_WADIR,
                'program_id' => null, 'jurusan' => null, 'password' => $pw,
            ]);
        }

        // ── 4. Kajur — 1 per jurusan (10 jurusan dari ProgramSeeder) ─────
        $jurusanList = Program::distinct()->whereNotNull('jurusan')->pluck('jurusan');
        foreach ($jurusanList as $jurusan) {
            $slug = Str::slug($jurusan, '.');
            User::updateOrCreate(['email' => "kajur.{$slug}@test.com"], [
                'name' => "Kajur {$jurusan}", 'role' => User::ROLE_KAJUR,
                'program_id' => null, 'jurusan' => $jurusan, 'password' => $pw,
            ]);
        }

        // ── 5. Kaprodi — 1 per program studi ────────────────
        $programs = Program::all();
        foreach ($programs as $program) {
            $slug = Str::lower(str_replace([' ', '&', '/'], '', $program->code));
            User::updateOrCreate(['email' => "prodi.{$slug}@test.com"], [
                'name' => "Kaprodi {$program->name}", 'role' => User::ROLE_KAPRODI,
                'program_id' => $program->id, 'jurusan' => null, 'password' => $pw,
            ]);
        }
    }
}
