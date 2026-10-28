<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Operator TU: boleh mengelola siswa, guru & staf, kelas, impor, profil sekolah, dan membuat akun sekaligus.
        Schema::table('users', fn (Blueprint $t) => $t->boolean('operator_tu')->default(false)->after('superadmin'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('operator_tu'));
    }
};
