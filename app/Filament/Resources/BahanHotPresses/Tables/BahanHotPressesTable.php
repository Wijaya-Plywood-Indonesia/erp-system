<?php

namespace App\Filament\Resources\BahanHotPresses\Tables;

use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;

class BahanHotPressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                /*
                 * JENIS BARANG
                 */
                TextColumn::make('barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->placeholder('-'),

                /*
                 * GRADE
                 */
                TextColumn::make('barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->searchable()
                    ->placeholder('-'),

                /*
                 * UKURAN
                 */
                TextColumn::make('barangSetengahJadi.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable()
                    ->placeholder('-'),

                /*
                 * ISI
                 */
                TextColumn::make('isi')
                    ->label('Jumlah Lembar'),
                
                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('ket')
                    ->label('Keterangan')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->form([
                        Textarea::make('ket')
                            ->label('Keterangan')
                            ->rows(3)
                            ->default(fn($record) => $record->keterangan),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ket' => $data['ket'],
                        ]);

                        Notification::make()
                            ->title('Keterangan berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn($record) => "Keterangan ")
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg'),
                EditAction::make()
                    ->hidden(fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
