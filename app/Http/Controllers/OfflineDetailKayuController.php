<?php

namespace App\Http\Controllers;

use App\Models\DetailKayuMasuk;
use App\Models\KayuMasuk; // sesuaikan jika nama model berbeda
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OfflineDetailKayuController extends Controller
{
    private const ROLE_ADMIN = ['super_admin', 'Super Admin'];

    public function sync(Request $request)
    {
        $items = $request->json('items') ?? $request->input('items', []);

        if (empty($items)) {
            $raw = $request->getContent();
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['items'])) {
                    $items = $decoded['items'];
                } elseif (is_array($decoded)) {
                    $items = $decoded;
                }
            }
        }

        if (empty($items) && $request->has('data')) {
            $decoded = json_decode($request->input('data'), true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        abort_unless(is_array($items), 422, 'Format data tidak valid.');
        abort_unless(count($items) <= 2000, 422, 'Maksimal 2000 data per upload.');

        if (empty($items)) {
            return response()->json(['results' => [], 'message' => 'Tidak ada data yang dikirim.'], 400);
        }

        $isAdmin = Auth::user()?->hasAnyRole(self::ROLE_ADMIN) ?? false;
        $results = [];

        foreach ($items as $item) {
            $uuid = $item['uuid'] ?? null;

            $v = Validator::make($item, [
                'uuid' => 'required|uuid',
                'parentId' => 'required|integer|exists:kayu_masuks,id',   // sesuaikan nama tabel
                'id_lahan' => 'required|integer|exists:lahans,id',
                'id_jenis_kayu' => 'required|integer|exists:jenis_kayus,id',   // sesuaikan nama tabel
                'panjang' => 'required|in:0,130,260',
                'grade' => 'required|in:1,2',
                'diameter' => 'required|numeric|gt:0',
                'jumlah_batang' => 'required|integer|min:1',
            ]);

            if ($v->fails()) {
                $results[] = ['uuid' => $uuid, 'ok' => false, 'error' => $v->errors()->first()];

                continue;
            }

            // Sudah pernah masuk? Anggap sukses (idempotent)
            if (DetailKayuMasuk::where('offline_uuid', $uuid)->exists()) {
                $results[] = ['uuid' => $uuid, 'ok' => true, 'duplicate' => true];

                continue;
            }

            // Cek LOCK nota (sama seperti di table)
            $kayuMasuk = KayuMasuk::find($item['parentId']);
            $nota = $kayuMasuk?->notakayu;
            if ($nota && $nota->status !== 'Belum Diperiksa' && ! $isAdmin) {
                $results[] = ['uuid' => $uuid, 'ok' => false, 'error' => 'Nota sudah terkunci.'];

                continue;
            }

            try {
                DetailKayuMasuk::create([
                    'offline_uuid' => $uuid,
                    'id_kayu_masuk' => $item['parentId'],
                    'id_lahan' => $item['id_lahan'],
                    'id_jenis_kayu' => $item['id_jenis_kayu'],
                    'panjang' => $item['panjang'],
                    'grade' => $item['grade'],
                    'diameter' => $item['diameter'],
                    'jumlah_batang' => $item['jumlah_batang'],
                ]);
                $results[] = ['uuid' => $uuid, 'ok' => true];
            } catch (UniqueConstraintViolationException $e) {
                $results[] = ['uuid' => $uuid, 'ok' => true, 'duplicate' => true];
            } catch (\Throwable $e) {
                report($e);
                $results[] = ['uuid' => $uuid, 'ok' => false, 'error' => 'Gagal menyimpan di server.'];
            }
        }

        return response()->json(['results' => $results]);
    }
}
