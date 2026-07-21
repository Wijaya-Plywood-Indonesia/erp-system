<?php

namespace App\Filament\Resources\BahanPilihPlywoods\Schemas;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BahanPilihPlywoodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (DENGAN KATEGORI)
                |--------------------------------------------------------------------------
                | Murni filter bantu untuk mempersempit opsi Barang Setengah Jadi di bawah.
                | Tidak lagi diisi otomatis dari data Serah Terima / Palet.
                */
                Select::make('grade_id')
                    ->label('Filter Grade')
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

                /*
                |--------------------------------------------------------------------------
                | BARANG SETENGAH JADI (BEBAS DIPILIH, TIDAK TERGANTUNG PALET)
                |--------------------------------------------------------------------------
                */
                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Barang Setengah Jadi (Plywood)')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {
                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->where('nama_kategori', 'PLYWOOD');
                            });

                        // Terapkan filter jika ada
                        if ($get('grade_id')) {
                            $query->where('id_grade', $get('grade_id'));
                        }
                        if ($get('jenis_barang_id_filter')) {
                            $query->where('id_jenis_barang', $get('jenis_barang_id_filter'));
                        }

                        return $query->get()->mapWithKeys(function ($b) {
                            return [
                                $b->id => ($b->grade?->kategoriBarang?->nama_kategori ?? '-').' | '.
                                    ($b->ukuran?->nama_ukuran ?? '-').' | '.
                                    ($b->grade?->nama_grade ?? '-').' | '.
                                    ($b->jenisBarang?->nama_jenis_barang ?? '-'),
                            ];
                        });
                    })
                    ->getOptionLabelUsing(function ($value) {
                        $b = BarangSetengahJadiHp::with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])->find($value);

                        if (! $b) {
                            return '-';
                        }

                        return ($b->grade?->kategoriBarang?->nama_kategori ?? '-').' | '.
                               ($b->ukuran?->nama_ukuran ?? '-').' | '.
                               ($b->grade?->nama_grade ?? '-').' | '.
                               ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    })
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | NO PALET & JUMLAH (INPUT MANUAL BEBAS)
                |--------------------------------------------------------------------------
                | Tidak lagi ada validasi/hint terhadap sisa stok dari Serah Terima Triplek Jadi.
                */
                TextInput::make('no_palet')
                    ->label('No Palet')
                    ->numeric()
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->required(),
            ]);
    }
}
