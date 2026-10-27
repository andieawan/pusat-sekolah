<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profil sekolah (kop surat, kepala sekolah, logo) & aturan password — dipakai semua aplikasi.
        Schema::create('pengaturan', function (Blueprint $t) {
            $t->id();
            $t->string('kunci', 60)->unique();
            $t->text('nilai')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
