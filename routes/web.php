<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\AplikasiController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImporController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

// --- Tamu ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'formLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.proses');
});

// --- SSO (browser) ---
Route::get('/sso/authorize', [SsoController::class, 'otorisasi'])->name('sso.authorize');
Route::get('/sso/logout', [SsoController::class, 'keluar'])->name('sso.logout');

// --- Sudah login ---
Route::middleware(['auth', 'akun.aktif'])->group(function () {
    Route::get('/', fn () => redirect()->route('portal'));
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/portal', [PortalController::class, 'index'])->name('portal');
    Route::get('/profil/password', [ProfilController::class, 'formPassword'])->name('profil.password');
    Route::put('/profil/password', [ProfilController::class, 'ubahPassword'])->name('profil.password.ubah');

    // --- Panel admin (khusus superadmin) ---
    Route::middleware('superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('data/{sumber}')
            ->where(['sumber' => 'tahun-ajaran|jurusan|pegawai|kelas|siswa'])
            ->name('master.')
            ->group(function () {
                Route::get('/', [MasterController::class, 'index'])->name('index');
                Route::get('/baru', [MasterController::class, 'create'])->name('create');
                Route::post('/', [MasterController::class, 'store'])->name('store');
                Route::get('/{id}/ubah', [MasterController::class, 'edit'])->whereNumber('id')->name('edit');
                Route::put('/{id}', [MasterController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('/{id}', [MasterController::class, 'destroy'])->whereNumber('id')->name('destroy');
            });

        Route::prefix('impor')->name('impor.')->group(function () {
            Route::get('/', [ImporController::class, 'form'])->name('form');
            Route::get('/templat/{sumber}', [ImporController::class, 'templat'])->name('templat');
            Route::post('/pratinjau', [ImporController::class, 'pratinjau'])->name('pratinjau');
            Route::post('/terapkan', [ImporController::class, 'terapkan'])->name('terapkan');
        });

        Route::resource('akun', AkunController::class)->except('show')->parameters(['akun' => 'akun']);

        Route::resource('aplikasi', AplikasiController::class)->except('show')->parameters(['aplikasi' => 'aplikasi']);
        Route::post('aplikasi/{aplikasi}/rotasi', [AplikasiController::class, 'rotasi'])->name('aplikasi.rotasi');
        Route::post('aplikasi/{aplikasi}/ping', [AplikasiController::class, 'ping'])->name('aplikasi.ping');

        Route::prefix('webhook')->name('webhook.')->group(function () {
            Route::get('/', [WebhookController::class, 'index'])->name('index');
            Route::post('/kirim-sekarang', [WebhookController::class, 'kirimSekarang'])->name('kirim');
            Route::post('/ulang-semua', [WebhookController::class, 'ulangSemuaGagal'])->name('ulang_semua');
            Route::get('/{webhook}', [WebhookController::class, 'show'])->name('show');
            Route::post('/{webhook}/ulang', [WebhookController::class, 'ulang'])->name('ulang');
        });

        Route::get('audit', [AuditController::class, 'index'])->name('audit');
    });
});
