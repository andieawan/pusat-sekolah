<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\SsoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfilController extends Controller
{
    public function formPassword()
    {
        return view('profil.password');
    }

    public function ubahPassword(Request $request, SsoService $sso)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:password_lama', \App\Support\AturanPassword::rule()],
        ]);

        $user = $request->user();
        $user->password = $data['password'];
        $user->wajib_ganti_password = false;
        $user->save();
        $sso->cabutUser($user); // token aplikasi lama tidak berlaku lagi
        Audit::catat('password.diubah');

        return redirect()->intended(route('portal'))->with('sukses', 'Password berhasil diubah.');
    }
}
