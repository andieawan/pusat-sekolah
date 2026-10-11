<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nama', 20)->index();
            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->boolean('aktif')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });

        Schema::create('jurusan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kode', 20)->index();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });

        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nip', 30)->index();
            $table->string('nama', 150);
            $table->string('jenis', 10)->default('guru'); // guru | staf
            $table->string('jabatan', 100)->nullable();
            $table->string('no_hp', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nama', 50);
            $table->string('tingkat', 5); // X | XI | XII
            $table->foreignId('jurusan_id')->nullable()->constrained('jurusan')->nullOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->foreignId('wali_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nis', 30)->index();
            $table->string('nisn', 20)->nullable()->index();
            $table->string('nama', 150);
            $table->string('jenis_kelamin', 1)->nullable(); // L | P
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->string('status', 10)->default('aktif'); // aktif | lulus | pindah | keluar
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('no_hp', 30)->nullable();
            $table->string('no_hp_ortu', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('pegawai');
        Schema::dropIfExists('jurusan');
        Schema::dropIfExists('tahun_ajaran');
    }
};
