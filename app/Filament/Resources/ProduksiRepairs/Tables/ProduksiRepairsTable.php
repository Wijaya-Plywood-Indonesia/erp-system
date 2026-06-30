<?php

namespace App\Filament\Resources\ProduksiRepairs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class ProduksiRepairsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')

            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal Repair')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->limit(40)
                    ->placeholder('Tidak ada kendala')
                    ->tooltip(fn ($record) => $record->kendala),
            ])

            ->recordActions([
                /* ================= KENDALA ================= */
                Action::make('kelola_kendala')
                    ->label(fn ($record) => $record->kendala ? 'Edit Kendala' : 'Tambah Kendala')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color(fn ($record) => $record->kendala ? 'info' : 'gray')
                    ->visible(fn ($record) => ! $record->validasiRepairs()->exists())
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Catatan Kendala')
                            ->required()
                            ->rows(4),
                    ])
                    ->mountUsing(fn ($form, $record) =>
                        $form->fill(['kendala' => $record->kendala])
                    )
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'kendala' => trim($data['kendala']),
                        ]);

                        Notification::make()
                            ->title('Kendala berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Manajemen Kendala')
                    ->modalWidth('lg'),

                EditAction::make()
                    ->visible(fn ($record) => ! $record->validasiRepairs()->exists()),

                ViewAction::make(),

                /* ================= DELETE ================= */
                DeleteAction::make()
                    ->visible(fn ($record) => ! $record->validasiRepairs()->exists())
                    ->before(function ($record) {

                        $hasRelation =
                            $record->rencanaPegawais()->exists()
                            || $record->rencanaRepair()->exists()
                            || $record->hasilRepairs()->exists()
                            || $record->modalRepairs()->exists()
                            || $record->validasiRepairs()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Repair ini masih memiliki data terkait.')
                                ->danger()
                                ->send();

                            // ⛔ WAJIB
                            throw new Halt();
                        }
                    }),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {

                            foreach ($records as $record) {
                                $hasRelation =
                                    $record->rencanaPegawais()->exists()
                                    || $record->rencanaRepair()->exists()
                                    || $record->hasilRepairs()->exists()
                                    || $record->modalRepairs()->exists()
                                    || $record->validasiRepairs()->exists();

                                if ($hasRelation) {
                                    Notification::make()
                                        ->title('Gagal menghapus data terpilih')
                                        ->body('Salah satu Produksi Repair masih memiliki relasi.')
                                        ->danger()
                                        ->send();

                                    // ⛔ WAJIB
                                    throw new Halt();
                                }
                            }
                        }),
                ]),
            ]);
    }
}
