<?php

use App\Http\Controllers\Api\AkunController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\RingkasanController;
use App\Http\Controllers\Api\SiswaTulisController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

// Awalan "/api" ditambahkan otomatis.

// --- Login tunggal (SSO) ---
Route::prefix('sso')->middleware('throttle:sso-token')->group(function () {
    Route::post('token', [SsoController::class, 'token'])->name('sso.token');
    Route::get('userinfo', [SsoController::class, 'userinfo'])->name('sso.userinfo');
    Route::post('revoke', [SsoController::class, 'cabut'])->name('sso.revoke');
});

// --- Data induk (server-ke-server, Basic client_id:client_secret) ---
Route::prefix('v1')->middleware(['app.client', 'throttle:api-master'])->group(function () {
    Route::get('ping', fn () => response()->json(['ok' => true, 'waktu_server' => now()->toIso8601String()]));
    Route::get('master/{sumber}', [MasterController::class, 'index'])
        ->where('sumber', 'tahun-ajaran|jurusan|kelas|siswa|pegawai');
    Route::get('ringkasan', [RingkasanController::class, 'index']);
    Route::get('akun', [AkunController::class, 'index']);
    // Tulis siswa (scope siswa_tulis: mutasi oleh Tata Usaha)
    Route::post('siswa', [SiswaTulisController::class, 'store']);
    Route::patch('siswa/{uuid}', [SiswaTulisController::class, 'update'])->whereUuid('uuid');
});
