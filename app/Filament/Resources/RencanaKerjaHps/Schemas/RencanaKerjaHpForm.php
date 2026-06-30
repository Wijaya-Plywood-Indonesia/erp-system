<?php

namespace App\Filament\Resources\RencanaKerjaHps\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;

class RencanaKerjaHpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([

                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (DENGAN KATEGORI)
                |--------------------------------------------------------------------------
                */
                Select::make('grade_id')
                    ->label('Grade')
                    ->options(
                        Grade::with('kategoriBarang')
                            ->orderBy('id_kategori_barang')
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    . ' | ' . $g->nama_grade
                            ])
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                /*
                |--------------------------------------------------------------------------
                | FILTER JENIS BARANG
                |--------------------------------------------------------------------------
                */
                Select::make('jenis_barang_id')
                    ->label('Jenis Barang')
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
                | BARANG SETENGAH JADI (HASIL FILTER)
                |--------------------------------------------------------------------------
                */
                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Barang Setengah Jadi')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {

                        $query = BarangSetengahJadiHp::query()
                            ->with([
                                'ukuran',
                                'jenisBarang',
                                'grade.kategoriBarang',
                            ])
                            // ✅ AMAN: pakai relasi, bukan nama tabel
                            ->joinRelationship('jenisBarang')
                            ->joinRelationship('ukuran');

                        // FILTER GRADE
                        if ($get('grade_id')) {
                            $query->where('barang_setengah_jadi_hp.id_grade', $get('grade_id'));
                        }

                        // FILTER JENIS BARANG (opsional)
                        if ($get('jenis_barang_id')) {
                            $query->where('barang_setengah_jadi_hp.id_jenis_barang', $get('jenis_barang_id'));
                        }

                        // 🔥 URUTAN SESUAI KEINGINAN
                        $query
                            ->orderBy('jenis_barang.nama_jenis_barang', 'asc') // Meranti → Sengon
                            ->orderBy('ukurans.tebal', 'asc')                  // 3 → 4 → 5
                            ->orderBy('barang_setengah_jadi_hp.id', 'asc');

                        return $query
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(function ($b) {

                                $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                                $ukuran   = $b->ukuran?->nama_ukuran ?? '-';
                                $grade    = $b->grade?->nama_grade ?? '-';
                                $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';

                                return [
                                    $b->id => "{$kategori} | {$ukuran} | {$grade} | {$jenis}"
                                ];
                            });
                    })
                    ->columnSpanFull(),


                /*
                |--------------------------------------------------------------------------
                | JUMLAH PRODUKSI
                |--------------------------------------------------------------------------
                */
                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
            ]);
    }
}
