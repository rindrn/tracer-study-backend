<?php

namespace Database\Seeders;

use App\Models\Transactional\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'head_tracer', 'label' => 'Super Admin (Ketua Tracer)', 'description' => 'Full system access', 'scope' => 'Seluruh Jurusan'],
            ['name' => 'tracer_team', 'label' => 'Admin (Tim Tracer)', 'description' => 'Kelola & edit kuesioner', 'scope' => 'Seluruh Jurusan'],
            ['name' => 'wadir', 'label' => 'Pimpinan (Direktur/Wadir/P2MPP)', 'description' => 'Viewer seluruh data institusi', 'scope' => 'Seluruh Jurusan'],
            ['name' => 'kajur', 'label' => 'Ketua Jurusan', 'description' => 'Viewer data jurusan', 'scope' => 'Jurusan'],
            ['name' => 'kaprodi', 'label' => 'Ketua Program Studi', 'description' => 'Viewer data program studi', 'scope' => 'Program Studi'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
