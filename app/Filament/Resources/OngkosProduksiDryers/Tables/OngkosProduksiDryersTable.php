<?php

namespace App\Filament\Resources\OngkosProduksiDryers\Tables;

use App\Services\HppDryerService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OngkosProduksiDryersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_produksi_sort')
                    ->label('Tanggal')
                    ->getStateUsing(fn($record) => $record->produksi?->tanggal_produksi)
                    ->date('d/m/Y')
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->join('produksi_press_dryers as ppd', 'ppd.id', '=', 'ongkos_produksi_dryers.id_produksi_dryer')
                            ->orderBy('ppd.tanggal_produksi', $direction)
                            ->select('ongkos_produksi_dryers.*');
                    }),
                TextColumn::make('produksi.shift')
                    ->label('Shift')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'pagi' => 'warning',
                        'malam' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('total_m3')
                    ->label('Total M3')
                    ->numeric(decimalPlaces: 4)
                    ->suffix(' m³'),
                TextColumn::make('ttl_pekerja')
                    ->label('Pekerja')
                    ->alignCenter(),
                TextColumn::make('jumlah_mesin')
                    ->label('Mesin')
                    ->alignCenter(),
                TextColumn::make('total_ongkos')
                    ->label('Total Ongkos')
                    ->money('IDR')
                    ->alignRight(),
                TextColumn::make('ongkos_per_m3')
                    ->label('Ongkos / M3')
                    ->money('IDR')
                    ->alignRight()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('hpp_kering_per_m3')
                    ->label('HPP Kering / M3')
                    ->getStateUsing(fn($record) => HppDryerService::HPP_VENEER_BASAH_PER_M3 + $record->ongkos_per_m3)
                    ->money('IDR')
                    ->alignRight()
                    ->weight(FontWeight::Bold)
                    ->color('primary'),
                IconColumn::make('is_final')
                    ->label('Final')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('ongkos_produksi_dryers.id', 'desc')
            ->filters([
                Filter::make('belum_final')
                    ->label('Belum Final')
                    ->query(fn(Builder $q) => $q->where('is_final', false)),
                Filter::make('bulan_ini')
                    ->label('Bulan Ini')
                    ->query(fn(Builder $q) => $q->whereHas(
                        'produksi',
                        fn($s) =>
                        $s->whereMonth('tanggal_produksi', now()->month)
                            ->whereYear('tanggal_produksi', now()->year)
                    )),
            ])
            ->recordActions([
                Action::make('recalculate')
                    ->label('Hitung Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn($record) => !$record->is_final)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->recalculate();
                        Notification::make()->title('Kalkulasi diperbarui')->success()->send();
                    }),
                ViewAction::make(),
                EditAction::make()->visible(fn($record) => !$record->is_final),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('finalkan')
                        ->label('Finalkan Terpilih')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['is_final' => true])),
                ]),
            ]);
    }
}