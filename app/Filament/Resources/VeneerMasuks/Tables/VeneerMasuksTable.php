<?php

namespace App\Filament\Resources\VeneerMasuks\Tables;

use App\Services\VeneerMutasiService;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;

class VeneerMasuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('no_nota')
                    ->label('No. Nota BM')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->id_nota_bm
                        ? route('filament.admin.resources.nota-barang-masuks.view', ['record' => $record->id_nota_bm])
                        : null
                    )
                    ->color('primary'),

                TextColumn::make('tujuan_nota')
                    ->label('Supplier / Pengirim')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record): string =>
                        $record->notaBm?->divalidasi_oleh !== null
                            ? 'success'
                            : 'warning'
                    )
                    ->formatStateUsing(fn ($record): string =>
                        $record->notaBm?->divalidasi_oleh !== null
                            ? 'Divalidasi'
                            : 'Belum Divalidasi'
                    ),

                TextColumn::make('details_summary')
                    ->label('Detail Barang')
                    ->getStateUsing(fn ($record) =>
                        $record->details
                            ->map(fn($d) =>
                                "{$d->qty} lbr " .
                                ($d->ukuran?->nama_ukuran ?? '-') .
                                " (" .
                                ($d->jenisKayu?->nama_kayu ?? '-') .
                                " KW {$d->kw})"
                            )
                            ->implode(', ')
                    )
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pembuat.name')
                    ->label('Dibuat Oleh')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('post')
                    ->label('Kirim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'kirim']);
                        app(VeneerMutasiService::class)->process($record);
                    })
                    ->visible(fn ($record) => $record->status === 'draft'),

                EditAction::make()
                    ->visible(fn ($record) =>
                        !$record->notaBm ||
                        $record->notaBm->divalidasi_oleh === null
                    ),

                DeleteAction::make()
                    ->before(fn ($record) =>
                        app(VeneerMutasiService::class)->reverse($record)
                    )
                    ->visible(fn ($record) =>
                        !$record->notaBm ||
                        $record->notaBm->divalidasi_oleh === null
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn ($records) =>
                            $records->each(fn ($record) =>
                                app(VeneerMutasiService::class)->reverse($record)
                            )
                        ),
                ]),
            ]);
    }
}