<?php

namespace App\Filament\Resources\HasilSandings\Schemas;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HasilSandingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (HANYA PLATFORM & PLYWOOD)
                |--------------------------------------------------------------------------
                */
                Select::make('grade_id')
                    ->label('Grade')
                    ->options(
                        Grade::with('kategoriBarang')
                            ->whereHas('kategoriBarang', function ($q) {
                                $q->whereIn('nama_kategori', ['PLATFORM', 'PLYWOOD']);
                            })
                            ->get()
                            ->mapWithKeys(fn ($g) => [
                                $g->id =>
                                    ($g->kategoriBarang?->nama_kategori ?? '-') .
                                    ' - ' .
                                    $g->nama_grade
                            ])
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Grade'),

                /*
                |--------------------------------------------------------------------------
                | FILTER JENIS BARANG
                |--------------------------------------------------------------------------
                */
                Select::make('id_jenis_barang')
                    ->label('Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang'),

                /*
                |--------------------------------------------------------------------------
                | BARANG SETENGAH JADI
                |--------------------------------------------------------------------------
                */
                Select::make('id_barang_setengah_jadi')
                    ->label('Barang Setengah Jadi')

                    // OPTIONS SAAT CREATE
                    ->options(function (callable $get) {

                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])

                            // 🔒 HANYA PLATFORM & PLYWOOD
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->whereIn('nama_kategori', ['PLATFORM', 'PLYWOOD']);
                            });

                        if ($get('grade_id')) {
                            $query->where('id_grade', $get('grade_id'));
                        }

                        if ($get('id_jenis_barang')) {
                            $query->where('id_jenis_barang', $get('id_jenis_barang'));
                        }

                        if (!$get('grade_id') && !$get('id_jenis_barang')) {
                            $query->limit(50);
                        }

                        return $query
                            ->orderBy('id', 'desc')
                            ->get()
                            ->mapWithKeys(function ($b) {

                                $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                                $ukuran   = $b->ukuran?->dimensi ?? '-';
                                $grade    = $b->grade?->nama_grade ?? '-';
                                $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';

                                return [
                                    $b->id => "{$kategori} — {$ukuran} — {$grade} — {$jenis}"
                                ];
                            });
                    })

                    // LABEL SAAT EDIT
                    ->getOptionLabelUsing(function ($value) {

                        $b = BarangSetengahJadiHp::with([
                            'ukuran',
                            'jenisBarang',
                            'grade.kategoriBarang'
                        ])->find($value);

                        if (!$b) {
                            return $value;
                        }

                        $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran   = $b->ukuran?->dimensi ?? '-';
                        $grade    = $b->grade?->nama_grade ?? '-';
                        $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$kategori} — {$ukuran} — {$grade} — {$jenis}";
                    })

                    ->searchable()
                    ->placeholder('Pilih Barang'),

                /*
                |--------------------------------------------------------------------------
                | INPUT DATA
                |--------------------------------------------------------------------------
                */
                TextInput::make('kuantitas')
                    ->numeric()
                    ->required(),

                TextInput::make('jumlah_sanding_face')
                    ->label('Jumlah Sanding Face (Pass)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                TextInput::make('jumlah_sanding_back')
                    ->label('Jumlah Sanding Back (Pass)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                TextInput::make('no_palet')
                    ->numeric()
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'Selesai 1 Sisi' => 'Selesai 1 Sisi',
                        'Selesai 2 Sisi' => 'Selesai 2 Sisi',
                        'Belum Selesai'  => 'Belum Selesai',
                    ])
                    ->default('Belum Selesai')
                    ->required(),
            ]);
    }
}
