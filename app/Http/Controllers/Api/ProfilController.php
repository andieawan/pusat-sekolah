<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfilSekolah;
use Illuminate\Http\JsonResponse;

/** GET /api/v1/profil — profil sekolah untuk kop surat, nama guru BK, aturan password. */
class ProfilController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ProfilSekolah::data()]);
    }
}
