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
            'password' => ['required', 'confirmed', 'different:password_lama', Password::min(10)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $sso->cabutUser($user); // token aplikasi lama tidak berlaku lagi
        Audit::catat('password.diubah');

        return redirect()->route('portal')->with('sukses', 'Password berhasil diubah.');
    }
}
