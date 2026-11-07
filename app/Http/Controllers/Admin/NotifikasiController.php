<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index()
    {
        $items = Notifikasi::latest('id')->paginate(30);

        return view('admin.notifikasi.index', ['items' => $items]);
    }

    /** Tandai semua (atau satu) sudah dibaca. */
    public function baca(Request $request)
    {
        Notifikasi::whereNull('dibaca_pada')->when($request->integer('id'), fn ($q, $id) => $q->whereKey($id))->update(['dibaca_pada' => now()]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('sukses', 'Notifikasi ditandai sudah dibaca.');
    }

    /** Untuk pemberitahuan langsung di layar (pusat.js): notifikasi belum dibaca setelah id tertentu. */
    public function baru(Request $request)
    {
        $sejak = $request->integer('sejak');
        $baru = Notifikasi::whereNull('dibaca_pada')->where('id', '>', $sejak)->orderBy('id')->limit(5)->get(['id', 'jenis', 'judul', 'isi']);

        return response()->json(['jumlah' => Notifikasi::belumDibaca(), 'akhir' => (int) Notifikasi::max('id'), 'baru' => $baru]);
    }
}
