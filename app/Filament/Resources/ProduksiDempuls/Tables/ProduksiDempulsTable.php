<?php

namespace App\Filament\Resources\ProduksiDempuls\Tables;

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

class ProduksiDempulsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal Repair')
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
                //
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

                        // 🔒 cek relasi sebelum delete
                        $hasRelation =
                            $record->rencanaPegawaiDempuls()->exists() ||
                            $record->detailDempuls()->exists() ||
                            $record->validasiDempuls()->exists() ||
                            $record->bahanDempuls()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Gagal menghapus data')
                                ->body('Data produksi tidak dapat dihapus karena masih memiliki data terkait.')
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
