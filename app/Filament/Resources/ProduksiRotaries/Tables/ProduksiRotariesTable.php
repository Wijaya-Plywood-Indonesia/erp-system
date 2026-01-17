<?php

namespace App\Filament\Resources\ProduksiRotaries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Support\Exceptions\Halt;

class ProduksiRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tgl_produksi')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('mesin.nama_mesin')
                    ->label('Nama Mesin')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('tgl_produksi')
                    ->schema([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('tgl_produksi', '>=', $d))
                            ->when($data['until'], fn ($q, $d) => $q->whereDate('tgl_produksi', '<=', $d));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),

                DeleteAction::make()
                    ->before(function ($record) {

                        $hasDetail =
                            $record->detailPegawaiRotary()->exists()
                            || $record->detailLahanRotary()->exists()
                            || $record->detailValidasiHasilRotary()->exists()
                            || $record->detailGantiPisauRotary()->exists()
                            || $record->detailPaletRotary()->exists()
                            || $record->detailKayuPecah()->exists()
                            || $record->riwayatKayu()->exists();

                        if ($hasDetail) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Rotary ini masih memiliki data didalamnya yang terkait.')
                                ->danger()
                                ->send();

                            // ⛔ WAJIB: hentikan delete
                            throw new Halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
