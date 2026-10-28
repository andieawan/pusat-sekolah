<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\AplikasiController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImporController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\AkunMassalController;
use App\Http\Controllers\Admin\AkunSiswaMassalController;
use App\Http\Controllers\Admin\KenaikanController;
use App\Http\Controllers\Admin\ProfilSekolahController;
use App\Http\Controllers\Admin\SambunganController;
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
Route::get('/logo-sekolah', [ProfilSekolahController::class, 'logo'])->middleware('throttle:120,1')->name('logo.sekolah');
Route::get('/sso/authorize', [SsoController::class, 'otorisasi'])->name('sso.authorize');
Route::get('/sso/logout', [SsoController::class, 'keluar'])->name('sso.logout');

// --- Sudah login ---
Route::middleware(['auth', 'akun.aktif', 'ganti.password'])->group(function () {
    Route::get('/', fn () => redirect()->route('portal'));
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/portal', [PortalController::class, 'index'])->name('portal');
    Route::get('/buka/{kode}', [PortalController::class, 'buka'])->where('kode', '[a-z0-9_-]+')->name('buka');
    Route::get('/profil/password', [ProfilController::class, 'formPassword'])->name('profil.password');
    Route::put('/profil/password', [ProfilController::class, 'ubahPassword'])->name('profil.password.ubah');

    // --- Panel admin (superadmin; Operator TU sebagian — lihat AdminPusat::OPERATOR_TU) ---
    Route::middleware('admin.pusat')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('data/{sumber}')
            ->where(['sumber' => 'tahun-ajaran|jurusan|pegawai|kelas|siswa|mapel'])
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

        Route::get('akun/massal', [AkunMassalController::class, 'form'])->name('akun.massal');
        Route::post('akun/massal', [AkunMassalController::class, 'buat'])->name('akun.massal.buat');
        Route::get('akun/siswa', [AkunSiswaMassalController::class, 'form'])->name('akun.siswa');
        Route::post('akun/siswa', [AkunSiswaMassalController::class, 'buat'])->name('akun.siswa.buat');
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

        Route::prefix('sambungan/{jenis}')->where(['jenis' => '[a-z0-9-]+'])->name('sambungan.')->group(function () {
            Route::post('/nyalakan', [SambunganController::class, 'nyalakan'])->name('nyalakan');
            Route::post('/matikan', [SambunganController::class, 'matikan'])->name('matikan');
            Route::post('/ganti-kunci', [SambunganController::class, 'gantiKunci'])->name('kunci');
            Route::post('/kabari', [SambunganController::class, 'kabari'])->name('kabari');
        });
        Route::get('sambungan', [SambunganController::class, 'index'])->name('sambungan.index');

        Route::get('profil-sekolah', [ProfilSekolahController::class, 'edit'])->name('profil.edit');
        Route::put('profil-sekolah', [ProfilSekolahController::class, 'update'])->name('profil.update');

        Route::get('kenaikan', [KenaikanController::class, 'index'])->name('kenaikan.index');
        Route::post('kenaikan/tahun-baru', [KenaikanController::class, 'tahunBaru'])->name('kenaikan.tahun');
        Route::post('kenaikan', [KenaikanController::class, 'terapkan'])->name('kenaikan.terapkan');

        Route::get('pengampu', [\App\Http\Controllers\Admin\PengampuController::class, 'index'])->name('pengampu.index');
        Route::post('pengampu', [\App\Http\Controllers\Admin\PengampuController::class, 'store'])->name('pengampu.store');
        Route::delete('pengampu/{pengampu}', [\App\Http\Controllers\Admin\PengampuController::class, 'destroy'])->whereNumber('pengampu')->name('pengampu.destroy');
        Route::post('pengampu/impor', [\App\Http\Controllers\Admin\PengampuController::class, 'impor'])->name('pengampu.impor');
        Route::get('pengampu/templat', [\App\Http\Controllers\Admin\PengampuController::class, 'templat'])->name('pengampu.templat');

        Route::get('audit', [AuditController::class, 'index'])->name('audit');
    });
});
