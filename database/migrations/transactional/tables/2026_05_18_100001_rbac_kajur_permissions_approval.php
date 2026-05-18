<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        // 1. Migrate old roles
        DB::connection('oltp')->table('users')->where('role', 'admin')->update(['role' => 'head_tracer']);
        DB::connection('oltp')->table('users')->where('role', 'p2mpp')->update(['role' => 'wadir']);

        // 2. Update CHECK constraint (remove admin/p2mpp, add kajur)
        DB::connection('oltp')->statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::connection('oltp')->statement("
            ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role::text = ANY (ARRAY['head_tracer','tracer_team','wadir','kajur','kaprodi']::text[]))
        ");

        // 3. Add jurusan to users (kajur scope)
        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->string('jurusan', 100)->nullable()->after('program_id');
        });

        // 4. Permissions table
        Schema::connection('oltp')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // 5. Role-Permission pivot
        Schema::connection('oltp')->create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['role', 'permission_id']);
        });

        // 6. Approval requests (tracer_team → head_tracer)
        Schema::connection('oltp')->create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);          // add_questionnaire, delete_questionnaire
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('approval_requests');
        Schema::connection('oltp')->dropIfExists('role_permissions');
        Schema::connection('oltp')->dropIfExists('permissions');

        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->dropColumn('jurusan');
        });

        DB::connection('oltp')->statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::connection('oltp')->statement("
            ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role::text = ANY (ARRAY['admin','p2mpp','kaprodi','head_tracer','tracer_team','wadir']::text[]))
        ");

        DB::connection('oltp')->table('users')->where('role', 'head_tracer')->update(['role' => 'admin']);
        DB::connection('oltp')->table('users')->where('role', 'wadir')->update(['role' => 'p2mpp']);
    }
};
