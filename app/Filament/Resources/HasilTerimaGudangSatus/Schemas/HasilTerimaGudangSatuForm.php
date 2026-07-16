<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Schemas;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
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
                Select::make('grade_id_filter')
                    ->label('Filter Grade')
                    ->options(
                        Grade::whereHas('kategoriBarang', function ($q) {
                            $q->where('nama_kategori', 'PLYWOOD');
                        })
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn ($g) => [
                                $g->id => $g->nama_grade,
                            ])
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                Select::make('jenis_barang_id_filter')
                    ->label('Filter Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang')
                    ->dehydrated(false),

                Select::make('kombinasi_bahan')
                    ->label('Pilih Barang (Ukuran | Jenis Barang | Grade)')
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->dehydrated(false)
                    ->options(function (callable $get) {

                        $query = BarangSetengahJadiHp::query()
                            ->with(['jenisBarang', 'ukuran', 'grade.kategoriBarang'])
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->where('nama_kategori', 'PLYWOOD');
                            })
                            ->joinRelationship('jenisBarang')
                            ->joinRelationship('ukuran');

                        // FILTER GRADE
                        if ($get('grade_id_filter')) {
                            $query->where('barang_setengah_jadi_hp.id_grade', $get('grade_id_filter'));
                        }

                        // FILTER JENIS BARANG
                        if ($get('jenis_barang_id_filter')) {
                            $query->where(
                                'barang_setengah_jadi_hp.id_jenis_barang',
                                $get('jenis_barang_id_filter')
                            );
                        }

                        $query
                            ->orderBy('ukurans.tebal', 'asc')
                            ->orderBy('barang_setengah_jadi_hp.id', 'asc');

                        return $query->get()
                            ->filter(fn ($b) => $b?->jenisBarang && $b?->ukuran && $b?->grade)
                            ->mapWithKeys(fn ($b) => [
                                $b->id_jenis_barang.'-'.$b->id_ukuran.'-'.$b->id_grade => ($b->ukuran?->dimensi ?? '-').' | '.
                                    ($b->jenisBarang?->nama_jenis_barang ?? '-').' | '.
                                    ($b->grade?->nama_grade ?? '-'),
                            ]);
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        [$idJenis, $idUkuran, $idGrade] = array_pad(explode('-', (string) $state, 3), 3, null);
                        $set('id_jenis_barang', $idJenis);
                        $set('id_ukuran', $idUkuran);
                        $set('id_grade', $idGrade);
                    })
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $idJenis = $get('id_jenis_barang');
                        $idUkuran = $get('id_ukuran');
                        $idGrade = $get('id_grade');
                        if ($idJenis && $idUkuran && $idGrade) {
                            $set('kombinasi_bahan', $idJenis.'-'.$idUkuran.'-'.$idGrade);
                        }
                    })
                    ->columnSpanFull(),

                Hidden::make('id_jenis_barang')->required(),
                Hidden::make('id_ukuran')->required(),
                Hidden::make('id_grade')->required(),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->required(),

                Textarea::make('ket')
                    ->label('Keterangan')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
