<?php

namespace App\Filament\Resources\KontrakKerjas\Tables;

use App\Models\KontrakKerja;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
class KontrakKerjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode Pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kontrak_mulai')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),

                TextColumn::make('kontrak_selesai')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),

                TextColumn::make('durasi_kontrak')
                    ->label('Durasi (hari)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status_dokumen')
                    ->label('Dokumen')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'draft' => 'gray',
                        'dicetak' => 'warning',
                        'ditandatangani' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status_kontrak')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'soon' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('dibuat_oleh')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('divalidasi_oleh')
                    ->label('Divalidasi Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated(false)
            ->filters([
                //
                SelectFilter::make('status_kontrak')
                    ->label('Status Pegawai')
                    //['active', 'soon', 'expired']
                    ->options([
                        'active' => 'Aktif',
                        'soon' => 'Segera Habis',
                        'expired' => 'Habis',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('print')
                    ->label('Cetak Kontrak')
                    ->icon('heroicon-o-printer')
                    ->url(fn($record): string => route('kontrak.print', $record))
                    ->openUrlInNewTab(),

            ])
            ->defaultSort('id', 'desc')
            ->toolbarActions([
                Action::make('update_status_kontrak')
                    ->label('Update Status Kontrak')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function () {

                        DB::statement("
            UPDATE kontrak_kerja
            SET 
                durasi_kontrak = 
                    CASE
                        WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                            THEN 0
                        ELSE DATEDIFF(kontrak_selesai, kontrak_mulai)
                    END,

                status_kontrak =
                    CASE
                        WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                            THEN 'expired'
                        WHEN CURDATE() > kontrak_selesai
                            THEN 'expired'
                        WHEN DATEDIFF(kontrak_selesai, CURDATE()) <= 30
                            THEN 'soon'
                        ELSE 'active'
                    END
        ");

                        Notification::make()
                            ->title('Status kontrak berhasil diperbarui')
                            ->success()
                            ->send();
                    }),
                Action::make('update_semua_durasi')
                    ->label('Update Semua Durasi')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function () {

                        DB::statement("
                UPDATE kontrak_kerja
                SET durasi_kontrak = DATEDIFF(kontrak_selesai, kontrak_mulai)
                WHERE kontrak_mulai IS NOT NULL
                AND kontrak_selesai IS NOT NULL
            ");

                        Notification::make()
                            ->title('Durasi semua kontrak berhasil diperbarui')
                            ->success()
                            ->send();
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),

            ]);
    }
}
