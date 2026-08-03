<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    protected $connection = 'oltp';
 
    public function up(): void
    {
        Schema::connection('oltp')->create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->enum('degree', ['S1', 'D3', 'D4', 'S2']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('programs');
    }
};
