<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('oltp')->create('stakeholder_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_id')->constrained('alumni_profiles')->cascadeOnDelete();
            $table->foreignId('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->string('contact_type', 20); // atasan, rekan, senior
            $table->string('contact_name', 150);
            $table->string('contact_email', 150);
            $table->string('alumni_status', 20)->nullable(); // bekerja, wiraswasta, lanjut_studi
            $table->timestamps();

            $table->index(['alumni_id', 'questionnaire_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('stakeholder_contacts');
    }
};
