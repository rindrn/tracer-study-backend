<?php
namespace Database\Seeders;

use App\Models\Transactional\Permission;
use App\Models\Transactional\RolePermission;
use App\Models\Transactional\User;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Define all permissions ───────────────────────────────────────────
        $permissions = [
            // User management
            ['name' => 'user.manage',              'description' => 'CRUD semua user'],
            ['name' => 'user.view',                'description' => 'Lihat daftar user'],

            // Questionnaire
            ['name' => 'questionnaire.manage',     'description' => 'CRUD kuesioner langsung'],
            ['name' => 'questionnaire.edit',       'description' => 'Edit kuesioner'],
            ['name' => 'questionnaire.request',    'description' => 'Ajukan tambah/hapus kuesioner (perlu approval)'],
            ['name' => 'questionnaire.toggle',     'description' => 'Aktifkan/nonaktifkan kuesioner'],
            ['name' => 'questionnaire.status',     'description' => 'Update status pengisian'],

            // Approval
            ['name' => 'approval.manage',          'description' => 'Approve/reject request dari admin'],

            // Master data
            ['name' => 'master.prodi',             'description' => 'Kelola kode prodi'],
            ['name' => 'master.region',            'description' => 'Kelola provinsi & kota'],

            // Reports & data viewing
            ['name' => 'report.status',            'description' => 'Kelola status laporan tracer study'],
            ['name' => 'data.view_all',            'description' => 'Viewer semua data institusi'],
            ['name' => 'data.view_jurusan',        'description' => 'Viewer data tingkat jurusan'],
            ['name' => 'data.view_prodi',          'description' => 'Viewer data prodi sendiri'],
            ['name' => 'data.download',            'description' => 'Download data'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['name' => $p['name']], $p);
        }

        // ── Role → Permission mapping ────────────────────────────────────────
        $map = [
            User::ROLE_HEAD_TRACER => [
                'user.manage', 'user.view',
                'questionnaire.manage', 'questionnaire.edit', 'questionnaire.toggle', 'questionnaire.status',
                'approval.manage',
                'master.prodi', 'master.region',
                'report.status',
                'data.view_all', 'data.download',
            ],
            User::ROLE_TRACER_TEAM => [
                'questionnaire.edit', 'questionnaire.request', 'questionnaire.status',
                'user.view',
            ],
            User::ROLE_WADIR => [
                'data.view_all', 'data.download',
            ],
            User::ROLE_KAJUR => [
                'data.view_jurusan', 'data.download',
            ],
            User::ROLE_KAPRODI => [
                'data.view_prodi', 'data.download',
            ],
            // Sama seperti kajur (viewer read-only): satu-satunya beda adalah
            // cakupannya meliputi lebih dari satu jurusan sekaligus.
            User::ROLE_DEKAN => [
                'data.view_jurusan', 'data.download',
            ],
        ];

        // Clear existing and re-seed
        RolePermission::query()->delete();

        foreach ($map as $role => $permNames) {
            $permIds = Permission::whereIn('name', $permNames)->pluck('id');
            foreach ($permIds as $pid) {
                RolePermission::create(['role' => $role, 'permission_id' => $pid]);
            }
        }
    }
}
