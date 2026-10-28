<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Representasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * GET /api/v1/akun — akun yang punya akses ke aplikasi pemanggil, beserta perannya di aplikasi itu.
 * Password TIDAK pernah dikirim: pengguna login lewat SSO. Akun yang aksesnya dicabut tidak tampil
 * di sini; kabarnya datang lewat webhook "akses.diperbarui" (peran kosong), atau bandingkan
 * dengan daftar penuh tanpa updated_since.
 */
class AkunController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $app = $request->attributes->get('aplikasi');
        if (! $app->punyaScope('akun')) {
            return response()->json(['error' => ['kode' => 'scope_tidak_cukup', 'pesan' => 'Aplikasi ini tidak berhak membaca akun.']], 403);
        }

        $v = Validator::make($request->query(), [
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($v->fails()) {
            return response()->json(['error' => ['kode' => 'parameter_tidak_valid', 'pesan' => $v->errors()->first(), 'rincian' => $v->errors()]], 422);
        }

        $waktuServer = now();
        $q = User::query()
            ->whereHas('akses', fn ($a) => $a->where('aplikasi_id', $app->id))
            ->with(['pegawai', 'siswa', 'akses' => fn ($a) => $a->where('aplikasi_id', $app->id)]);

        if ($request->filled('updated_since')) {
            $sejak = Carbon::parse($request->query('updated_since'));
            $q->where(function ($w) use ($sejak, $app) {
                $w->where('updated_at', '>', $sejak)->orWhereHas(
                    'akses',
                    fn ($a) => $a->where('aplikasi_id', $app->id)->where('updated_at', '>', $sejak),
                );
            });
        }

        $hal = $q->orderBy('id')->paginate((int) $request->query('per_page', 200));

        return response()->json([
            'data' => $hal->getCollection()->map(fn (User $u) => Representasi::akun($u, $u->akses->pluck('peran')->all(), $app->punyaScope('akun_sandi')))->values(),
            'meta' => [
                'halaman' => $hal->currentPage(),
                'per_halaman' => $hal->perPage(),
                'total' => $hal->total(),
                'halaman_terakhir' => $hal->lastPage(),
                'waktu_server' => $waktuServer->toIso8601String(),
            ],
        ]);
    }
}
