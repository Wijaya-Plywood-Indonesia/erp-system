<?php

namespace App\Filament\Resources\Bks\Tables;

use App\Models\Bk;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Bk $r) => \App\Filament\Resources\Bks\BkResource::getUrl('view', ['record' => $r]))
            ->recordClasses(fn (Bk $r) => match ($r->warna()) {
                'danger' => 'bg-red-50 dark:bg-red-950/30',
                'success' => 'bg-green-50 dark:bg-green-950/30',
                default => null,
            })
            ->columns([
                // Tampilan HP: satu kolom ringkas (nomor + kategori + tujuan + tanggal), kolom lain disembunyikan.
                TextColumn::make('ringkas')->label('Nota')->hiddenFrom('md')
                    ->getStateUsing(fn (Bk $r) => $r->no_nota)->weight('bold')
                    ->searchable(['no_nota', 'tujuan_nota'])
                    ->description(fn (Bk $r) => strtoupper($r->kategori) . ' · ke ' . $r->tujuan_nota . ' · ' . $r->tanggal->format('d M Y')
                        . ($r->status === 'divalidasi' && $r->divalidasi_at ? ' · ' . (int) $r->divalidasi_at->diffInDays(now()) . ' hari di luar' : ''))
                    ->wrap(),
                TextColumn::make('tanggal')->date('d M Y')->sortable()->visibleFrom('md'),
                TextColumn::make('no_nota')->label('No. Nota')->searchable()->weight('bold')->visibleFrom('md'),
                TextColumn::make('kategori')->label('Kategori')->weight('bold')->visibleFrom('md')
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state))
                    ->color(fn ($state) => match ($state) { 'bkl' => 'info', 'bkk' => 'warning', default => 'gray' }),
                TextColumn::make('tujuan_nota')->label('Kepada')->searchable()->visibleFrom('md'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state, Bk $r) => $r->labelStatus())
                    ->color(fn ($state, Bk $r) => $r->warna()),
                TextColumn::make('hari_di_luar')->label('Di luar')->visibleFrom('md')
                    ->getStateUsing(fn (Bk $r) => $r->status === 'divalidasi' && $r->divalidasi_at
                        ? (int) $r->divalidasi_at->diffInDays(now()) . ' hari' : null)
                    ->placeholder('-')
                    ->color(fn (Bk $r) => $r->status === 'divalidasi' && $r->divalidasi_at && $r->divalidasi_at->diffInDays(now()) > 30 ? 'danger' : 'gray')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('divalidasi_at', $direction === 'asc' ? 'desc' : 'asc');
                    }),
                TextColumn::make('validator.name')->label('Divalidasi Oleh')
                    ->placeholder('Belum divalidasi')->color(fn ($state) => $state ? 'success' : 'gray')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('kategori')->options(collect(config('bkbm.kategori_bk'))->map(fn ($k) => $k['label'])->all()),
                SelectFilter::make('status')->options(['draft' => 'Draft', 'divalidasi' => 'Barang di luar', 'selesai' => 'Selesai']),
            ])
            ->recordActions([
                \App\Filament\Resources\Bks\Actions\BarangKembaliAction::make(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()->visible(fn (Bk $r) => ! $r->terkunci()),
                    DeleteAction::make()->visible(fn (Bk $r) => ! $r->terkunci()),
                ]),
            ]);
    }
}
