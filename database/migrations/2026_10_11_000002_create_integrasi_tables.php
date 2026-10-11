<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aplikasi yang terdaftar (Absensi, BK, TU, PKL, Website, ...).
        Schema::create('aplikasi', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kode', 30)->unique();
            $table->string('nama', 100);
            $table->string('base_url');
            $table->string('client_id', 40)->unique();
            $table->string('client_secret_hash', 64);
            $table->json('redirect_uris');
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable(); // terenkripsi (cast "encrypted")
            $table->json('scope');
            $table->json('peran_tersedia');
            $table->boolean('aktif')->default(true);
            $table->timestamp('secret_dibuat_pada')->nullable();
            $table->timestamps();
        });

        // Peran seorang akun di sebuah aplikasi (satu baris per peran).
        Schema::create('akses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('aplikasi_id')->constrained('aplikasi')->cascadeOnDelete();
            $table->string('peran', 40);
            $table->timestamps();
            $table->unique(['user_id', 'aplikasi_id', 'peran']);
        });

        // Kode otorisasi sekali pakai (hanya hash yang disimpan).
        Schema::create('sso_kode', function (Blueprint $table) {
            $table->id();
            $table->string('kode_hash', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('aplikasi_id')->constrained('aplikasi')->cascadeOnDelete();
            $table->text('redirect_uri');
            $table->string('code_challenge', 128)->nullable();
            $table->timestamp('kedaluwarsa_pada');
            $table->timestamp('digunakan_pada')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Token akses yang diterbitkan untuk aplikasi (hanya hash yang disimpan).
        Schema::create('sso_token', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('aplikasi_id')->constrained('aplikasi')->cascadeOnDelete();
            $table->timestamp('kedaluwarsa_pada');
            $table->timestamp('dicabut_pada')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'aplikasi_id']);
        });

        // Antrean webhook (pola outbox): perubahan dicatat dulu, dikirim kemudian dengan retry.
        Schema::create('webhook_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('aplikasi_id')->constrained('aplikasi')->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('payload');
            $table->string('status', 12)->default('pending'); // pending | terkirim | gagal
            $table->unsignedSmallInteger('percobaan')->default(0);
            $table->timestamp('jadwal_berikutnya')->nullable();
            $table->unsignedSmallInteger('kode_http')->nullable();
            $table->text('galat_terakhir')->nullable();
            $table->timestamp('terkirim_pada')->nullable();
            $table->timestamps();
            $table->index(['status', 'jadwal_berikutnya']);
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('aksi', 60)->index();
            $table->string('objek', 80)->nullable();
            $table->json('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('webhook_outbox');
        Schema::dropIfExists('sso_token');
        Schema::dropIfExists('sso_kode');
        Schema::dropIfExists('akses');
        Schema::dropIfExists('aplikasi');
    }
};
