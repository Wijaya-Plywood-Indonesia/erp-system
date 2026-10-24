<?php

namespace App\Services\Akuntansi;

use App\Models\JurnalProduksiKirimLog;
use App\Models\User;
use App\Services\Akuntansi\Produksi\JurnalProduksiRegistry;
use App\Support\ResolveBarangCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim jurnal produksi (jenis apa pun yang terdaftar di
 * JurnalProduksiRegistry) ke API akuntansi: POST /api/jurnal/produksi.
 *
 * Aman dipanggil berulang: akuntansi menimpa jurnal yang masih draft, dan
 * menolak (tanpa mengubah) yang sudah diposting.
 */
class JurnalProduksiSender
{
    public function __construct(private readonly JurnalProduksiRegistry $registry) {}

    /**
     * Jurnal yang akan dikirim + status terhadap pengiriman sebelumnya.
     *
     * Status per jurnal:
     *   belum_dikirim | terkirim (data sama dg yg terakhir dikirim) | berubah (data ERP berbeda sejak dikirim)
     *
     * Untuk tampilan layar ($denganIdBarang = false) pencarian id_barang ke
     * server akuntansi dilewati supaya cepat; id_barang tidak ikut dihitung
     * di hash. Saat kirim ($denganIdBarang = true) id_barang dicari sungguhan.
     *
     * @return array<int, array>
     */
    public function preview(string $jenis, string $tanggal, bool $denganIdBarang = false): array
    {
        $jurnals = $denganIdBarang
            ? $this->registry->get($jenis)->build($tanggal)
            : ResolveBarangCache::tanpaIdBarang(fn () => $this->registry->get($jenis)->build($tanggal));

        foreach ($jurnals as &$j) {
            $rows = $j['rows'];
            $akunTidakValid = $j['akun_tidak_valid'] ?? [];

            $j['jumlah_baris'] = count($rows);
            $j['total_debit'] = (float) collect($rows)->where('map', 'd')->sum('total');
            $j['total_kredit'] = (float) collect($rows)->where('map', 'k')->sum('total');
            $j['is_balance'] = abs($j['total_debit'] - $j['total_kredit']) <= 1;
            $j['has_akun_tidak_valid'] = count($akunTidakValid) > 0;
            $j['akun_tidak_valid'] = $akunTidakValid;
            $j['hash'] = sha1(json_encode(array_map(function ($r) {
                unset($r['id_barang']); // id_barang tidak ikut hash (tidak dicari saat tampilan)

                return $r;
            }, $rows)));

            $terakhir = JurnalProduksiKirimLog::where('no_dokumen', $j['no_dokumen'])
                ->whereIn('status', JurnalProduksiKirimLog::STATUS_BERHASIL)
                ->with('pengirim:id,name')
                ->latest('id')
                ->first();

            $j['terakhir_dikirim'] = $terakhir?->created_at;
            $j['terakhir_oleh'] = $terakhir?->pengirim?->name;
            $j['status'] = match (true) {
                ! $terakhir => 'belum_dikirim',
                $terakhir->payload_hash === $j['hash'] => 'terkirim',
                default => 'berubah',
            };
        }
        unset($j);

        return $jurnals;
    }

    /**
     * Bangun ulang jurnal dari data ERP saat ini, lalu kirim.
     *
     * @return array{success:bool, message:string, dokumen:array}
     */
    public function kirim(string $jenis, string $tanggal, ?int $userId = null): array
    {
        $url = rtrim((string) config('services.akuntansi.url'), '/');
        $key = (string) config('services.akuntansi.key');

        if ($url === '' || $key === '') {
            return $this->gagal('Konfigurasi AKUNTANSI_URL / AKUNTANSI_API_KEY belum diisi di .env ERP.');
        }

        $jurnals = $this->preview($jenis, $tanggal, true);

        if (! $jurnals) {
            return $this->gagal('Belum ada data produksi pada tanggal ini.');
        }

        $tidakBalance = collect($jurnals)->where('is_balance', false)->pluck('no_dokumen')->all();
        if ($tidakBalance) {
            $pesan = 'Jurnal tidak balance (debit != kredit): '.implode(', ', $tidakBalance).'. Tidak dikirim.';
            foreach ($jurnals as $j) {
                $this->catat($jenis, $tanggal, $j, 'gagal', $pesan, $userId);
            }

            return $this->gagal($pesan);
        }

        // Blokir jika ada akun yang belum terpetakan ke COA (UNKNOWN atau akun lama tidak dikenal)
        $adaAkunTidakValid = collect($jurnals)->where('has_akun_tidak_valid', true);
        if ($adaAkunTidakValid->isNotEmpty()) {
            $detail = $adaAkunTidakValid->map(function ($j) {
                $akun = implode(', ', $j['akun_tidak_valid']);

                return "{$j['no_dokumen']}: {$akun}";
            })->implode('; ');
            $pesan = "Akun tidak dikenal di COA pada: {$detail}. Lengkapi referensi harga/COA terlebih dahulu.";
            foreach ($jurnals as $j) {
                $this->catat($jenis, $tanggal, $j, 'gagal', $pesan, $userId);
            }

            return $this->gagal($pesan);
        }

        // Pengirim: dicatat di akuntansi sebagai pembuat jurnal (dicocokkan lewat email/nama).
        $pengirim = $userId ? User::find($userId) : null;

        $payload = [
            'jenis' => $jenis,
            'tanggal' => $tanggal,
            'dikirim_oleh' => $pengirim ? ['id' => $pengirim->id, 'nama' => $pengirim->name, 'email' => $pengirim->email] : null,
            'jurnals' => array_map(fn ($j) => ['no_dokumen' => $j['no_dokumen'], 'rows' => $j['rows']], $jurnals),
        ];

        try {
            $response = Http::withHeaders(['X-API-KEY' => $key, 'Accept' => 'application/json'])
                ->when(! config('services.akuntansi.verify_ssl', true), fn ($r) => $r->withoutVerifying())
                ->timeout(60)
                // kirim ulang aman (idempotent), tapi hanya untuk gagal koneksi
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->post($url.'/api/jurnal/produksi', $payload);
        } catch (\Throwable $e) {
            Log::error('[JurnalProduksi] Koneksi gagal', ['jenis' => $jenis, 'tanggal' => $tanggal, 'error' => $e->getMessage()]);
            $pesan = 'Tidak dapat terhubung ke server akuntansi: '.$e->getMessage();
            foreach ($jurnals as $j) {
                $this->catat($jenis, $tanggal, $j, 'gagal', $pesan, $userId);
            }

            return $this->gagal($pesan);
        }

        $body = $response->json() ?? [];
        $dokumenResp = $body['dokumen'] ?? [];

        if (! $response->successful() && ! $dokumenResp) {
            $pesan = 'HTTP '.$response->status().': '.($body['message'] ?? mb_substr($response->body(), 0, 300));
            Log::error('[JurnalProduksi] Ditolak akuntansi', ['jenis' => $jenis, 'tanggal' => $tanggal, 'status' => $response->status(), 'body' => $body]);
            foreach ($jurnals as $j) {
                $this->catat($jenis, $tanggal, $j, 'gagal', $pesan, $userId);
            }

            return $this->gagal($pesan);
        }

        // Respons sukses/parsial/ditolak: catat hasil per dokumen.
        $ringkas = [];
        $ada = 0;
        $ok = 0;
        foreach ($jurnals as $j) {
            $d = $dokumenResp[$j['no_dokumen']] ?? ['status' => 'gagal', 'message' => 'Tidak ada di respons akuntansi.'];
            $status = match ($d['status'] ?? '') {
                'dibuat' => 'terkirim',
                'diperbarui' => 'diperbarui',
                'ditolak', 'dilewati' => 'ditolak',
                default => 'gagal',
            };
            $this->catat($jenis, $tanggal, $j, $status, $d['message'] ?? null, $userId);

            $ada++;
            $ok += in_array($status, JurnalProduksiKirimLog::STATUS_BERHASIL, true) ? 1 : 0;
            $ringkas[$j['no_dokumen']] = ['status' => $status, 'message' => $d['message'] ?? ''];
        }

        $pesan = $ok === $ada
            ? "Berhasil: {$ok} jurnal terkirim ke akuntansi."
            : "{$ok} dari {$ada} jurnal terkirim. Lihat rincian di bawah.";

        // Peringatan dari akuntansi (mis. pengirim tidak ditemukan) hanya dicatat
        // di log, tidak ditampilkan ke admin.
        if (! empty($body['warnings'])) {
            Log::info('[JurnalProduksi] Peringatan dari akuntansi', ['jenis' => $jenis, 'warnings' => $body['warnings']]);
        }

        return [
            'success' => $ok === $ada,
            'message' => $pesan,
            'dokumen' => $ringkas,
            'peringatan' => [],
        ];
    }

    private function catat(string $jenis, string $tanggal, array $j, string $status, ?string $pesan, ?int $userId): void
    {
        JurnalProduksiKirimLog::create([
            'jenis' => $jenis,
            'tanggal' => $tanggal,
            'no_dokumen' => $j['no_dokumen'],
            'status' => $status,
            'payload_hash' => $j['hash'] ?? null,
            'total_debit' => $j['total_debit'] ?? 0,
            'total_kredit' => $j['total_kredit'] ?? 0,
            'jumlah_baris' => $j['jumlah_baris'] ?? 0,
            'pesan' => $pesan,
            'dikirim_oleh' => $userId,
        ]);
    }

    private function gagal(string $pesan): array
    {
        return ['success' => false, 'message' => $pesan, 'dokumen' => [], 'peringatan' => []];
    }
}
