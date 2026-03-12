<?php

namespace App\Filament\Resources\StokVeneerKerings\Tables;

use App\Services\HppDryerService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StokVeneerKeringsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_transaksi')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('jenis_transaksi')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'masuk' => 'success',
                        'keluar' => 'danger',
                        'koreksi' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(),
                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),
                TextColumn::make('kw')
                    ->label('KW')
                    ->badge()
                    ->alignCenter(),
                TextColumn::make('m3')
                    ->label('M3')
                    ->numeric(decimalPlaces: 4)
                    ->suffix(' m³'),
                TextColumn::make('hpp_kering_per_m3')
                    ->label('HPP Kering/M3')
                    ->money('IDR')
                    ->alignRight()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('stok_m3_sesudah')
                    ->label('Stok Sesudah')
                    ->numeric(decimalPlaces: 4)
                    ->suffix(' m³')
                    ->color(fn($state) => $state <= 0 ? 'danger' : 'success'),
                TextColumn::make('hpp_average')
                    ->label('HPP Average/M3')
                    ->money('IDR')
                    ->alignRight()
                    ->weight(FontWeight::Bold)
                    ->color('primary'),
                TextColumn::make('nilai_stok_sesudah')
                    ->label('Nilai Stok')
                    ->money('IDR')
                    ->alignRight(),
            ])
            ->defaultSort('tanggal_transaksi', 'desc')
            ->filters([
                SelectFilter::make('jenis_transaksi')
                    ->label('Jenis')
                    ->options([
                        'masuk' => 'Masuk',
                        'keluar' => 'Keluar',
                        'koreksi' => 'Koreksi',
                    ]),
                SelectFilter::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu'),
                Filter::make('rentang_tanggal')
                    ->label('Rentang Tanggal')
                    ->form([
                        DatePicker::make('dari')->label('Dari'),
                        DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(
                        fn(Builder $q, array $data) => $q
                            ->when($data['dari'], fn($s) => $s->whereDate('tanggal_transaksi', '>=', $data['dari']))
                            ->when($data['sampai'], fn($s) => $s->whereDate('tanggal_transaksi', '<=', $data['sampai']))
                    ),
            ])
            ->headerActions([
                Action::make('catat_keluar')
                    ->label('Catat Stok Keluar')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->form([
                        Select::make('id_ukuran')
                            ->label('Ukuran')
                            ->options(fn() => \App\Models\Ukuran::all()->mapWithKeys(
                                fn($u) => [$u->id => "{$u->panjang}mm x {$u->lebar}mm x {$u->tebal}mm"]
                            ))
                            ->searchable()
                            ->required(),
                        Select::make('id_jenis_kayu')
                            ->label('Jenis Kayu')
                            ->options(fn() => \App\Models\JenisKayu::all()->pluck('nama_kayu', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('kw')
                            ->label('Kualitas (KW)')
                            ->required(),
                        DatePicker::make('tanggal')
                            ->label('Tanggal Keluar')
                            ->required()
                            ->default(now()),
                        TextInput::make('m3')
                            ->label('Volume Keluar (M3)')
                            ->numeric()
                            ->required()
                            ->minValue(0.000001)
                            ->suffix('m³'),
                        Textarea::make('keterangan')
                            ->label('Keterangan'),
                    ])
                    ->action(function (array $data) {
                        try {
                            app(HppDryerService::class)->buatTransaksiKeluar(
                                $data['id_ukuran'],
                                $data['id_jenis_kayu'],
                                $data['kw'],
                                $data['m3'],
                                $data['tanggal'],
                                $data['keterangan'] ?? null,
                            );
                            Notification::make()->title('Stok keluar dicatat')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}