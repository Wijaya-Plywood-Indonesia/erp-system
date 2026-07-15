<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Schemas;

use App\Models\BahanTerimaGudangSatu;
use App\Models\BarangSetengahJadiHp;
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
                    ->label('Pilih Barang (Kategori | Ukuran | Jenis Barang | Grade)')
                    ->searchable()
                    ->required()
                    ->dehydrated(false)
                    ->options(function () {
                        // Mengambil langsung dari master data Barang Setengah Jadi HP
                        return BarangSetengahJadiHp::query()
                            ->with(['jenisBarang', 'ukuran', 'grade.kategoriBarang'])
                            ->get()
                            // Menyaring data agar tidak error jika ada relasi yang kosong di database
                            ->filter(fn($b) => $b?->jenisBarang && $b?->ukuran && $b?->grade)
                            // Mengurutkan pilihan berdasarkan ID Grade secara ascending
                            ->sortBy(fn($b) => $b->id_grade)
                            ->mapWithKeys(fn($b) => [
                                // Key menggunakan kombinasi 3 ID sekaligus
                                $b->id_jenis_barang . '-' . $b->id_ukuran . '-' . $b->id_grade =>
                                    ($b->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori') . ' | ' .
                                    ($b->ukuran?->dimensi ?? '-') . ' | ' .
                                    ($b->jenisBarang?->nama_jenis_barang ?? '-') . ' | ' .
                                    ($b->grade?->nama_grade ?? '-')
                            ]);
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Memecah state menjadi 3 variabel ID
                        [$idJenis, $idUkuran, $idGrade] = array_pad(explode('-', (string) $state, 3), 3, null);
                        $set('id_jenis_barang', $idJenis);
                        $set('id_ukuran', $idUkuran);
                        $set('id_grade', $idGrade); // Otomatis mengisi id_grade tanpa perlu input manual
                    })
                    ->live()
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $idJenis = $get('id_jenis_barang');
                        $idUkuran = $get('id_ukuran');
                        $idGrade = $get('id_grade');
                        if ($idJenis && $idUkuran && $idGrade) {
                            $set('kombinasi_bahan', $idJenis . '-' . $idUkuran . '-' . $idGrade);
                        }
                    }),

                Hidden::make('id_jenis_barang')->required(),
                Hidden::make('id_ukuran')->required(),
                Hidden::make('id_grade')->required(),

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
                            ->mapWithKeys(fn($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    . ' | ' . $g->nama_grade,
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
