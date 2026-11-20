<?php

namespace App\Http\Controllers;

use App\Models\DetailTurusanKayu;
use App\Models\KayuMasuk;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OfflineDetailTurusanController extends Controller
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
                'parentId' => 'required|exists:kayu_masuks,id',
                'lahan_id' => 'required|integer|exists:lahans,id',
                'jenis_kayu_id' => 'required|integer|exists:jenis_kayus,id',
                'panjang' => 'required|in:0,130,260',
                'grade' => 'required|in:1,2',
                'diameter' => 'required|numeric|gt:0',
                'kuantitas' => 'required|integer|min:1',
            ]);

            if ($v->fails()) {
                $results[] = ['uuid' => $uuid, 'ok' => false, 'error' => $v->errors()->first()];

                continue;
            }

            if (DetailTurusanKayu::where('offline_uuid', $uuid)->exists()) {
                $results[] = ['uuid' => $uuid, 'ok' => true, 'duplicate' => true];

                continue;
            }

            // Cek LOCK nota (sama seperti DetailTurusanKayusTable)
            $kayuMasuk = KayuMasuk::find($item['parentId']);
            $locked = $kayuMasuk?->notaKayu
                && str_contains($kayuMasuk->notaKayu->status ?? '', 'Sudah Diperiksa');
            if ($locked && ! $isAdmin) {
                $results[] = ['uuid' => $uuid, 'ok' => false, 'error' => 'Nota sudah terkunci.'];

                continue;
            }

            try {
                DB::transaction(function () use ($item, $uuid) {
                    $next = (int) DetailTurusanKayu::where('id_kayu_masuk', $item['parentId'])
                        ->lockForUpdate()
                        ->max('nomer_urut') + 1;

                    // harga diisi otomatis oleh event creating di model
                    DetailTurusanKayu::create([
                        'offline_uuid' => $uuid,
                        'id_kayu_masuk' => $item['parentId'],
                        'nomer_urut' => $next,
                        'lahan_id' => $item['lahan_id'],
                        'jenis_kayu_id' => $item['jenis_kayu_id'],
                        'panjang' => $item['panjang'],
                        'grade' => $item['grade'],
                        'diameter' => $item['diameter'],
                        'kuantitas' => $item['kuantitas'],
                    ]);
                });
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
