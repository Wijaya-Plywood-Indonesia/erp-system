<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BkBm\BkBmKirimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BkbmPesanController extends Controller
{
    public function terima(Request $request, BkBmKirimService $svc)
    {
        $kunci = (string) config('bkbm.partner_key');
        if ($kunci === '' || ! hash_equals($kunci, (string) $request->header('X-API-KEY'))) {
            return response()->json(['ok' => false, 'error' => 'Tidak berwenang.'], 401);
        }

        $v = Validator::make($request->all(), [
            'uuid' => 'required|uuid',
            'siklus_uuid' => 'required|uuid',
            'jenis' => 'required|in:buat_bm,status_update',
            'dari_pabrik' => 'nullable|string|max:50',
            'payload' => 'required|array',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'error' => $v->errors()], 422);
        }

        try {
            return response()->json($svc->terima($v->validated()));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
