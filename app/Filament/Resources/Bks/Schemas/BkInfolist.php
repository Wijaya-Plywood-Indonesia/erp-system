<?php

namespace App\Filament\Resources\Bks\Schemas;

use App\Models\Bk;
use App\Models\BkbmOutbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class BkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('no_nota')->label('No. Nota')->weight('bold'),
                TextEntry::make('kategori')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
                TextEntry::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->labelStatus())
                    ->color(fn ($state, $record) => $record->warna()),
                TextEntry::make('tanggal')->date(),
                TextEntry::make('tujuan_nota')->label('Kepada'),
                TextEntry::make('alamat')->placeholder('-'),
                TextEntry::make('keterangan')->placeholder('-')->columnSpanFull(),
                TextEntry::make('bmr_ref')->label('BMR (barang kembali)')
                    ->getStateUsing(fn (Bk $r) => $r->bmrPengembalian()->first()?->no_nota)
                    ->placeholder('Belum dikembalikan')
                    ->visible(fn (Bk $r) => $r->kategori === 'bkk'),
                TextEntry::make('bkr_asal')->label('Mengembalikan pinjaman dari BMK')
                    ->getStateUsing(fn (Bk $r) => $r->bmkAsal?->no_nota)
                    ->placeholder('-')
                    ->visible(fn (Bk $r) => $r->kategori === 'bkr'),
                TextEntry::make('bml_ref')->label('BML (barang kembali)')
                    ->getStateUsing(fn ($record) => $record->bmTerkait()->where('kategori', 'bml')->first()?->no_nota)
                    ->placeholder('-')
                    ->color('primary')->weight('bold')
                    ->action(\App\Filament\Resources\Bks\Actions\ReferensiBmlAction::make())
                    ->visible(fn ($record) => $record->kategori === 'bkl'),
            ])->columns(['default' => 1, 'sm' => 2, 'lg' => 3]),

            Section::make('Riwayat')->icon('heroicon-o-clock')->schema([
                TextEntry::make('dibuat')->label('Dibuat')
                    ->getStateUsing(fn (Bk $r) => ($r->pembuat?->name ?? '-') . ' · ' . $r->created_at?->format('d M Y H:i')),
                TextEntry::make('divalidasi')->label('Divalidasi')
                    ->getStateUsing(fn (Bk $r) => $r->sudahDivalidasi()
                        ? ($r->validator?->name ?? '-') . ' · ' . $r->divalidasi_at?->format('d M Y H:i')
                        : null)
                    ->placeholder('Belum divalidasi'),
                TextEntry::make('diubah')->label('Terakhir diubah')
                    ->getStateUsing(fn (Bk $r) => $r->updated_at?->format('d M Y H:i')),
                TextEntry::make('selesai_manual')->label('Ditandai selesai manual')
                    ->getStateUsing(fn (Bk $r) => $r->selesai_manual_at
                        ? (\App\Models\User::find($r->selesai_manual_oleh)?->name ?? '-') . ' · ' . $r->selesai_manual_at->format('d M Y H:i') . ' · ' . $r->selesai_manual_alasan
                        : null)
                    ->visible(fn (Bk $r) => (bool) $r->selesai_manual_at)
                    ->columnSpanFull(),
                TextEntry::make('bm_tujuan')->label('BM di pabrik lain')
                    ->getStateUsing(fn (Bk $r) => self::bmDiPabrikLain($r))
                    ->color('primary')->weight('bold')
                    ->action(\App\Filament\Resources\Bks\Actions\BmDiPabrikLainAction::make())
                    ->visible(fn (Bk $r) => (bool) BkbmOutbox::untuk($r->siklus_uuid, 'buat_bm'))
                    ->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 3]),

            Section::make('Pengiriman ke pabrik lain')->icon('heroicon-o-paper-airplane')->schema([
                TextEntry::make('kirim_status')->label('Status')
                    ->getStateUsing(fn (Bk $r) => BkbmOutbox::untuk($r->siklus_uuid, 'buat_bm')?->teksStatus())
                    ->badge()
                    ->color(fn ($record) => match (BkbmOutbox::untuk($record->siklus_uuid, 'buat_bm')?->status) {
                        'terkirim' => 'success', 'gagal' => 'danger', default => 'warning',
                    }),
                TextEntry::make('kirim_detail')->label('Penjelasan')
                    ->getStateUsing(fn (Bk $r) => BkbmOutbox::untuk($r->siklus_uuid, 'buat_bm')?->teksDetail())
                    ->color('warning')
                    ->visible(fn (Bk $r) => BkbmOutbox::untuk($r->siklus_uuid, 'buat_bm')?->teksDetail() !== null)
                    ->extraAttributes(['style' => 'white-space:pre-line'])
                    ->columnSpanFull(),
            ])->visible(fn (Bk $r) => (bool) BkbmOutbox::untuk($r->siklus_uuid, 'buat_bm')),
        ]);
    }

    /** Nomor BM di pabrik tujuan (dari jawaban penerima) dan kabar terakhir bila BM sudah divalidasi di sana. */
    private static function bmDiPabrikLain(Bk $bk): ?string
    {
        $o = BkbmOutbox::untuk($bk->siklus_uuid, 'buat_bm');
        if (! $o) {
            return null;
        }
        $respon = $o->payload['_respon'] ?? [];
        $no = $respon['no_bm'] ?? null;
        $pabrik = $respon['pabrik'] ?? null;

        $kabar = DB::table('bkbm_inbox')->where('jenis', 'status_update')->where('payload->no_bk', $bk->no_nota)->latest('id')->first();
        $d = $kabar ? json_decode($kabar->payload, true) : null;

        $no ??= $d['no_bm'] ?? null;
        $pabrik ??= $d['pabrik'] ?? null;
        if (! $no) {
            return $o->status === 'terkirim' ? 'Sudah terkirim, nomor BM belum diketahui.' : 'Belum terbentuk, menunggu pengiriman berhasil.';
        }

        $nama = $no . ($pabrik ? " ({$pabrik})" : '');
        if ($d) {
            $oleh = $d['divalidasi_oleh'] ?? null;
            $at = ! empty($d['divalidasi_at']) ? \Illuminate\Support\Carbon::parse($d['divalidasi_at'])->format('d M Y H:i') : null;
            return $nama . ' · sudah divalidasi' . ($oleh ? " oleh {$oleh}" : '') . ($at ? " pada {$at}" : '');
        }
        return $nama . ' · menunggu diisi dan divalidasi di sana';
    }
}
