<?php

namespace App\Filament\Resources\DetailKomposisis\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\Komposisi;
use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;

class DetailKomposisiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([

                /*
                |--------------------------------------------------------------------------
                | KOMPOSISI UTAMA (TIDAK RESET)
                |--------------------------------------------------------------------------
                */
                Select::make('id_komposisi')
    ->label('Komposisi Utama')
    ->required()
    ->searchable()
    ->preload()
    ->options(function () {
        return Komposisi::query()
            ->with([
                'barangSetengahJadiHp.ukuran',
                'barangSetengahJadiHp.jenisBarang',
                'barangSetengahJadiHp.grade.kategoriBarang'
            ])
            ->limit(100)
            ->get()
            ->mapWithKeys(function ($k) {
                $bsj = $k->barangSetengahJadiHp;

                $kategori = $bsj?->grade?->kategoriBarang?->nama_kategori ?? 'Kategori?';
                $ukuran   = $bsj?->ukuran?->nama_ukuran ?? 'Ukuran?';
                $jenis    = $bsj?->jenisBarang?->nama_jenis_barang ?? 'Jenis?';
                $grade    = $bsj?->grade?->nama_grade ?? 'Grade?';

                return [
                    $k->id => sprintf(
                        '%s | %s | %s | %s',
                        $kategori,
                        $ukuran,
                        $jenis,
                        $grade
                    )
                ];
            });
    })
    ->afterStateUpdated(fn ($state) => session(['detail_last_komposisi' => $state]))
    ->default(fn () => session('detail_last_komposisi'))
    ->columnSpanFull(),


                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (TIDAK RESET)
                |--------------------------------------------------------------------------
                */
                Select::make('grade_id')
                    ->label('Filter Grade (Hanya Veneer)')
                    ->dehydrated(false)
                    ->options(
                        Grade::query()
                            ->with('kategoriBarang')
                            ->whereHas('kategoriBarang', fn($q) => $q->where('nama_kategori', 'Veneer'))
                            ->orderBy('id_kategori_barang')
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori') . ' | ' . $g->nama_grade
                            ])
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Pilih Grade Veneer')
                    ->afterStateUpdated(fn ($state) => session(['detail_last_grade' => $state]))
                    ->default(fn () => session('detail_last_grade')),

                /*
                |--------------------------------------------------------------------------
                | FILTER JENIS BARANG (TIDAK RESET)
                |--------------------------------------------------------------------------
                */
                Select::make('jenis_barang_id')
                    ->label('Filter Jenis Barang')
                    ->dehydrated(false)
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang')
                    ->afterStateUpdated(fn ($state) => session(['detail_last_jenis_barang' => $state]))
                    ->default(fn () => session('detail_last_jenis_barang')),

                /*
                |--------------------------------------------------------------------------
                | BARANG SETENGAH JADI (TIDAK RESET)
                |--------------------------------------------------------------------------
                */
                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Barang Setengah Jadi (HP)')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {
                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang']);

                        if ($get('grade_id')) {
                            $query->where('id_grade', $get('grade_id'));
                        }

                        if ($get('jenis_barang_id')) {
                            $query->where('id_jenis_barang', $get('jenis_barang_id'));
                        }

                        $query->whereHas('grade.kategoriBarang', fn($q) => $q->where('nama_kategori', 'Veneer'));

                        if (!$get('grade_id') && !$get('jenis_barang_id')) {
                            $query->limit(50);
                        }

                        return $query->orderBy('id', 'desc')
                            ->get()
                            ->mapWithKeys(function ($b) {
                                return [
                                    $b->id => sprintf(
                                        '%s | %s | %s | %s',
                                        $b->grade?->kategoriBarang?->nama_kategori ?? 'Kategori?',
                                        $b->ukuran?->nama_ukuran ?? 'Ukuran?',
                                        $b->grade?->nama_grade ?? 'Grade?',
                                        $b->jenisBarang?->nama_jenis_barang ?? 'Jenis?'
                                    )
                                ];
                            });
                    })
                    ->columnSpanFull()
                    ->afterStateUpdated(fn ($state) => session(['detail_last_barang_hp' => $state]))
                    ->default(fn () => session('detail_last_barang_hp')),

                /*
                |--------------------------------------------------------------------------
                | FIELD YANG HARUS DI-RESET
                |--------------------------------------------------------------------------
                */

                TextInput::make('lapisan')
                    ->label('Lapisan ke-')
                    ->required()
                    ->maxLength(255),

                TextInput::make('keterangan')
                    ->label('Keterangan Lapisan')
                    ->nullable(),
            ]);
    }
}
