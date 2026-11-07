<?php

namespace App\Filament\Resources\Bms\Schemas;

use App\Models\Bm;
use App\Models\BkbmOutbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BmInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('no_nota')->label('No. Nota')->weight('bold'),
                TextEntry::make('kategori')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'divalidasi' ? 'Divalidasi' : 'Draft')
                    ->color(fn ($state) => $state === 'divalidasi' ? 'success' : 'gray'),
                TextEntry::make('tanggal')->date(),
                TextEntry::make('dari')->placeholder('-'),
                TextEntry::make('referensi')->label('Referensi BK')->placeholder('-')
                    ->getStateUsing(fn ($record) => $record->bkReferensi?->no_nota
                        ?? ($record->ref_no_bk ? $record->ref_no_bk . ($record->ref_pabrik ? " ({$record->ref_pabrik})" : '') : null))
                    ->color('primary')->weight('bold')
                    ->action(\App\Filament\Resources\Bms\Actions\ReferensiBkAction::make()),
                TextEntry::make('keterangan')->placeholder('-')->columnSpanFull(),
            ])->columns(['default' => 1, 'sm' => 2, 'lg' => 3]),

            Section::make('Riwayat')->icon('heroicon-o-clock')->schema([
                TextEntry::make('dibuat')->label('Dibuat')
                    ->getStateUsing(function (Bm $r) {
                        $siapa = $r->pembuat?->name
                            ?? ($r->ref_no_bk ? 'Otomatis dari ' . $r->ref_no_bk . ($r->ref_pabrik ? " ({$r->ref_pabrik})" : '') : '-');
                        return $siapa . ' · ' . $r->created_at?->format('d M Y H:i');
                    }),
                TextEntry::make('divalidasi')->label('Divalidasi')
                    ->getStateUsing(fn (Bm $r) => $r->sudahDivalidasi()
                        ? ($r->validator?->name ?? '-') . ' · ' . $r->divalidasi_at?->format('d M Y H:i')
                        : null)
                    ->placeholder('Belum divalidasi'),
                TextEntry::make('diubah')->label('Terakhir diubah')
                    ->getStateUsing(fn (Bm $r) => $r->updated_at?->format('d M Y H:i')),
            ])->columns(['default' => 1, 'md' => 3]),

            Section::make('Kabar ke pabrik asal')->icon('heroicon-o-paper-airplane')->schema([
                TextEntry::make('kirim_status')->label('Status')
                    ->getStateUsing(fn (Bm $r) => BkbmOutbox::untuk($r->siklus_uuid, 'status_update')?->teksStatus())
                    ->badge()
                    ->color(fn ($record) => match (BkbmOutbox::untuk($record->siklus_uuid, 'status_update')?->status) {
                        'terkirim' => 'success', 'gagal' => 'danger', default => 'warning',
                    }),
                TextEntry::make('kirim_detail')->label('Penjelasan')
                    ->getStateUsing(fn (Bm $r) => BkbmOutbox::untuk($r->siklus_uuid, 'status_update')?->teksDetail())
                    ->color('warning')
                    ->visible(fn (Bm $r) => BkbmOutbox::untuk($r->siklus_uuid, 'status_update')?->teksDetail() !== null)
                    ->extraAttributes(['style' => 'white-space:pre-line'])
                    ->columnSpanFull(),
            ])->visible(fn (Bm $r) => (bool) BkbmOutbox::untuk($r->siklus_uuid, 'status_update')),
        ]);
    }
}
