<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alamat siswa: mengisi otomatis surat panggilan & berita acara kunjungan rumah (BK).
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('alamat', 255)->nullable()->after('nama_ortu');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', fn (Blueprint $table) => $table->dropColumn('alamat'));
    }
};
