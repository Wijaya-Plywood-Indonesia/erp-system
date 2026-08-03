<?php

namespace App\Filament\Resources\MasukGrajiTripleks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MasukGrajiTripleksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'barangSetengahJadiHp.jenisBarang',
                'barangSetengahJadiHp.grade.kategoriBarang',
                'barangSetengahJadiHp.ukuran',
                'serahTerimaHp.triplekMthMutasiKeluar.jenisKayu',
            ]))
            ->columns([
                TextColumn::make('jenis_barang_display')
                    ->label('Jenis Barang')
                    ->getStateUsing(function ($record) {
                        // Barang dari Gudang Triplek Mentah tidak punya
                        // barangSetengahJadiHp — ambil dari mutasi keluar.
                        if ($record->serahTerimaHp?->id_triplek_mth_mutasi_keluar !== null) {
                            return $record->serahTerimaHp->triplekMthMutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        return $record->barangSetengahJadiHp?->jenisBarang?->nama_jenis_barang ?? '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->whereHas('barangSetengahJadiHp.jenisBarang', fn ($qr) => $qr
                                ->whereRaw('LOWER(nama_jenis_barang) LIKE ?', ["%{$search}%"]))
                                ->orWhereHas('serahTerimaHp.triplekMthMutasiKeluar.jenisKayu', fn ($qr) => $qr
                                    ->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$search}%"]));
                        });
                    })
                    ->sortable(false),

                TextColumn::make('grade_display')
                    ->label('Grade')
                    ->getStateUsing(function ($record) {
                        // Barang dari Gudang Triplek Mentah selalu berkategori
                        // Plywood (tidak menyimpan field kategori sendiri).
                        if ($record->serahTerimaHp?->id_triplek_mth_mutasi_keluar !== null) {
                            $kwGrade = $record->serahTerimaHp->triplekMthMutasiKeluar?->kw_grade ?? '-';

                            return "Plywood | {$kwGrade}";
                        }

                        return ($record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                            .' | '.
                            ($record->barangSetengahJadiHp?->grade?->nama_grade ?? '-');
                    })
                    ->sortable(false),

                TextColumn::make('ukuran_display')
                    ->label('Ukuran')
                    ->getStateUsing(function ($record) {
                        // Barang dari Gudang Triplek Mentah tidak punya
                        // barangSetengahJadiHp — rakit dimensi dari mutasi keluar.
                        if ($record->serahTerimaHp?->id_triplek_mth_mutasi_keluar !== null) {
                            $m = $record->serahTerimaHp->triplekMthMutasiKeluar;

                            return $m
                                ? ($m->panjang + 0).'mm x '.($m->lebar + 0).'mm x '.($m->tebal + 0).'mm'
                                : '-';
                        }

                        return $record->barangSetengahJadiHp?->ukuran?->nama_ukuran ?? '-';
                    })
                    ->sortable(false),

                TextColumn::make('isi')
                    ->label('Jumlah')
                    ->alignCenter(),
            ])

            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->recordActions([
                EditAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
