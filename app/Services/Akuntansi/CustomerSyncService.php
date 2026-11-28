<?php

namespace App\Services\Akuntansi;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomerSyncService
{
    /**
     * Sinkronkan customer (nama, alamat, npwp) ke web Akuntansi secara instan.
     */
    public function syncCustomer(string $nama, ?string $alamat = null, ?string $npwp = null, ?string $telepon = null): ?array
    {
        $nama = trim($nama);
        if ($nama === '') {
            return null;
        }

        // 1. Simpan / perbarui juga di master Customer lokal erp-system
        try {
            $customer = Customer::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])->first();
            if ($customer) {
                $customerUpdated = false;
                if (!empty($alamat) && (empty($customer->alamat) || $customer->alamat !== $alamat)) {
                    $customer->alamat = $alamat;
                    $customerUpdated = true;
                }
                if (!empty($npwp) && (empty($customer->npwp) || $customer->npwp !== $npwp)) {
                    $customer->npwp = $npwp;
                    $customerUpdated = true;
                }
                if (!empty($telepon) && (empty($customer->telepon) || $customer->telepon !== $telepon)) {
                    $customer->telepon = $telepon;
                    $customerUpdated = true;
                }
                if ($customerUpdated) {
                    $customer->save();
                }
            } else {
                $customer = Customer::create([
                    'nama' => $nama,
                    'alamat' => $alamat,
                    'npwp' => $npwp,
                    'telepon' => $telepon,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal update master Customer lokal ERP: ' . $e->getMessage());
        }

        // 2. Kirim langsung ke Web Akuntansi
        $rawUrl = (string) config('services.akuntansi.url', 'http://localhost:8080');
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'http://' . $rawUrl;
        }
        $endpoint = rtrim($rawUrl, '/') . '/api/customer/resolve-or-create';
        $apiKey = (string) config('services.akuntansi.key');

        $payload = [
            'nama' => $nama,
            'alamat' => $alamat,
            'npwp' => $npwp,
            'telepon' => $telepon,
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json('data');
                Log::info("Berhasil sync customer ke Akuntansi: {$nama}", ['data' => $data]);
                return $data;
            }

            Log::warning("Gagal sync customer ke Akuntansi: {$nama}. Response: " . $response->body());
        } catch (\Throwable $e) {
            Log::warning("Exception saat sync customer ke Akuntansi: {$nama}. Error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Ambil data customer dari Akuntansi jika belum ada di ERP.
     */
    public function getCustomerFromAkuntansi(string $nama): ?array
    {
        $nama = trim($nama);
        if ($nama === '') {
            return null;
        }

        $rawUrl = (string) config('services.akuntansi.url', 'http://localhost:8080');
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'http://' . $rawUrl;
        }
        $endpoint = rtrim($rawUrl, '/') . '/api/customer/list?q=' . urlencode($nama);
        $apiKey = (string) config('services.akuntansi.key');

        try {
            $response = Http::withoutVerifying()
                ->timeout(5)
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($endpoint);

            if ($response->successful()) {
                $customers = $response->json('data') ?? [];
                foreach ($customers as $c) {
                    if (mb_strtolower(trim($c['nama'])) === mb_strtolower($nama)) {
                        return $c;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Exception getCustomerFromAkuntansi: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Ambil daftar semua nama customer (gabungan lokal ERP + Akuntansi) untuk datalist autocomplete.
     */
    public static function getCustomerOptions(): array
    {
        $erpNames = Customer::pluck('nama')->toArray();

        try {
            $rawUrl = (string) config('services.akuntansi.url', 'http://localhost:8080');
            if (!preg_match('#^https?://#i', $rawUrl)) {
                $rawUrl = 'http://' . $rawUrl;
            }
            $endpoint = rtrim($rawUrl, '/') . '/api/customer/list';
            $apiKey = (string) config('services.akuntansi.key');

            $response = Http::withoutVerifying()
                ->timeout(3)
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($endpoint);

            if ($response->successful()) {
                $akuntansiList = $response->json('data') ?? [];
                $akuntansiNames = array_column($akuntansiList, 'nama');
                return array_values(array_unique(array_merge($erpNames, $akuntansiNames)));
            }
        } catch (\Throwable $e) {
            // Silently fall back to erpNames
        }

        return $erpNames;
    }

    /**
     * Cari daftar nama customer (gabungan lokal ERP + Akuntansi) berdasarkan kata kunci untuk Select searchable.
     */
    public static function searchCustomerOptions(string $search): array
    {
        $erpNames = Customer::query()
            ->where('nama', 'like', "%{$search}%")
            ->orderBy('nama')
            ->limit(50)
            ->pluck('nama')
            ->toArray();

        $akuntansiNames = [];

        try {
            $rawUrl = (string) config('services.akuntansi.url', 'http://localhost:8080');
            if (!preg_match('#^https?://#i', $rawUrl)) {
                $rawUrl = 'http://' . $rawUrl;
            }
            $endpoint = rtrim($rawUrl, '/') . '/api/customer/list';
            $apiKey = (string) config('services.akuntansi.key');

            $response = Http::withoutVerifying()
                ->timeout(3)
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($endpoint, ['q' => $search]);

            if ($response->successful()) {
                $akuntansiList = $response->json('data') ?? [];
                $akuntansiNames = array_column($akuntansiList, 'nama');
            }
        } catch (\Throwable $e) {
            // Silently fall back to erpNames
        }

        $merged = array_values(array_unique(array_merge($erpNames, $akuntansiNames)));
        sort($merged);

        return array_combine($merged, $merged) ?: [];
    }
}
