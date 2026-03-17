<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Builder;

class DetailBarangDikerjakansTable
{
    public static function configure(Table $table): Table
    {
        return $table

            /*
            |=====================================================
            | 🔥 GROUP BY PEGAWAI
            |=====================================================
            */
            ->groups([
                Group::make('id_pegawai_nyusup')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(
                        fn($record) =>
                        $record->pegawaiNyusup?->pegawai?->nama_pegawai
                            ?? 'Pegawai Tidak Diketahui'
                    )
                    ->collapsible(true), // default tertutup
            ])

            /*
            |=====================================================
            | 📋 COLUMNS
            |=====================================================
            */
            ->columns([

                TextColumn::make('barang')
                    ->label('Barang')
                    ->getStateUsing(function ($record) {
                        $b = $record->barangSetengahJadiHp;

                        if (! $b) {
                            return '-';
                        }

                        $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran   = $b->ukuran?->nama_ukuran ?? '-';
                        $grade    = $b->grade?->nama_grade ?? '-';
                        $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$kategori} | {$ukuran} | {$grade} | {$jenis}";
                    })
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('barangSetengahJadiHp', function (Builder $q) use ($search) {
                            $q->whereHas('ukuran', function ($qu) use ($search) {
                                // Mencari di dimensi fisik (panjang, lebar, tebal)
                                $qu->where('panjang', 'like', "%{$search}%")
                                    ->orWhere('lebar', 'like', "%{$search}%")
                                    ->orWhere('tebal', 'like', "%{$search}%")
                                    ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                            })
                                ->orWhereHas('grade', function ($qg) use ($search) {
                                    $qg->where('nama_grade', 'like', "%{$search}%")
                                        ->orWhereHas('kategoriBarang', fn($qk) => $qk->where('nama_kategori', 'like', "%{$search}%"));
                                })
                                ->orWhereHas('jenisBarang', fn($qj) => $qj->where('nama_jenis_barang', 'like', "%{$search}%"));
                        });
                    }),

                TextColumn::make('modal')
                    ->label('Modal')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('hasil')
                    ->label('Hasil')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),
            ])

            /*
            |=====================================================
            | ➕ HEADER ACTIONS
            |=====================================================
            */
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            /*
            |=====================================================
            | ✏️ RECORD ACTIONS
            |=====================================================
            */
            ->recordActions([
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            /*
            |=====================================================
            | 🧹 BULK ACTIONS
            |=====================================================
            */
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            /*
            |=====================================================
            | 📌 DEFAULT GROUP
            |=====================================================
            */
            ->defaultGroup('id_pegawai_nyusup');
    }
}
