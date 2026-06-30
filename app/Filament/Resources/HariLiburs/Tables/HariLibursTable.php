<?php

namespace App\Filament\Resources\HariLiburs\Tables;

use App\Services\GoogleICSImporter;
use App\Services\NagerDateImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Facades\Filament;
class HariLibursTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                //->limit(20)
                ,

                BadgeColumn::make('type')
                    ->label('Kategori')
                    ->colors([
                        'primary' => 'national',
                        'warning' => 'cuti_bersama',
                        'info' => 'religion',
                        'success' => 'company',
                        'gray' => 'custom',
                    ])
                    ->sortable(),

                IconColumn::make('is_repeat_yearly')
                    ->label('Berulang')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->filters([
                //
                SelectFilter::make('type')
                    ->label('Kategori')
                    ->options([
                        'national' => 'National',
                        'cuti_bersama' => 'Cuti Bersama',
                        'religion' => 'Religion',
                        'company' => 'Company',
                        'custom' => 'Custom',
                    ]),

                TernaryFilter::make('is_repeat_yearly')
                    ->label('Berulang Tiap Tahun'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])

            ->toolbarActions([
                Action::make('importHolidays')
                    ->label('Import Hari Libur Nasional')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('success')
                    ->form([
                        Select::make('year')
                            ->label('Pilih Tahun')
                            ->options([
                                date('Y') => date('Y'),
                                date('Y') + 1 => date('Y') + 1,
                                date('Y') + 2 => date('Y') + 2,
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data) {

                        $year = (int) $data['year'];

                        // Import dari Nager
                        $nager = NagerDateImporter::import($year);
                        if (is_array($nager) && ($nager['error'] ?? false)) {
                            Notification::make()
                                ->title('Gagal Import dari Nager.Date')
                                ->body($nager['message'])
                                ->danger()
                                ->send();
                            return;
                        }

                        // Import dari Google ICS
                        $google = GoogleICSImporter::import($year);
                        if (is_array($google) && ($google['error'] ?? false)) {
                            Notification::make()
                                ->title('Gagal Import dari Google Calendar')
                                ->body($google['message'])
                                ->danger()
                                ->send();
                            return;
                        }

                        // --- Jika semua sukses ---
                        if (!is_int($nager) || !is_int($google)) {
                            Notification::make()
                                ->title("Import Tahun $year selesai dengan sebagian error")
                                ->body("Nager: " . (is_int($nager) ? $nager : 0) .
                                    " • Google ICS: " . (is_int($google) ? $google : 0))
                                ->warning()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title("Import Hari Libur Tahun $year Berhasil")
                            ->body("Nager: $nager • Google ICS: $google")
                            ->success()
                            ->send();
                    })

                    ->requiresConfirmation(),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
