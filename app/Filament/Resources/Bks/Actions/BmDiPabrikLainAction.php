<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Models\Bk;
use App\Models\BkbmOutbox;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/** Klik "BM di pabrik lain" pada BK: pop up berisi BM/BMK/BMR di pabrik tujuan (salinan, hanya untuk dibaca). */
class BmDiPabrikLainAction
{
    public static function make(): Action
    {
        return Action::make('lihat_bm_pabrik_lain')
            ->modalHeading(function (Bk $record) {
                $respon = BkbmOutbox::untuk($record->siklus_uuid, 'buat_bm')?->payload['_respon'] ?? [];
                return 'Nota di pabrik lain' . (! empty($respon['no_bm']) ? ': ' . $respon['no_bm'] : '');
            })
            ->modalWidth('2xl')
            ->modalContent(function (Bk $record) {
                $o = BkbmOutbox::untuk($record->siklus_uuid, 'buat_bm');
                $respon = $o?->payload['_respon'] ?? [];
                $kabar = DB::table('bkbm_inbox')->where('jenis', 'status_update')->where('payload->no_bk', $record->no_nota)->latest('id')->first();
                $d = $kabar ? json_decode($kabar->payload, true) : null;

                $no = $respon['no_bm'] ?? $d['no_bm'] ?? null;
                $pabrik = $respon['pabrik'] ?? $d['pabrik'] ?? 'pabrik lain';
                $kat = strtoupper($d['kategori_bm'] ?? ($o->payload['kategori_bm'] ?? ''));

                if (! $d) {
                    return new HtmlString('<p><b>' . e($no ?: 'Nota') . '</b> (' . e($pabrik) . ') belum divalidasi di sana. '
                        . 'Isi barangnya akan tampil di sini setelah divalidasi.</p>');
                }

                $at = ! empty($d['divalidasi_at']) ? \Illuminate\Support\Carbon::parse($d['divalidasi_at'])->format('d M Y H:i') : null;
                $head = '<p style="margin-bottom:8px"><b>' . e($kat) . ' ' . e($no ?? '') . '</b> · ' . e($pabrik)
                    . (! empty($d['tanggal']) ? ' · ' . e(\Illuminate\Support\Carbon::parse($d['tanggal'])->format('d M Y')) : '')
                    . '<br>Divalidasi oleh ' . e($d['divalidasi_oleh'] ?? '-') . ($at ? ' pada ' . e($at) : '') . '</p>';

                if (! array_key_exists('barang', $d)) {
                    return new HtmlString($head . '<p>Kabar ini dikirim sebelum fitur lihat isi tersedia, jadi daftar barang tidak ada. Lihat langsung di pabrik tujuan.</p>');
                }

                $rows = '';
                foreach ($d['barang'] as $b) {
                    $rows .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . e($b['nama_barang'] ?? '') . '</td>'
                        . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">' . e(rtrim(rtrim(number_format((float) ($b['jumlah'] ?? 0), 3, '.', ''), '0'), '.')) . '</td>'
                        . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . e($b['satuan'] ?? '') . '</td>'
                        . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . e($b['keterangan'] ?? '') . '</td></tr>';
                }
                $tabel = $rows !== ''
                    ? '<table style="width:100%;font-size:14px"><thead><tr style="text-align:left"><th style="padding:6px 8px">Barang</th><th style="padding:6px 8px;text-align:right">Jumlah</th><th style="padding:6px 8px">Satuan</th><th style="padding:6px 8px">Keterangan</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                    : '<p>Nota ini tidak berisi barang.</p>';

                return new HtmlString($head . $tabel);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }
}