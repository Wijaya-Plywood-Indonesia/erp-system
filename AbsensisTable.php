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
                    ->label('Download Files')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function ($record) {
                        $files = $record->file_path;

                        if (empty($files)) return null;

                        // Jika hanya 1 file, langsung download string biasa
                        if (count($files) === 1) {
                            return Storage::disk('public')->download($files[0]);
                        }

                        // Jika banyak file, buat ZIP sementara
                        $zipFileName = 'logs-' . $record->tanggal . '.zip';
                        $zipPath = storage_path('app/public/' . $zipFileName);
                        $zip = new \ZipArchive();

                        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                            foreach ($files as $file) {
                                $filePath = storage_path('app/public/' . $file);
                                if (file_exists($filePath)) {
                                    $zip->addFile($filePath, basename($file));
                                }
                            }
                            $zip->close();
                        }

                        return response()->download($zipPath)->deleteFileAfterSend(true);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
