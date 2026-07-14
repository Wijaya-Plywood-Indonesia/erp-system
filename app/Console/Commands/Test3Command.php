<?php

namespace App\Console\Commands;

use App\Models\DetailTurusanKayu;
use App\Models\HppAverageLog;
use App\Models\NotaKayu;
use Illuminate\Console\Command;

class Test3Command extends Command
{
    /**
     * php artisan test3 8
     */
    protected $signature = 'test3 {id=25}';

    protected $description = 'Test Batch Aktif menggunakan Updated At Nota Sebelum Reset sebagai Cutoff (sama seperti getKayuAktif di TempatKayusTable)';

    public function handle(): int
    {
        $lahanId = (int) $this->argument('id');

        /*
        |--------------------------------------------------------------------------
        | 1) Cari reset terakhir (log 'keluar' dengan stok_batang_after = 0)
        |--------------------------------------------------------------------------
        */

        $lastReset = HppAverageLog::where('id_lahan', $lahanId)
            ->where('tipe_transaksi', 'keluar')
            ->where('stok_batang_after', 0)
            ->orderByDesc('id')
            ->first();

        $cutoff = null;
        $lastNotaBeforeReset = null;
        $referensiNota = null;

        if (! $lastReset) {
            $this->warn("Reset tidak ditemukan untuk Lahan ID: {$lahanId}. Menampilkan seluruh data pada lahan tersebut.");
            $this->newLine();
        } else {
            /*
            |--------------------------------------------------------------------------
            | 2) Cari nota terakhir SEBELUM reset (log 'masuk' terakhir dengan id < reset)
            |--------------------------------------------------------------------------
            */

            $lastNotaBeforeReset = HppAverageLog::where('id_lahan', $lahanId)
                ->where('tipe_transaksi', 'masuk')
                ->where('id', '<', $lastReset->id)
                ->orderByDesc('id')
                ->first();

            if (! $lastNotaBeforeReset) {
                // Reset SUDAH terjadi, hanya log acuannya yang tidak ketemu.
                // JANGAN fallback ke "tampilkan semua data". Fallback aman: created_at reset.
                $cutoff = $lastReset->created_at;
                $this->warn('Log masuk sebelum reset tidak ditemukan. Fallback cutoff = waktu reset itu sendiri.');
                $this->info("Reset Log   : {$lastReset->id}");
                $this->info("Cutoff (fallback) : {$cutoff}");
            } elseif (is_null($lastNotaBeforeReset->referensi_type)) {
                // Log ini berasal dari OPNAME STOK KAYU (App\Filament\Pages\OpnameStokKayu),
                // bukan dari NotaKayu asli. Opname tidak punya referensi_id, jadi tidak perlu
                // (dan tidak bisa) dicari ke NotaKayu. Pakai created_at log opname itu sendiri.
                $cutoff = $lastNotaBeforeReset->created_at;

                $this->info("Reset Log             : {$lastReset->id}");
                $this->info("Last Log Before Reset : {$lastNotaBeforeReset->id} (SUMBER: OPNAME STOK KAYU)");
                $this->info("Keterangan Log        : {$lastNotaBeforeReset->keterangan}");
                $this->info("Cutoff (dari opname)  : {$cutoff}");
            } else {
                $referensiId = $lastNotaBeforeReset->referensi_id;

                /*
                |--------------------------------------------------------------------------
                | Ambil NotaKayu dari referensi_id tsb, gunakan updated_at-nya sebagai cutoff
                |--------------------------------------------------------------------------
                */

                $referensiNota = $referensiId ? NotaKayu::find($referensiId) : null;

                if (! $referensiNota) {
                    $cutoff = $lastReset->created_at;
                    $this->warn("NotaKayu dengan ID '{$referensiId}' tidak ditemukan. Fallback cutoff = waktu reset itu sendiri.");
                    $this->info("Reset Log   : {$lastReset->id}");
                    $this->info("Cutoff (fallback) : {$cutoff}");
                } else {
                    $cutoff = $referensiNota->updated_at;

                    $this->info("Reset Log             : {$lastReset->id}");
                    $this->info("Last Log Before Reset : {$lastNotaBeforeReset->id}");
                    $this->info("Referensi ID (Nota)   : {$referensiId}");
                    $this->info("Nota Updated At (cutoff) : {$cutoff}");
                }
            }
        }

        $this->info("LAHAN       : {$lahanId}");
        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | DEBUG: Tampilkan seluruh data HppAverageLog untuk lahan ini
        |--------------------------------------------------------------------------
        */

        $hppLogs = HppAverageLog::where('id_lahan', $lahanId)
            ->orderBy('id')
            ->get();

        $this->line('==================== DATA HPP AVERAGE LOG (Lahan '.$lahanId.') ====================');

        if ($hppLogs->isEmpty()) {
            $this->warn('Tidak ada data HppAverageLog untuk lahan ini.');
        } else {
            $this->info("Total baris HppAverageLog: {$hppLogs->count()}");

            $hppTable = $hppLogs->map(function ($log) use ($lastReset, $lastNotaBeforeReset) {
                return [
                    'ID' => $log->id,
                    'Tipe' => $log->tipe_transaksi,
                    'Ref Type' => $log->referensi_type ? class_basename($log->referensi_type) : 'OPNAME',
                    'Referensi ID' => $log->referensi_id,
                    'Stok Batang Before' => $log->stok_batang_before,
                    'Stok Batang After' => $log->stok_batang_after,
                    'Created At' => $log->created_at,
                    'Keterangan' => $log->keterangan,
                    'Ket' => ($lastReset && $log->id === $lastReset->id)
                        ? '<-- RESET'
                        : (($lastNotaBeforeReset && $log->id === $lastNotaBeforeReset->id)
                            ? '<-- LAST NOTA BEFORE RESET'
                            : ''),
                ];
            })->toArray();

            $this->table([
                'ID',
                'Tipe',
                'Ref Type',
                'Referensi ID',
                'Stok Batang Before',
                'Stok Batang After',
                'Created At',
                'Keterangan',
                'Ket',
            ], $hppTable);
        }
        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | DEBUG: Deteksi OPNAME setelah reset
        | Simple rule: log yang muncul SETELAH reset (id > lastReset->id) dan
        | referensi_id-nya NULL, dianggap berasal dari Opname Stok Kayu.
        |--------------------------------------------------------------------------
        */

        if ($lastReset) {
            $opnameSetelahReset = HppAverageLog::where('id_lahan', $lahanId)
                ->where('id', '>', $lastReset->id)
                ->whereNull('referensi_id')
                ->orderBy('id')
                ->get();

            $this->line('==================== OPNAME SETELAH RESET (referensi_id NULL, id > reset) ====================');
            if ($opnameSetelahReset->isEmpty()) {
                $this->info('Tidak ada opname setelah reset.');
            } else {
                $this->warn("Ditemukan {$opnameSetelahReset->count()} log opname setelah reset:");

                $opnameTable = $opnameSetelahReset->map(function ($log) {
                    return [
                        'ID' => $log->id,
                        'Tipe' => $log->tipe_transaksi,
                        'Stok Batang Before' => $log->stok_batang_before,
                        'Stok Batang After' => $log->stok_batang_after,
                        'Created At' => $log->created_at,
                        'Keterangan' => $log->keterangan,
                    ];
                })->toArray();

                $this->table([
                    'ID',
                    'Tipe',
                    'Stok Batang Before',
                    'Stok Batang After',
                    'Created At',
                    'Keterangan',
                ], $opnameTable);
            }
            $this->newLine();
        }

        /*
        |--------------------------------------------------------------------------
        | LOGIKA (sama persis dengan getKayuAktif() di TempatKayusTable):
        |   - Nota BELUM LUNAS : aktif jika created_at  > cutoff
        |   - Nota SUDAH LUNAS : aktif jika updated_at  > cutoff
        | cutoff = updated_at dari nota terakhir SEBELUM reset (referensi_id)
        |--------------------------------------------------------------------------
        */

        $data = DetailTurusanKayu::with([
            'lahan',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.notaKayu',
        ])
            ->where('lahan_id', $lahanId)
            ->when($cutoff, function ($q) use ($cutoff) {
                $q->whereHas('kayuMasuk.notaKayu', function ($query) use ($cutoff) {
                    $query->where(function ($sub) use ($cutoff) {
                        $sub->where(function ($belumLunas) use ($cutoff) {
                            $belumLunas->where('status_pelunasan', 'not like', 'Lunas%')
                                ->where('created_at', '>', $cutoff);
                        })->orWhere(function ($sudahLunas) use ($cutoff) {
                            $sudahLunas->where('status_pelunasan', 'like', 'Lunas%')
                                ->where('updated_at', '>', $cutoff);
                        });
                    });
                });
            })
            ->get()
            ->groupBy('id_kayu_masuk')
            ->map(function ($rows) {
                $first = $rows->first();

                $statusPelunasan = $first->kayuMasuk?->notaKayu?->status_pelunasan;
                $isLunas = str_starts_with(strtolower(trim($statusPelunasan ?? '')), 'lunas');

                return [
                    'ID Kayu' => $first->id_kayu_masuk,
                    'ID Nota' => $first->kayuMasuk?->notaKayu?->id,
                    'No Nota' => $first->kayuMasuk?->notaKayu?->no_nota,
                    'Kode Lahan' => $first->lahan?->kode_lahan,
                    'Seri' => $first->kayuMasuk?->seri,
                    'Supplier' => trim($first->kayuMasuk?->penggunaanSupplier?->nama_supplier ?? ''),
                    'Status Pelunasan' => $statusPelunasan,
                    'Nota Created At' => $first->kayuMasuk?->notaKayu?->created_at,
                    'Nota Updated At' => $first->kayuMasuk?->notaKayu?->updated_at,
                    'Batang' => (int) $rows->sum('kuantitas'),
                    // round-then-sum per item (4 desimal), konsisten dengan NotaKayuController.
                    'Kubikasi' => (float) $rows->sum(fn ($r) => round($r->kubikasi, 4)),
                    'Panjang' => $rows->pluck('panjang')->unique()->sort()->implode(', '),
                    'Grade' => $rows->pluck('grade')->unique()->sort()->implode(', '),
                    'is_lunas' => $isLunas,
                ];
            })
            ->sortBy('ID Nota')
            ->values();

        $columns = [
            'ID Kayu',
            'ID Nota',
            'No Nota',
            'Kode Lahan',
            'Seri',
            'Supplier',
            'Status Pelunasan',
            'Nota Created At',
            'Nota Updated At',
            'Batang',
            'Kubikasi',
            'Panjang',
            'Grade',
        ];

        $this->line('==================== DATA FINAL (Batch Aktif) ====================');
        if ($data->isEmpty()) {
            $this->warn('Tidak ada data (final).');

            return self::SUCCESS;
        }

        $this->info("Total baris final : {$data->count()}");
        $this->info('Total Batang      : '.$data->sum('Batang'));
        $this->info('Total Kubikasi    : '.round($data->sum('Kubikasi'), 4));
        $this->newLine();

        $this->table($columns, $data->toArray());

        return self::SUCCESS;
    }
}
