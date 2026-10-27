<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sambungan antar-aplikasi (mis. PKL → BK) yang diatur dari menu Administrasi → Sambungan.
        Schema::create('sambungan', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30)->unique();
            $table->boolean('aktif')->default(false);
            $table->text('kunci')->nullable(); // terenkripsi (cast "encrypted")
            $table->timestamp('kunci_dibuat_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sambungan');
    }
};
