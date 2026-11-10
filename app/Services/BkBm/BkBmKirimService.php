<?php

namespace App\Services\BkBm;

use App\Jobs\KirimBkbmOutboxJob;
use App\Models\Bk;
use App\Models\Bm;
use App\Models\BkbmOutbox;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pengiriman antar pabrik: kotak keluar (outbox) di pengirim, kotak masuk (inbox) di penerima.
 * Dua jenis pesan: buat_bm (BK divalidasi -> BM kosong di pabrik lain)
 *                  status_update (BM divalidasi -> BK asal jadi selesai).
 */
class BkBmKirimService
{
    private const MAKS_PERCOBAAN = 12;

    public static function aktif(): bool
    {
        return (bool) config('bkbm.kirim_aktif') && config('bkbm.partner_url') && config('bkbm.partner_key');
    }

    /* ───────────── Pengirim ───────────── */

    private function antri(string $jenis, string $siklus, array $payload): void
    {
        $row = BkbmOutbox::create([
            'uuid' => (string) Str::uuid(),
            'siklus_uuid' => $siklus,
            'jenis' => $jenis,
            'payload' => $payload,
            'status' => 'pending',
            'kirim_berikutnya_at' => now(),
        ]);
        // Dikirim langsung (sync) setelah transaksi selesai, jadi tidak bergantung pada worker antrean.
        // Gagal kirim tidak membatalkan validasi: pesan tetap di kotak keluar dan dicoba ulang.
        KirimBkbmOutboxJob::dispatch($row->id)->onConnection('sync')->afterCommit();
    }

    /** Dipanggil dalam transaksi validasi BK. */
    public function antriDariBk(Bk $bk): void
    {
        $lawan = config("bkbm.kategori_bk.{$bk->kategori}.bm_lawan");
        if (! self::aktif() || ! $lawan || ! config("bkbm.kategori_bk.{$bk->kategori}.kirim")) {
            return;
        }
        $prefix = config('bkbm.prefix_barang_umum');
        $this->antri('buat_bm', $bk->siklus_uuid, [
            'no_bk' => $bk->no_nota,
            'kategori_bk' => $bk->kategori,
            'kategori_bm' => $lawan,
            'ref_no_bkk' => $bk->kategori === 'bkr' ? $bk->ref_no_bkk : null, // BKR: nomor BKK asal di pabrik penerima
            'tanggal' => $bk->tanggal->format('Y-m-d'),
            'dari' => config('bkbm.pabrik'),
            'tujuan_nota' => $bk->tujuan_nota,
            'keterangan' => $bk->keterangan,
            'barang' => $bk->detail->map(fn ($d) => [
                'nama_barang' => preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $d->nama_barang),
                'jumlah' => (float) $d->jumlah,
                'satuan' => $d->satuan,
                'keterangan' => $d->keterangan,
            ])->values()->all(),
        ]);
    }

    /** Dipanggil dalam transaksi validasi BM yang berasal dari BK pabrik lain. */
    public function antriDariBm(Bm $bm): void
    {
        if (! self::aktif() || ! $bm->ref_no_bk) {
            return;
        }
        $this->antri('status_update', $bm->siklus_uuid, [
            'no_bk' => $bm->ref_no_bk,
            'no_bm' => $bm->no_nota,
            'status' => 'bm_divalidasi',
            'kategori_bm' => $bm->kategori,
            'pabrik' => config('bkbm.pabrik'),
            'divalidasi_oleh' => $bm->validator?->name,
            'divalidasi_at' => $bm->divalidasi_at?->format('Y-m-d H:i:s'),
            'tanggal' => $bm->tanggal?->format('Y-m-d'),
            'keterangan' => $bm->keterangan,
            // Isi BM yang sudah divalidasi, supaya pabrik asal bisa melihatnya (hanya untuk dibaca).
            'barang' => $bm->detail()->get()->map(fn ($d) => [
                'nama_barang' => preg_replace('/^' . preg_quote(config('bkbm.prefix_barang_umum'), '/') . '/', '', $d->nama_barang),
                'jumlah' => (float) $d->jumlah,
                'satuan' => $d->satuan,
                'keterangan' => $d->keterangan,
            ])->values()->all(),
        ]);
    }

    /** Kirim satu pesan. Gagal = dijadwalkan ulang dengan jeda bertambah, tidak pernah hilang diam-diam. */
    public function kirim(BkbmOutbox $row): bool
    {
        if ($row->status === 'terkirim') {
            return true;
        }
        try {
            $res = Http::timeout(15)
                ->withHeaders(['X-API-KEY' => config('bkbm.partner_key'), 'Accept' => 'application/json'])
                ->post(rtrim(config('bkbm.partner_url'), '/') . '/api/bkbm/pesan', [
                    'uuid' => $row->uuid,
                    'siklus_uuid' => $row->siklus_uuid,
                    'jenis' => $row->jenis,
                    'dari_pabrik' => config('bkbm.pabrik'),
                    'payload' => $row->payload,
                ]);

            if ($res->successful() && $res->json('ok') === true) {
                // Jawaban penerima (nomor BM yang terbentuk, nama pabrik) disimpan untuk ditampilkan di BK.
                $row->update([
                    'status' => 'terkirim',
                    'terkirim_at' => now(),
                    'error' => null,
                    'payload' => ($row->payload ?? []) + ['_respon' => $res->json()],
                ]);
                return true;
            }
            $error = self::jelaskan($res, null);
            Log::warning('BKBM kirim gagal', ['outbox' => $row->id, 'http' => $res->status(), 'body' => Str::limit((string) $res->body(), 500)]);
        } catch (\Throwable $e) {
            $error = self::jelaskan(null, $e);
            Log::warning('BKBM kirim gagal', ['outbox' => $row->id, 'error' => $e->getMessage()]);
        }

        $n = $row->percobaan + 1;
        $row->update([
            'percobaan' => $n,
            'error' => $error,
            'status' => $n >= self::MAKS_PERCOBAAN ? 'gagal' : 'pending',
            'kirim_berikutnya_at' => now()->addMinutes(min(2 ** $n, 60)),
        ]);
        return false;
    }

    /** Ubah kegagalan teknis menjadi kalimat yang dimengerti admin. Detail teknis ada di storage/logs. */
    private static function jelaskan(?Response $res, ?\Throwable $e): string
    {
        if ($e) {
            if ($e instanceof ConnectionException) {
                $m = strtolower($e->getMessage());
                if (str_contains($m, 'timed out') || str_contains($m, 'timeout')) {
                    return 'Pabrik tujuan terlalu lama menjawab (lebih dari 15 detik). Servernya mungkin sedang sibuk atau koneksi internet lambat.';
                }
                return 'Pabrik tujuan tidak bisa dihubungi. Server di sana mungkin mati, internet terputus, atau alamat tujuan salah ('
                    . config('bkbm.partner_url') . ').';
            }
            return 'Terjadi kesalahan saat mengirim: ' . Str::limit($e->getMessage(), 150);
        }

        $s = $res->status();
        $pesan = $res->json('error');
        $pesan = is_string($pesan) ? $pesan : ($res->json('message') ?: null);

        return match (true) {
            $s === 401 => 'Ditolak pabrik tujuan: kunci rahasia tidak cocok. Samakan BKBM_PARTNER_KEY di kedua pabrik.',
            $s === 404 => 'Alamat penerima tidak ditemukan di pabrik tujuan. Biasanya aplikasi di sana belum diperbarui, atau alamat tujuan salah.',
            $s === 422 => 'Data ditolak pabrik tujuan' . ($pesan ? ': ' . Str::limit($pesan, 200) : '.'),
            $s === 429 => 'Pabrik tujuan sedang membatasi permintaan. Akan dicoba lagi.',
            $s >= 500 => "Pabrik tujuan mengalami error (kode {$s}). Hubungi admin sistem di sana" . ($pesan ? '. Pesan: ' . Str::limit($pesan, 150) : '.'),
            default => "Pabrik tujuan membalas dengan kode {$s}.",
        };
    }

    /* ───────────── Penerima ───────────── */

    public function terima(array $pesan): array
    {
        return DB::transaction(function () use ($pesan) {
            $sudah = DB::table('bkbm_inbox')->where('uuid', $pesan['uuid'])->lockForUpdate()->exists();
            if ($sudah) {
                return ['ok' => true, 'duplikat' => true];
            }

            $hasil = match ($pesan['jenis']) {
                'buat_bm' => $this->terimaBuatBm($pesan),
                'status_update' => $this->terimaStatusUpdate($pesan),
                default => throw new \InvalidArgumentException('Jenis pesan tidak dikenal.'),
            };

            DB::table('bkbm_inbox')->insert([
                'uuid' => $pesan['uuid'],
                'jenis' => $pesan['jenis'],
                'payload' => json_encode($pesan['payload']),
                'diproses_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['ok' => true] + $hasil + ['pabrik' => config('bkbm.pabrik')];
        });
    }

    private function terimaBuatBm(array $p): array
    {
        $d = $p['payload'];
        if (! in_array($d['kategori_bm'], array_keys(config('bkbm.kategori_bm')), true)) {
            throw new \InvalidArgumentException('Kategori BM tidak valid.');
        }
        // BMR: cari BKK asal di pabrik ini, supaya BKK bisa jadi hijau saat BMR divalidasi.
        $idBkkAsal = null;
        if ($d['kategori_bm'] === 'bmr') {
            $bkk = Bk::where('kategori', 'bkk')->where('no_nota', $d['ref_no_bkk'] ?? '')->first();
            if (! $bkk) {
                throw new \InvalidArgumentException('BKK asal "' . ($d['ref_no_bkk'] ?? '-') . '" tidak ditemukan di pabrik ini.');
            }
            $idBkkAsal = $bkk->id;
        }

        // BM kosong: isi barang diisi manual oleh penerima. Salinan BK asal hanya untuk dibaca.
        $bm = BkBmService::buatBm([
            'id_bkk_asal' => $idBkkAsal,
            'siklus_uuid' => $p['siklus_uuid'],
            'tanggal' => now()->format('Y-m-d'),
            'dari' => $p['dari_pabrik'] ?? $d['dari'] ?? null,
            'keterangan' => null,
            'ref_no_bk' => $d['no_bk'],
            'ref_pabrik' => $p['dari_pabrik'] ?? $d['dari'] ?? null,
            'ref_snapshot' => [
                'kategori' => $d['kategori_bk'],
                'tanggal' => $d['tanggal'],
                'tujuan_nota' => $d['tujuan_nota'] ?? null,
                'keterangan' => $d['keterangan'] ?? null,
                'barang' => $d['barang'] ?? [],
            ],
        ], $d['kategori_bm'], null, false);

        return ['no_bm' => $bm->no_nota];
    }

    private function terimaStatusUpdate(array $p): array
    {
        $d = $p['payload'];
        $bk = Bk::where('siklus_uuid', $p['siklus_uuid'])->where('no_nota', $d['no_bk'])->lockForUpdate()->first();
        if (! $bk) {
            throw new \InvalidArgumentException('BK asal tidak ditemukan.');
        }
        if ($bk->status === 'divalidasi') {
            // BKK: BMK sudah divalidasi di sana = kuning (dipinjam). Kategori lain = selesai.
            $baru = ($bk->kategori === 'bkk' && ($d['kategori_bm'] ?? null) === 'bmk') ? 'dipinjam' : 'selesai';
            $bk->update(['status' => $baru]);
        }
        return [];
    }
}