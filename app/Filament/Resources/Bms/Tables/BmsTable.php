<?php

namespace App\Filament\Resources\Bms\Tables;

use App\Models\Bm;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BmsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Bm $r) => \App\Filament\Resources\Bms\BmResource::getUrl('view', ['record' => $r]))
            ->recordClasses(fn (Bm $r) => $r->status === 'divalidasi' ? 'bg-green-50 dark:bg-green-950/30' : null)
            ->columns([
                // Tampilan HP: satu kolom ringkas, kolom lain disembunyikan.
                TextColumn::make('ringkas')->label('Nota')->hiddenFrom('md')
                    ->getStateUsing(fn (Bm $r) => $r->no_nota)->weight('bold')
                    ->searchable(['no_nota', 'dari'])
                    ->description(fn (Bm $r) => strtoupper($r->kategori) . ' · dari ' . ($r->dari ?: '-') . ' · ' . $r->tanggal->format('d M Y')
                        . ($r->ref_no_bk ? ' · ref ' . $r->ref_no_bk : ''))
                    ->wrap(),
                TextColumn::make('tanggal')->date('d M Y')->sortable()->visibleFrom('md'),
                TextColumn::make('no_nota')->label('No. Nota')->searchable()->weight('bold')->visibleFrom('md'),
                TextColumn::make('kategori')->label('Kategori')->weight('bold')->visibleFrom('md')->formatStateUsing(fn ($state) => strtoupper((string) $state)),
                TextColumn::make('dari')->searchable()->visibleFrom('md'),
                TextColumn::make('referensi')->label('Referensi BK')->placeholder('-')->visibleFrom('md')
                    ->getStateUsing(fn (Bm $r) => $r->bkReferensi?->no_nota
                        ?? ($r->ref_no_bk ? $r->ref_no_bk . ($r->ref_pabrik ? " ({$r->ref_pabrik})" : '') : null))
                    ->color('primary')->weight('bold')
                    ->action(\App\Filament\Resources\Bms\Actions\ReferensiBkAction::make()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'divalidasi' ? 'Divalidasi' : 'Draft')
                    ->color(fn ($state) => $state === 'divalidasi' ? 'success' : 'gray'),
                TextColumn::make('validator.name')->label('Divalidasi Oleh')->placeholder('Belum divalidasi')
                    ->color(fn ($state) => $state ? 'success' : 'gray')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('kategori')->options(config('bkbm.kategori_bm')),
                SelectFilter::make('status')->options(['draft' => 'Draft', 'divalidasi' => 'Divalidasi']),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()->visible(fn (Bm $r) => ! $r->terkunci()),
                    DeleteAction::make()->visible(fn (Bm $r) => ! $r->terkunci()),
                ]),
            ]);
    }
}
