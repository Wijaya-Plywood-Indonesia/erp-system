<?php

namespace App\Filament\Resources\ProduksiRotaries\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

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

                TextColumn::make('kendala')
                    ->label('Keterangan Kendala')
                    ->placeholder('Tidak ada kendala')
                    ->searchable()
                    ->limit(30),

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
                Action::make('isi_kendala')
                    ->label('Isi Kendala')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->modalHeading('Keterangan Kendala Produksi Rotary')
                    ->form([
                        Textarea::make('kendala')
                            ->label('Kendala')
                            ->rows(5),
                    ])
                    ->fillForm(fn ($record): array => [
                        'kendala' => $record->kendala,
                    ])
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'kendala' => $data['kendala'],
                        ]);

                        Notification::make()
                            ->title('Berhasil memperbarui kendala')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                ViewAction::make(),

                DeleteAction::make()
                    ->before(function ($record) {
                        $hasDetail =
                            $record->detailPegawaiRotary()->exists()
                            || $record->detailLahanRotary()->exists()
                            || $record->detailValidasiHasilRotary()->exists()
                            || $record->kendalaRotaries()->exists()
                            || $record->detailPaletRotary()->exists()
                            || $record->detailKayuPecah()->exists()
                            || $record->riwayatKayu()->exists();

                        if ($hasDetail) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Rotary ini masih memiliki data didalamnya yang terkait.')
                                ->danger()
                                ->send();

                            throw new Halt;
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
