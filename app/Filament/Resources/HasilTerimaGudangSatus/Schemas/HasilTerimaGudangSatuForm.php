<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Schemas;

use App\Models\BahanTerimaGudangSatu;
use App\Models\Grade;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HasilTerimaGudangSatuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kombinasi_bahan')
                    ->label('Ukuran (Jenis Barang | Ukuran)')
                    ->searchable()
                    ->required()
                    ->dehydrated(false)
                    ->options(function () {
                        return BahanTerimaGudangSatu::query()
                            ->with('barangSetengahJadiHp.jenisBarang', 'barangSetengahJadiHp.ukuran')
                            ->get()
                            ->map(fn ($b) => $b->barangSetengahJadiHp)
                            ->filter(fn ($b) => $b?->jenisBarang && $b?->ukuran)
                            ->unique(fn ($b) => $b->id_jenis_barang.'-'.$b->id_ukuran)
                            ->sortBy(fn ($b) => $b->ukuran->tebal ?? 0)
                            ->mapWithKeys(fn ($b) => [
                                $b->id_jenis_barang.'-'.$b->id_ukuran => $b->jenisBarang->nama_jenis_barang.' | '.$b->ukuran->dimensi,
                            ]);
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        [$idJenis, $idUkuran] = array_pad(explode('-', (string) $state, 2), 2, null);
                        $set('id_jenis_barang', $idJenis);
                        $set('id_ukuran', $idUkuran);
                    })
                    ->live()
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $idJenis = $get('id_jenis_barang');
                        $idUkuran = $get('id_ukuran');
                        if ($idJenis && $idUkuran) {
                            $set('kombinasi_bahan', $idJenis.'-'.$idUkuran);
                        }
                    }),

                Hidden::make('id_jenis_barang')->required(),
                Hidden::make('id_ukuran')->required(),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->required(),

                Select::make('id_grade')
                    ->label('Grade')
                    ->options(
                        Grade::whereHas('kategoriBarang', function ($q) {
                            $q->where('nama_kategori', 'PLYWOOD');
                        })
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn ($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    .' | '.$g->nama_grade,
                            ])
                    )
                    ->searchable()
                    ->required(),

                Textarea::make('ket')
                    ->label('Keterangan')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
