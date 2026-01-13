<?php

namespace App\Filament\Resources\GantiPisauRotaries\Tables;

use App\Models\GantiPisauRotary;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea; // Tambahkan import Textarea
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Carbon\Carbon;

class GantiPisauRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. KOLOM KENDALA
                TextColumn::make('jenis_kendala')
                    ->label('Kendala')
                    // Description dihapus karena jenis_kendala sekarang adalah teks lengkap
                    ->wrap()
                    ->sortable(),

                // 2. JAM MULAI
                TextColumn::make('jam_mulai_ganti_pisau')
                    ->label('Mulai')
                    ->time('H:i')
                    ->sortable(),

                // 3. JAM SELESAI
                TextColumn::make('jam_selesai_ganti')
                    ->label('Selesai')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (empty($state) || $state === '-') return '-';
                        try {
                            return Carbon::parse($state)->format('H:i');
                        } catch (\Exception $e) {
                            return '-';
                        }
                    })
                    ->color(fn($state) => ($state && $state !== '-') ? 'success' : 'danger'),

                // 4. DURASI & TOTAL
                TextColumn::make('durasi')
                    ->label('Durasi')
                    ->state(function (GantiPisauRotary $record) {
                        $jamSelesai = $record->jam_selesai_ganti;
                        if (empty($jamSelesai) || $jamSelesai === '-') return 'Proses...';
                        try {
                            $mulai = Carbon::parse($record->jam_mulai_ganti_pisau);
                            $selesai = Carbon::parse($jamSelesai);
                            return $mulai->diffInMinutes($selesai) . ' Menit';
                        } catch (\Exception $e) {
                            return '-';
                        }
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Downtime')
                            ->using(function ($query) {
                                $records = $query->get();
                                $totalMenit = 0;
                                foreach ($records as $record) {
                                    if (empty($record->jam_selesai_ganti) || $record->jam_selesai_ganti === '-') continue;
                                    try {
                                        $mulai = Carbon::parse($record->jam_mulai_ganti_pisau);
                                        $selesai = Carbon::parse($record->jam_selesai_ganti);
                                        $totalMenit += $mulai->diffInMinutes($selesai);
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                                if ($totalMenit >= 60) {
                                    $jam = floor($totalMenit / 60);
                                    $menit = $totalMenit % 60;
                                    return "{$jam} Jam {$menit} Menit";
                                }
                                return "{$totalMenit} Menit";
                            })
                    ),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            // --- INPUT DATA VIA TABLE HEADER ---
            ->headerActions([
                CreateAction::make()
                    ->label('Mulai Kendala Baru')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Input Kendala Mesin')
                    ->modalWidth('md')
                    ->form([
                        Grid::make(1)
                            ->schema([
                                // PERBAIKAN: Menggunakan Textarea langsung
                                Textarea::make('jenis_kendala')
                                    ->label('Jenis Kendala')
                                    ->required()
                                    ->rows(3)
                                    ->placeholder('Deskripsikan kendala...'),

                                Hidden::make('jam_mulai_ganti_pisau')
                                    ->default(now()->format('H:i')),
                            ])
                    ]),
                // MutateFormDataUsing dihapus karena sudah tidak ada logika reset keterangan
            ])

            ->actions([
                // TOMBOL SELESAI
                Action::make('selesai_ganti')
                    ->label('Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn(GantiPisauRotary $record) => empty($record->jam_selesai_ganti) || $record->jam_selesai_ganti === '-')
                    ->requiresConfirmation()
                    ->action(function (GantiPisauRotary $record) {
                        $record->update(['jam_selesai_ganti' => now()->format('H:i')]);
                        Notification::make()->title('Pekerjaan Selesai')->success()->send();
                    }),

                // EDIT JUGA MENGGUNAKAN LOGIKA YANG SAMA (Textarea)
                EditAction::make()
                    ->form([
                        Grid::make(1)
                            ->schema([
                                Textarea::make('jenis_kendala')
                                    ->label('Jenis Kendala')
                                    ->required()
                                    ->rows(3)
                                    ->placeholder('Deskripsikan kendala...'),
                            ])
                    ]),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
