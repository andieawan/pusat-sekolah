<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Semester aktif (Ganjil/Genap) diatur di Pusat dan diikuti semua aplikasi.
        Schema::table('tahun_ajaran', fn (Blueprint $t) => $t->string('semester', 6)->default('Ganjil')->after('selesai'));
    }

    public function down(): void
    {
        Schema::table('tahun_ajaran', fn (Blueprint $t) => $t->dropColumn('semester'));
    }
};
