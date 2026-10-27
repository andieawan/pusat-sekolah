<?php

namespace App\Support;

use App\Models\Pengaturan;
use Illuminate\Validation\Rules\Password;

/** Aturan password akun Pusat (diatur di Administrasi → Profil Sekolah). Bawaan: minimal 10, huruf & angka. */
final class AturanPassword
{
    /** @return array{min:int,huruf:bool,angka:bool,simbol:bool} */
    public static function nilai(): array
    {
        return [
            'min' => max(8, min(64, (int) Pengaturan::ambil('pw_min', '10'))),
            'huruf' => Pengaturan::ambil('pw_huruf', '1') === '1',
            'angka' => Pengaturan::ambil('pw_angka', '1') === '1',
            'simbol' => Pengaturan::ambil('pw_simbol', '0') === '1',
        ];
    }

    public static function rule(): Password
    {
        $n = self::nilai();
        $r = Password::min($n['min']);
        $n['huruf'] && $r = $r->letters();
        $n['angka'] && $r = $r->numbers();
        $n['simbol'] && $r = $r->symbols();

        return $r;
    }

    public static function keterangan(): string
    {
        $n = self::nilai();
        $isi = array_keys(array_filter(['huruf' => $n['huruf'], 'angka' => $n['angka'], 'simbol' => $n['simbol']]));

        return 'Minimal '.$n['min'].' karakter'.($isi ? ', mengandung '.implode(' dan ', $isi) : '').'.';
    }

    /** Password awal acak yang mudah dibaca dan memenuhi aturan. */
    public static function acak(): string
    {
        $n = self::nilai();
        $huruf = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $pw = '';
        for ($i = 0; $i < max(6, $n['min'] - 4); $i++) {
            $pw .= $huruf[random_int(0, strlen($huruf) - 1)];
        }
        $pw .= str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return $n['simbol'] ? $pw.'#' : $pw;
    }
}
