<?php

namespace App\Filament\Resources\BahanHotPresses\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BarangSetengahJadiHp;
use App\Models\JenisBarang;
use App\Models\Grade;

class BahanHotPressForm
{
    public static function configure(Schema $schema): Schema
    {
        $getLastRencana = fn($livewire) => 
            $livewire->ownerRecord
                ?->rencanaKerjaHp()
                ->latest()
                ->with('barangSetengahJadiHp')
                ->first();

        return $schema
            ->columns(2)
            ->components([
                Select::make('grade_id') 
                    ->label('Filter Grade')
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

                // =========================================================================
                // 2. FILTER JENIS BARANG (OPSIONAL) - TIDAK DEHYDRATED
                // =========================================================================
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

                // =========================================================================
                // 3. BARANG SETENGAH JADI (SELECT UTAMA) - DEHYDRATED (Disimpan)
                // =========================================================================
                Select::make('id_barang_setengah_jadi')
                    ->label('Barang Setengah Jadi')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {

                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang']);

                        // FILTER GRADE
                        if ($get('grade_id')) {
                            $query->where('id_grade', $get('grade_id'));
                        }

                        // FILTER JENIS BARANG
                        if ($get('jenis_barang_id_filter')) {
                            $query->where('id_jenis_barang', $get('jenis_barang_id_filter'));
                        }

                        // Batasi hasil jika tidak ada filter untuk performa
                        if (!$get('grade_id') && !$get('jenis_barang_id_filter')) {
                             $query->limit(50);
                        }

                        return $query
                            ->orderBy('id', 'desc')
                            ->get()
                            ->mapWithKeys(function ($b) {
                                // Format tampilan di select option
                                $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '?';
                                $ukuran   = $b->ukuran?->nama_ukuran ?? '?';
                                $grade    = $b->grade?->nama_grade ?? '?';
                                $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '?';

                                return [
                                    $b->id => "{$kategori} | {$ukuran} | {$grade} | {$jenis}"
                                ];
                            });
                    })
                    // LOGIKA DEFAULT VALUE DARI RENCANA KERJA TERAKHIR
                    ->afterStateHydrated(function (callable $set, callable $get, $livewire) use ($getLastRencana) {
                         // Hanya set default jika field saat ini masih kosong
                        if ($get('id_barang_setengah_jadi')) return;

                        $last = $getLastRencana($livewire);

                        if ($last?->barangSetengahJadiHp) {
                            $set('id_barang_setengah_jadi', $last->barangSetengahJadiHp->id);
                        }
                    })
                    ->columnSpanFull(), 

                    TextInput::make('isi')
                    ->label('Isi')
                    ->numeric()
                    ->required(),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),
            ]);
    }
}
