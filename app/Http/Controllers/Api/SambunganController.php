<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SambunganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/sambungan — sambungan antar-aplikasi milik aplikasi pemanggil (termasuk kunci). */
class SambunganController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => SambunganService::untuk($request->attributes->get('aplikasi'))])
            ->header('Cache-Control', 'no-store');
    }
}
