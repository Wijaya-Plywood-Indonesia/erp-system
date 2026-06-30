<?php

namespace App\Filament\Resources\RekapKayuMasuks\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class RekapKayuMasuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->columns([

                // ===========================
                // KOLOM ASLI DB
                // ===========================

                TextColumn::make('tgl_kayu_masuk')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('penggunaanSupplier.nama_supplier')
                    ->label('Supplier')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('seri')
                    ->label('Nomor Seri')
                    ->sortable()
                    ->searchable(),

                // ===========================
                // KOLOM RELASI / HASIL OLAHAN
                // ===========================

                TextColumn::make('panjang')
                    ->label('Panjang')
                    ->wrap()
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailMasukanKayu
                            ->pluck('panjang')
                            ->unique()
                            ->implode(', ')
                        ?? '-'
                    ),

                TextColumn::make('jenis')
                    ->label('Jenis Kayu')
                    ->wrap()
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailMasukanKayu
                            ->pluck('jenisKayu.nama_kayu')
                            ->unique()
                            ->implode(', ')
                        ?? '-'
                    )
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->whereHas(
                            'detailMasukanKayu.jenisKayu',
                            fn($q) =>
                            $q->where('nama_kayu', 'like', "%{$search}%")
                        )
                    ),

                TextColumn::make('lahan')
                    ->label('Lahan')
                    ->wrap()
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailMasukanKayu
                            ->pluck('lahan.kode_lahan')
                            ->unique()
                            ->implode(', ')
                        ?? '-'
                    )
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->whereHas(
                            'detailMasukanKayu.lahan',
                            fn($q) =>
                            $q->where('kode_lahan', 'like', "%{$search}%")
                        )
                    ),

                TextColumn::make('banyak')
                    ->label('Total Batang')
                    ->numeric()
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailMasukanKayu->sum('jumlah_batang') ?? 0
                    ),

                TextColumn::make('diameter')
                    ->label('Diameter')
                    ->wrap()
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailMasukanKayu
                            ->pluck('diameter')
                            ->unique()
                            ->implode(', ')
                        ?? '-'
                    ),

                // ===========================
                // STATUS NOTA (BADGE)
                // ===========================

                BadgeColumn::make('status')
                    ->label('Status Nota')
                    ->tooltip(
                        fn($record) =>
                        $record->notaKayu->first()?->status ?? 'Belum Diperiksa'
                    )
                    ->getStateUsing(function ($record) {
                        $status = $record->notaKayu->first()?->status;

                        if ($status && str_contains(strtolower($status), 'sudah')) {
                            return 'Sudah Cetak Nota';
                        }

                        return 'Belum Cetak Nota';
                    })
                    ->colors([
                        'success' => 'Sudah Cetak Nota',
                        'danger' => 'Belum Cetak Nota',
                    ])
                    ->icon(fn(string $state) => match ($state) {
                        'Sudah Cetak Nota' => 'heroicon-o-check-circle',
                        default => 'heroicon-o-x-circle',
                    })
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->whereHas(
                            'notaKayu',
                            fn($q) =>
                            $q->where('status', 'like', "%{$search}%")
                        )
                    ),

            ])
            ->filters([
                // Filter Rentang Tanggal
                Filter::make('tgl_kayu_masuk')
                    ->form([
                        DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal')
                            ->placeholder('Pilih tanggal awal')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->suffixIcon('heroicon-o-calendar')
                            ->suffixIconColor('primary'),
                        DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal')
                            ->placeholder('Pilih tanggal akhir')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->suffixIcon('heroicon-o-calendar')
                            ->suffixIconColor('primary'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tgl_kayu_masuk', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tgl_kayu_masuk', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari_tanggal'] ?? null) {
                            $indicators['dari_tanggal'] = 'Dari: ' . Carbon::parse($data['dari_tanggal'])->format('d/m/Y');
                        }
                        if ($data['sampai_tanggal'] ?? null) {
                            $indicators['sampai_tanggal'] = 'Sampai: ' . Carbon::parse($data['sampai_tanggal'])->format('d/m/Y');
                        }
                        return $indicators;
                    })
            ])
            ->defaultSort('id', 'desc');
    }
}
