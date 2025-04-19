<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor1_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('tutor2_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('fk_semaine')->constrained('semaines', 'id')->onDelete('cascade');
            $table->foreignId('fk_salle')->constrained('salles', 'numero');
            $table->boolean('open')->default(false);
            $table->dateTime('start');
            $table->dateTime('end');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creneaux');
    }
};
