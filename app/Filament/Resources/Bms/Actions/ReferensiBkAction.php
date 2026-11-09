<?php

namespace App\Filament\Resources\Bms\Actions;

use App\Filament\Resources\Bks\BkResource;
use App\Models\Bm;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

/** Klik No. Referensi BK pada BM: muncul pop up isi barang BK, dengan tombol menuju BK. */
class ReferensiBkAction
{
    public static function make(): Action
    {
        return Action::make('lihat_bk')
            ->modalHeading(fn (Bm $record) => 'Referensi ' . ($record->bkReferensi?->no_nota ?? $record->ref_no_bk ?? '-'))
            ->modalWidth('2xl')
            ->modalContent(function (Bm $record) {
                $bk = $record->bkReferensi;
                $snap = $record->ref_snapshot;
                if (! $bk && ! $snap) {
                    return new HtmlString('<p>Tidak ada referensi.</p>');
                }
                // BK dari pabrik lain: isinya berupa salinan yang dikirim saat BK divalidasi (hanya untuk dibaca).
                $daftar = $bk
                    ? $bk->detail->map(fn ($d) => (object) ['nama_barang' => $d->nama_barang, 'jumlah' => $d->jumlah, 'satuan' => $d->satuan, 'keterangan' => $d->keterangan])
                    : collect($snap['barang'] ?? [])->map(fn ($d) => (object) $d);
                $kat = $bk?->kategori ?? ($snap['kategori'] ?? '');
                $tgl = $bk ? $bk->tanggal->format('d M Y') : ($snap['tanggal'] ?? '');
                $tuj = $bk?->tujuan_nota ?? ($snap['tujuan_nota'] ?? '');
                $rows = '';
                foreach ($daftar as $d) {
                    $nama = e(preg_replace('/^' . preg_quote(config('bkbm.prefix_barang_umum'), '/') . '/', '', $d->nama_barang));
                    $rows .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . $nama . '</td>'
                        . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">' . e(rtrim(rtrim(number_format((float) $d->jumlah, 3, '.', ''), '0'), '.')) . '</td>'
                        . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . e($d->satuan) . '</td>'
                        . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . e($d->keterangan) . '</td></tr>';
                }
                $head = '<p style="margin-bottom:8px"><b>' . e(strtoupper($kat)) . '</b> · ' . e($tgl) . ' · Kepada: ' . e($tuj)
                    . ($bk ? '' : ' · dari ' . e($record->ref_pabrik ?? 'pabrik lain')) . '</p>';
                $tabel = $rows !== ''
                    ? '<table style="width:100%;font-size:14px"><thead><tr style="text-align:left"><th style="padding:6px 8px">Barang</th><th style="padding:6px 8px;text-align:right">Jumlah</th><th style="padding:6px 8px">Satuan</th><th style="padding:6px 8px">Keterangan</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                    : '<p>BK ini belum berisi barang.</p>';
                return new HtmlString($head . $tabel);
            })
            ->modalSubmitAction(fn (Bm $record) => $record->id_bk_referensi ? null : false)
            ->modalSubmitActionLabel('Buka BK')
            ->modalCancelActionLabel('Tutup')
            ->action(fn (Bm $record) => $record->id_bk_referensi
                ? redirect(BkResource::getUrl('view', ['record' => $record->id_bk_referensi]))
                : null);
    }
}
