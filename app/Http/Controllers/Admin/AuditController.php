<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::with('user')->orderByDesc('id');
        if (($aksi = trim((string) $request->query('aksi'))) !== '') {
            $q->where('aksi', 'like', addcslashes($aksi, '%_\\').'%');
        }

        return view('admin.audit.index', ['items' => $q->simplePaginate(50)->withQueryString()]);
    }
}
