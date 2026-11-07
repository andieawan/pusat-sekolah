<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aplikasi melaporkan hasil sinkronnya ke Pusat → tampil di menu Aplikasi + notifikasi admin.
        Schema::table('aplikasi', function (Blueprint $t) {
            $t->timestamp('sinkron_diminta_pada')->nullable();
            $t->timestamp('sinkron_lapor_pada')->nullable();
            $t->json('sinkron_laporan')->nullable();
        });
        Schema::create('notifikasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('aplikasi_id')->nullable()->constrained('aplikasi')->nullOnDelete();
            $t->string('jenis', 10); // ok | galat
            $t->string('judul', 150);
            $t->string('isi', 500)->nullable();
            $t->timestamp('dibaca_pada')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
        Schema::table('aplikasi', fn (Blueprint $t) => $t->dropColumn(['sinkron_diminta_pada', 'sinkron_lapor_pada', 'sinkron_laporan']));
    }
};
