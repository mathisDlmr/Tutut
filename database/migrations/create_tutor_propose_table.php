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
        Schema::create('tutor_propose', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_user')->constrained('users', 'id');
            $table->foreignId('fk_code')->constrained('uvs', 'code');
            $table->timestamps();
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutor_propose');
    }
};
