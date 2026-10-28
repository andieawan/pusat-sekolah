<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengampu: guru X mengajar mapel Y di kelas Z (kelas sudah terikat tahun ajaran).
        Schema::create('pengampu', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pegawai_id')->constrained('pegawai');
            $table->foreignId('mapel_id')->constrained('mapel');
            $table->foreignId('kelas_id')->constrained('kelas');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pegawai_id', 'mapel_id', 'kelas_id']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengampu');
    }
};
