<?php

namespace App\Filament\Resources\Absensis\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AbsensisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('file_path')
                    ->label('File')
                    ->formatStateUsing(fn($state) => basename($state))
                    ->hidden(),
                TextColumn::make('uploaded_by')
                    ->label('Uploader')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('download')
                    ->label('Download File')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(fn($record) => Storage::disk('public')->download($record->file_path)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
