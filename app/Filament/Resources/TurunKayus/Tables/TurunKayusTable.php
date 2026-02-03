<?php

namespace App\Filament\Resources\TurunKayus\Tables;

use App\Filament\Resources\TurunKayus\TurunKayuResource;
use App\Models\TurunKayu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

use Filament\Tables\Table;
use Filament\Actions\Action;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;

class TurunKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])

            ->recordActions([
                Action::make('kelola_kendala')
                    ->label(fn($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->icon(fn($record) => $record->kendala ? 'heroicon-o-pencil-square' : 'heroicon-o-plus')
                    ->color(fn($record) => $record->kendala ? 'info' : 'warning')

                    // ✅ Form style baru di Filament 4
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Kendala')
                            ->required()
                            ->rows(4),
                    ])

                    // ✅ Saat modal dibuka — isi form dengan data kendala lama jika ada
                    ->mountUsing(function ($form, $record) {
                        $form->fill([
                            'kendala' => $record->kendala ?? '',
                        ]);
                    })

                    // ✅ Saat tombol Simpan ditekan
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'kendala' => trim($data['kendala']),
                        ]);

                        Notification::make()
                            ->title($record->kendala ? 'Kendala diperbarui' : 'Kendala ditambahkan')
                            ->success()
                            ->send();
                    })

                    ->modalHeading(fn($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->modalSubmitActionLabel('Simpan'),
                EditAction::make(),
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                Action::make('create_today')
                    ->label('Tambah Hari Ini')
                    ->icon('heroicon-o-plus')
                    ->color('success')

                    // langsung create record tanpa form
                    ->action(function () {
                        $model = config('filament.resources.' . TurunKayuResource::class . '.model')
                            ?? TurunKayu::class;

                        $model::create([
                            'tanggal' => now()->toDateString(),
                        ]);

                        Notification::make()
                            ->title('Data hari ini berhasil dibuat')
                            ->success()
                            ->send();
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
