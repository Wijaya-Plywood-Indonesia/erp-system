<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Filament\Resources\Bms\BmResource;
use App\Models\Bk;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

/** Klik No. BML pada BKL: pop up isi barang BML, lalu tombol Buka BML. */
class ReferensiBmlAction
{
    public static function bml(Bk $bk)
    {
        return $bk->bmTerkait()->where('kategori', 'bml')->with('detail')->first();
    }

    public static function make(): Action
    {
        return Action::make('lihat_bml')
            ->modalHeading(fn (Bk $record) => 'Barang kembali ' . (self::bml($record)?->no_nota ?? '-'))
            ->modalWidth('2xl')
            ->modalContent(function (Bk $record) {
                $bml = self::bml($record);
                if (! $bml) {
                    return new HtmlString('<p>BML belum dibuat.</p>');
                }
                $td = 'style="padding:6px 8px;border-bottom:1px solid #e5e7eb"';
                $rows = '';
                foreach ($bml->detail as $d) {
                    $nama = e(preg_replace('/^' . preg_quote(config('bkbm.prefix_barang_umum'), '/') . '/', '', $d->nama_barang));
                    $jml = e(rtrim(rtrim(number_format((float) $d->jumlah, 3, '.', ''), '0'), '.'));
                    $rows .= "<tr><td $td>$nama</td><td $td align=\"right\">$jml</td><td $td>" . e($d->satuan) . "</td><td $td>" . e($d->keterangan) . '</td></tr>';
                }
                $status = $bml->status === 'divalidasi' ? 'Divalidasi' : 'Draft';
                $head = '<p style="margin-bottom:8px"><b>' . e($bml->no_nota) . '</b> · ' . e($bml->tanggal->format('d M Y')) . ' · ' . $status . '</p>';
                $tabel = $rows !== ''
                    ? '<table style="width:100%;font-size:14px"><thead><tr style="text-align:left"><th style="padding:6px 8px">Barang</th><th style="padding:6px 8px;text-align:right">Jumlah</th><th style="padding:6px 8px">Satuan</th><th style="padding:6px 8px">Keterangan</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                    : '<p>BML ini belum berisi barang.</p>';
                return new HtmlString($head . $tabel);
            })
            ->modalSubmitActionLabel('Buka BML')
            ->modalCancelActionLabel('Tutup')
            ->action(function (Bk $record) {
                $bml = self::bml($record);
                return $bml ? redirect(BmResource::getUrl('view', ['record' => $bml])) : null;
            });
    }
}
