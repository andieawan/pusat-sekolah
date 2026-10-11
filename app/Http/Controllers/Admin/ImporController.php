<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImporService;
use App\Support\CsvParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImporController extends Controller
{
    public function form()
    {
        return view('admin.impor.form', ['kolom' => ImporService::KOLOM]);
    }

    public function templat(string $sumber)
    {
        abort_unless(in_array($sumber, ImporService::SUMBER, true), 404);
        $header = implode(';', ImporService::KOLOM[$sumber]);

        return response()->streamDownload(
            fn () => print "\xEF\xBB\xBF".$header."\r\n",
            "templat-{$sumber}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /** Tahap 1: baca file, tampilkan hasil simulasi. Belum ada yang ditulis ke database. */
    public function pratinjau(Request $request, ImporService $impor)
    {
        $data = $request->validate([
            'sumber' => ['required', 'in:'.implode(',', ImporService::SUMBER)],
            'berkas' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ]);

        $isi = file_get_contents($request->file('berkas')->getRealPath());
        try {
            $csv = CsvParser::parse($isi);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }

        $wajib = ['siswa' => ['nis', 'nama'], 'kelas' => ['nama', 'tingkat']][$data['sumber']] ?? ['nip', 'nama'];
        $hilang = array_diff($wajib, $csv['header']);
        if ($hilang) {
            return back()->withErrors(['berkas' => 'Kolom wajib tidak ditemukan: '.implode(', ', $hilang).'. Unduh templat untuk format yang benar.']);
        }

        $token = Str::random(40);
        Storage::disk('local')->put("impor/{$token}.csv", $isi);
        $request->session()->put('impor', ['token' => $token, 'sumber' => $data['sumber']]);

        $r = $impor->rencana($data['sumber'], $csv['baris']);

        return view('admin.impor.pratinjau', [
            'sumber' => $data['sumber'],
            'ringkas' => ['baru' => $r['baru'], 'ubah' => $r['ubah'], 'sama' => $r['sama']],
            'galat' => array_slice($r['galat'], 0, 100),
            'jumlahGalat' => count($r['galat']),
            'contoh' => array_slice($r['item'], 0, 10),
        ]);
    }

    /** Tahap 2: terapkan file yang sama persis dengan yang dipratinjau. */
    public function terapkan(Request $request, ImporService $impor)
    {
        $sesi = $request->session()->get('impor');
        $token = (string) $request->input('token');
        if (! $sesi || ! hash_equals($sesi['token'], $token) || ! Storage::disk('local')->exists("impor/{$token}.csv")) {
            return redirect()->route('admin.impor.form')->withErrors(['berkas' => 'Sesi impor kedaluwarsa. Unggah ulang file.']);
        }

        $csv = CsvParser::parse(Storage::disk('local')->get("impor/{$token}.csv"));
        $hasil = $impor->terapkan($sesi['sumber'], $csv['baris']);

        Storage::disk('local')->delete("impor/{$token}.csv");
        $request->session()->forget('impor');

        return redirect()->route('admin.master.index', $sesi['sumber'])->with(
            'sukses',
            "Impor selesai: {$hasil['baru']} baru, {$hasil['ubah']} diperbarui, {$hasil['sama']} tanpa perubahan, {$hasil['galat']} galat dilewati.",
        );
    }
}
