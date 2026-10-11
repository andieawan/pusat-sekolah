<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akun login tunggal. Satu akun bisa terhubung ke satu pegawai atau satu siswa.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('username', 60)->unique();
            $table->string('nama');
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->boolean('superadmin')->default(false);
            $table->boolean('aktif')->default(true);
            $table->unsignedBigInteger('pegawai_id')->nullable()->index();
            $table->unsignedBigInteger('siswa_id')->nullable()->index();
            $table->timestamp('login_terakhir')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
