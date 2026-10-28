<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mata pelajaran: satu daftar untuk semua aplikasi (Absensi; nanti jadwal, ulangan, rapor).
        Schema::create('mapel', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kode', 20)->index();
            $table->string('nama', 150);
            $table->string('kelompok', 20)->default('umum'); // umum | kejuruan | mulok
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapel');
    }
};
