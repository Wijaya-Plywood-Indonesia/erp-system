<?php

namespace App\Filament\Resources\TurusanKayus\Tables;

use App\Models\DetailKayuMasuk;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TurusanKayusTable
{
    public static function configure(Table $table): Table
    {
        $isAdmin = Auth::user()->hasRole('admin');

        $isLocked = function ($record) use ($isAdmin) {
            // Jika Admin, jangan pernah kunci (selalu bisa edit)
            if ($isAdmin) return false;

            // Jika record kosong (menghindari error null)
            if (!$record) return false;

            // Kunci jika nota sudah diperiksa/final
            // Gunakan ?-> untuk keamanan jika relasi notaKayu kosong
            return $record->notaKayu?->status !== 'Belum Diperiksa' && $record->notaKayu !== null;
        };

        return $table

            ->defaultSort('created_at', 'desc') // urutkan dari yang terbaru
            ->columns([
                TextColumn::make('seri')
                    ->label('Seri Kayu')
                    ->alignCenter()
                    ->numeric()->sortable(),
                TextColumn::make('jenis_dokumen_angkut')
                    ->searchable(),
                TextColumn::make('upload_dokumen_angkut')
                    ->label('Dokumen Legal')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Ada File' : 'Kosong')
                    ->color(fn($state) => $state ? 'success' : 'danger'),
                TextColumn::make('tgl_kayu_masuk')->dateTime()->sortable(),

                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([


            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    // Hanya muncul jika tidak terkunci ATAU user adalah Admin
                    ->visible(fn ($record) => !$isLocked($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($record) => !$isLocked($record)),
                ]),
            ]);
    }
}
