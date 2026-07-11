<?php

namespace App\Filament\Resources\BahanTerimaGudangSatus\Schemas;

use App\Models\BahanTerimaGudangSatu;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\SerahTerimaGudangSatu;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BahanTerimaGudangSatuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Select::make('grade_id')
                //     ->label('Filter Grade')
                //     ->options(
                //         Grade::whereHas('kategoriBarang', function ($q) {
                //             $q->where('nama_kategori', 'PLYWOOD');
                //         })
                //             ->orderBy('nama_grade')
                //             ->get()
                //             ->mapWithKeys(fn ($g) => [
                //                 $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                //                     .' | '.$g->nama_grade,
                //             ])
                //     )
                //     ->reactive()
                //     ->searchable()
                //     ->placeholder('Semua Grade')
                //     ->dehydrated(false),

                // Select::make('jenis_barang_id_filter')
                //     ->label('Filter Jenis Barang')
                //     ->options(
                //         JenisBarang::orderBy('nama_jenis_barang')
                //             ->pluck('nama_jenis_barang', 'id')
                //     )
                //     ->reactive()
                //     ->searchable()
                //     ->placeholder('Semua Jenis Barang')
                //     ->dehydrated(false),

                Select::make('id_serah_terima_gudang_satu')
                    ->label('Bahan (Serah Terima Gudang Satu)')
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->options(function (callable $get) {
                        $query = SerahTerimaGudangSatu::query()
                            ->with([
                                'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                                'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                                'hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang',
                            ])
                            ->where('diterima_oleh', '!=', '-')
                            ->whereHas('hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang', function ($q) {
                                $q->where('nama_kategori', 'PLYWOOD');
                            });

                        if ($get('grade_id')) {
                            $query->whereHas('hasilPilihPlywood.barangSetengahJadiHp', function ($q) use ($get) {
                                $q->where('id_grade', $get('grade_id'));
                            });
                        }

                        if ($get('jenis_barang_id_filter')) {
                            $query->whereHas('hasilPilihPlywood.barangSetengahJadiHp', function ($q) use ($get) {
                                $q->where('id_jenis_barang', $get('jenis_barang_id_filter'));
                            });
                        }

                        return $query
                            ->get()
                            // sisa dihitung via accessor (bukan kolom DB), jadi difilter di PHP
                            ->filter(fn ($s) => $s->sisa > 0)
                            ->sortBy(fn ($s) => $s->barangSetengahJadi?->ukuran?->tebal ?? 0)
                            ->mapWithKeys(function ($s) {
                                $b = $s->barangSetengahJadi;

                                return [
                                    $s->id => ($b?->grade?->kategoriBarang?->nama_kategori ?? '-').' | '.
                                        ($b?->ukuran?->nama_ukuran ?? '-').' | '.
                                        ($b?->grade?->nama_grade ?? '-').' | '.
                                        ($b?->jenisBarang?->nama_jenis_barang ?? '-').
                                        ' (Sisa: '.rtrim(rtrim(number_format($s->sisa, 2, ',', '.'), '0'), ',').')',
                                ];
                            });
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        // reset jumlah biar tidak kebawa nilai lama yang mungkin melebihi sisa baru
                        $set('jumlah', null);

                        $s = $state ? SerahTerimaGudangSatu::find($state) : null;
                        $set('sisa_info', $s ? number_format($s->sisa, 2, ',', '.') : '-');
                    }),

                TextInput::make('sisa_info')
                    ->label('Sisa Tersedia')
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $id = $get('id_serah_terima_gudang_satu');
                        $s = $id ? SerahTerimaGudangSatu::find($id) : null;
                        $set('sisa_info', $s ? number_format($s->sisa, 2, ',', '.') : '-');
                    }),

                TextInput::make('no_palet')
                    ->label('No Palet')
                    ->numeric()
                    ->required()
                    ->default(fn () => BahanTerimaGudangSatu::count() + 1),

                TextInput::make('jumlah')
                    ->label('Jumlah Dikerjakan Hari Ini')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(function (callable $get) {
                        $id = $get('id_serah_terima_gudang_satu');
                        if (! $id) {
                            return null;
                        }
                        $s = SerahTerimaGudangSatu::find($id);

                        return $s?->sisa;
                    }),
            ]);
    }
}
