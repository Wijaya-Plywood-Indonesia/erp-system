<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;

class ProduksiTembelTripleksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal Produksi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->wrap()
                    ->limit(50)
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                // Tambahkan filter jika diperlukan nanti
            ])
            ->recordActions([
                Action::make('kendala')
                    ->label(fn ($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->icon(fn ($record) => $record->kendala ? 'heroicon-o-pencil-square' : 'heroicon-o-plus')
                    ->color(fn ($record) => $record->kendala ? 'info' : 'warning')
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Kendala')
                            ->required()
                            ->rows(4),
                    ])
                    ->mountUsing(function ($form, $record) {
                        $form->fill([
                            'kendala' => $record->kendala ?? '',
                        ]);
                    })
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'kendala' => trim($data['kendala']),
                        ]);

                        Notification::make()
                            ->title($record->kendala ? 'Kendala diperbarui' : 'Kendala ditambahkan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn ($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->modalSubmitActionLabel('Simpan'),

                ViewAction::make(),

                EditAction::make(),

                DeleteAction::make()
                    ->before(function ($record, DeleteAction $action) {

                        // 🔒 Cek relasi sebelum mendelete data produksi utama
                        $hasRelation =
                            $record->pegawaiTembeltriplek()->exists() ||
                            $record->hasilTembeltriplek()->exists() ||
                            $record->validasiTembeltriplek()->exists() ||
                            $record->bahanPenolongTembeltriplek()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Gagal menghapus data')
                                ->body('Data produksi tidak dapat dihapus karena masih memiliki data terkait (Pegawai, Hasil, Validasi, atau Bahan Penolong).')
                                ->danger()
                                ->send();

                            // ⛔ batalkan delete
                            $action->cancel();
                        }
                    })
                    ->successNotification(
                        Notification::make()
                            ->title('Data produksi berhasil dihapus')
                            ->success()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}