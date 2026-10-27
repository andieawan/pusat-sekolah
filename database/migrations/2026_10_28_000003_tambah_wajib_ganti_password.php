<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akun yang dibuat massal dengan password awal acak wajib mengganti password saat pertama masuk.
        Schema::table('users', fn (Blueprint $t) => $t->boolean('wajib_ganti_password')->default(false)->after('password'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('wajib_ganti_password'));
    }
};
