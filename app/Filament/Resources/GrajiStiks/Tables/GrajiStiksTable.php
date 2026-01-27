<?php

namespace App\Filament\Resources\GrajiStiks\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;

class GrajiStiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal Produksi')
                    ->date('d F Y')
                    ->sortable(),

                // Menampilkan status kunci secara visual di tabel utama
                TextColumn::make('validasiTerakhir.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'divalidasi' => 'success',
                        'ditolak' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'Pending')),

                TextColumn::make('kendala')
                    ->label('Keterangan Kendala')
                    ->placeholder('Tidak ada kendala')
                    ->searchable()
                    ->limit(30),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal')
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari_tanggal'] ?? null) {
                            $indicators[] = 'Dari: ' . \Carbon\Carbon::parse($data['dari_tanggal'])->format('d/m/Y');
                        }
                        if ($data['sampai_tanggal'] ?? null) {
                            $indicators[] = 'Sampai: ' . \Carbon\Carbon::parse($data['sampai_tanggal'])->format('d/m/Y');
                        }
                        return $indicators;
                    })
            ])
            ->actions([
                // 🔒 Lock Action Isi Kendala
                // Menambahkan ? agar jika $record null tidak menyebabkan crash
                Action::make('isi_kendala')
                    ->label('Isi Kendala')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->modalHeading('Keterangan Kendala Produksi')
                    ->hidden(fn($record) => $record?->isLocked())
                    ->form([
                        Textarea::make('kendala')
                            ->label('Kendala')
                            ->rows(5),
                    ])
                    ->fillForm(fn($record): array => [
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

                EditAction::make()
                    ->hidden(fn($record) => $record?->isLocked()),

                DeleteAction::make()
                    ->hidden(fn($record) => $record?->isLocked()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // Bulk action tidak memiliki $record tunggal secara default saat render awal
                        ->hidden(fn($record) => $record?->isLocked() ?? false),
                ]),
            ]);
    }
}
