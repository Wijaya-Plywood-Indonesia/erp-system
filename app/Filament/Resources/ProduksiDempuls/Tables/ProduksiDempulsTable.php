<?php

namespace App\Filament\Resources\ProduksiDempuls\Tables;

use App\Models\ProduksiDempul;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProduksiDempulsTable
{
    public static function configure(Table $table): Table
    {
        $kolomTanggal = ProduksiDempul::kolomTanggalAktif();

        return $table
            ->columns([
                TextColumn::make('tanggal_dempul')
                    ->label('Tanggal Dempul')
                    ->date('d/m/Y')
                    ->sortable(query: function ($query, string $direction) use ($kolomTanggal) {
                        return $query->orderBy($kolomTanggal, $direction);
                    })
                    ->searchable(query: function ($query, string $search) use ($kolomTanggal) {
                        return $query->whereDate($kolomTanggal, 'like', "%{$search}%");
                    }),

                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->wrap()
                    ->limit(50)
                    ->sortable()
                    ->searchable(),
            ])
            ->defaultSort($kolomTanggal, 'desc') // ⬅️ urutkan terbaru di atas secara default
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
