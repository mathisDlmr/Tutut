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
        Schema::create('dispo_salle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_salle')->constrained('salles', 'numero')->onDelete('cascade');
            $table->enum('jour', ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Médians', 'Finaux']);
            $table->time('debut');
            $table->time('fin');
            $table->timestamps();
        });                         
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispo_salle');
    }
};
