<?php

namespace App\Support;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\RequestInterface;

/**
 * Pengatur panggilan GET /api/barang/resolve-* ke web akuntansi (pencarian
 * id_barang) yang dipakai semua sheet jurnal.
 *
 * 1) Mode lewati ($lewati = true): panggilan TIDAK dikirim ke jaringan,
 *    langsung dijawab {"id_barang": null}. Dipakai saat menampilkan
 *    ringkasan di layar, yang tidak butuh id_barang.
 * 2) Cache bersama: hasil ditemukan disimpan 1 jam, hasil kosong 2 menit,
 *    gagal/timeout tidak disimpan.
 */
class ResolveBarangCache
{
    public const TTL_DITEMUKAN = 3600;

    public const TTL_KOSONG = 120;

    public static bool $lewati = false;

    /** Jalankan $fn tanpa memanggil server akuntansi untuk id_barang. */
    public static function tanpaIdBarang(callable $fn): mixed
    {
        $sebelum = self::$lewati;
        self::$lewati = true;

        try {
            return $fn();
        } finally {
            self::$lewati = $sebelum;
        }
    }

    public static function middleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                if ($request->getMethod() !== 'GET'
                    || ! str_contains($request->getUri()->getPath(), '/api/barang/resolve-')) {
                    return $handler($request, $options);
                }

                if (self::$lewati) {
                    return Create::promiseFor(
                        new Response(200, ['Content-Type' => 'application/json'], '{"id_barang":null}')
                    );
                }

                $key = 'resolve_barang:'.sha1((string) $request->getUri());

                $tersimpan = Cache::get($key);
                if ($tersimpan !== null) {
                    return Create::promiseFor(
                        new Response(200, ['Content-Type' => 'application/json'], $tersimpan)
                    );
                }

                return $handler($request, $options)->then(function ($response) use ($key) {
                    if ($response->getStatusCode() === 200) {
                        $body = (string) $response->getBody();
                        $response->getBody()->rewind();

                        $json = json_decode($body, true);
                        $ttl = is_array($json) && ! empty($json['id_barang'])
                            ? self::TTL_DITEMUKAN
                            : self::TTL_KOSONG;

                        Cache::put($key, $body, $ttl);
                    }

                    return $response;
                });
            };
        };
    }
}