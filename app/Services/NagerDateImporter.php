<?php

namespace App\Services;

use App\Models\HariLibur;
use Illuminate\Support\Facades\Http;

class NagerDateImporter
{
    public static function import(int $year)
    {
        // 1. Coba ambil dari Nager Date
        $data = self::fromNager($year);

        // 2. Jika gagal → fallback ke GitHub Indonesia
        if (isset($data['error']) || empty($data)) {
            $data = self::fromGitHub($year);
        }

        // 3. Jika tetap gagal
        if (isset($data['error']) || empty($data)) {
            return [
                'error' => true,
                'message' => 'Tidak dapat mengambil data dari Nager ataupun GitHub.',
            ];
        }

        // 4. Simpan ke database
        $saved = 0;
        foreach ($data as $item) {
            HariLibur::updateOrCreate(
                ['date' => $item['date']],
                [
                    'name' => $item['name'],
                    'type' => $item['type'] ?? 'national',
                    'is_repeat_yearly' => false,
                    'source' => $item['source'],
                ]
            );

            $saved++;
        }

        return [
            'error' => false,
            'message' => "Berhasil import {$saved} hari libur.",
            'count' => $saved
        ];
    }

    // ================================================
    // SOURCE 1: Nager Date API
    // ================================================
    private static function fromNager(int $year)
    {
        try {
            $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/ID";
            $response = Http::timeout(5)->get($url);

            if ($response->failed()) {
                throw new \Exception("Nager API gagal diakses.");
            }

            return collect($response->json())->map(function ($i) {
                return [
                    'date' => $i['date'],
                    'name' => $i['localName'],
                    'type' => 'national',
                    'source' => 'Nager.Date API',
                ];
            })->toArray();

        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ================================================
    // SOURCE 2: GitHub Indonesia (Fallback)
    // ================================================
    private static function fromGitHub(int $year)
    {
        try {
            $url = "https://raw.githubusercontent.com/guangrei/APIHariLibur/main/calendar/{$year}.json";
            $response = Http::timeout(5)->get($url);

            if ($response->failed()) {
                throw new \Exception("GitHub API gagal diakses.");
            }

            return collect($response->json())->map(function ($i) {
                return [
                    'date' => $i['tanggal'],
                    'name' => $i['keterangan'],
                    'type' => $i['jenis'] ?? 'national',
                    'source' => 'GitHub Hari Libur Indonesia',
                ];
            })->toArray();

        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
