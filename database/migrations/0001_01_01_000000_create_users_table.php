<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations de la table des utilisateurs applicatifs.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('identifiant')->unique();
            $table->string('prenom');
            $table->string('nom');
            $table->string('role')->default('user'); // 'admin' | 'user'
            $table->string('agence_id')->nullable();  // Prévu mais non utilisé dans le MVP (D4)
            $table->string('password');
            $table->boolean('actif')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
