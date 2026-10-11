<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AksesService;
use App\Services\Audit;
use App\Services\SsoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AkunController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->with(['pegawai', 'siswa', 'akses.aplikasi']);
        if (($t = trim((string) $request->query('q'))) !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('username', 'like', $like)->orWhere('nama', 'like', $like));
        }

        return view('admin.akun.index', [
            'items' => $q->orderBy('nama')->simplePaginate(25)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.akun.form', $this->dataForm(new User(['aktif' => true]), []));
    }

    public function store(Request $request, AksesService $akses)
    {
        $data = $this->validasi($request, null);
        $user = new User($data['akun']);
        $user->password = $data['password'];
        $user->save();
        $akses->sinkron($user, $data['peran']);
        Audit::catat('akun.dibuat', $user->username);

        return redirect()->route('admin.akun.index')->with('sukses', "Akun {$user->username} dibuat.");
    }

    public function edit(User $akun)
    {
        $peran = $akun->akses()->get()->groupBy('aplikasi_id')->map(fn ($b) => $b->pluck('peran')->all())->all();

        return view('admin.akun.form', $this->dataForm($akun, $peran));
    }

    public function update(Request $request, User $akun, AksesService $akses, SsoService $sso)
    {
        $data = $this->validasi($request, $akun);
        $sendiri = $akun->id === $request->user()->id;
        if ($sendiri && (! $data['akun']['aktif'] || ! $data['akun']['superadmin'])) {
            return back()->withInput()->with('galat', 'Anda tidak bisa menonaktifkan akun sendiri atau mencabut status superadmin sendiri.');
        }

        $akun->fill($data['akun']);
        if ($data['password'] !== null) {
            $akun->password = $data['password'];
            $sso->cabutUser($akun);
            Audit::catat('password.direset', $akun->username);
        }
        $akun->save();
        $akses->sinkron($akun, $data['peran']);
        Audit::catat('akun.diperbarui', $akun->username);

        return redirect()->route('admin.akun.index')->with('sukses', "Akun {$akun->username} diperbarui.");
    }

    public function destroy(Request $request, User $akun)
    {
        if ($akun->id === $request->user()->id) {
            return back()->with('galat', 'Anda tidak bisa menghapus akun sendiri.');
        }
        Audit::catat('akun.dihapus', $akun->username);
        $akun->delete(); // akses & token ikut terhapus (cascade)

        return redirect()->route('admin.akun.index')->with('sukses', 'Akun dihapus.');
    }

    private function dataForm(User $akun, array $peranTerpilih): array
    {
        return [
            'akun' => $akun,
            'aplikasi' => Aplikasi::orderBy('nama')->get(),
            'peranTerpilih' => $peranTerpilih,
            'pegawai' => Pegawai::where('aktif', true)->orderBy('nama')->pluck('nama', 'id')->all(),
            'nisSiswa' => $akun->siswa?->nis,
        ];
    }

    /** @return array{akun: array, password: ?string, peran: array} */
    private function validasi(Request $request, ?User $akun): array
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $passwordAturan = ['confirmed', Password::min(10)->letters()->numbers()];
        $v = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($akun?->id)],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($akun?->id)],
            'password' => $akun ? array_merge(['nullable'], $passwordAturan) : array_merge(['required'], $passwordAturan),
            'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'siswa_nis' => ['nullable', 'string', 'max:30'],
            'peran' => ['nullable', 'array'],
            'peran.*' => ['array'],
            'peran.*.*' => ['string', 'max:40'],
        ]);

        $siswaId = null;
        if (! empty($v['siswa_nis'])) {
            $siswaId = Siswa::where('nis', $v['siswa_nis'])->value('id');
            if (! $siswaId) {
                throw ValidationException::withMessages(['siswa_nis' => 'NIS siswa tidak ditemukan.']);
            }
        }
        if (! empty($v['pegawai_id']) && $siswaId) {
            throw ValidationException::withMessages(['siswa_nis' => 'Akun hanya boleh terhubung ke pegawai ATAU siswa, tidak keduanya.']);
        }

        return [
            'akun' => [
                'username' => $v['username'],
                'nama' => $v['nama'],
                'email' => $v['email'] ?: null,
                'pegawai_id' => $v['pegawai_id'] ?? null,
                'siswa_id' => $siswaId,
                'superadmin' => $request->boolean('superadmin'),
                'aktif' => $request->boolean('aktif'),
            ],
            'password' => $v['password'] ?? null,
            'peran' => array_map(fn ($p) => (array) $p, (array) ($v['peran'] ?? [])),
        ];
    }
}
