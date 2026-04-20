<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('alumni_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('nim', 30)->unique();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('program_id')
                ->constrained('programs')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('entry_year')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['program_id', 'graduation_year']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('alumni_profiles');
    }
};
