<?php

namespace App\Filament\Resources\TriplekHasilHps\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use App\Models\JenisBarang;
use App\Models\Grade;
use App\Models\Ukuran;
use App\Models\BarangSetengahJadiHp; 
use App\Models\Mesin;
use App\Models\RencanaKerjaHp; // Pastikan ini ada
use Illuminate\Database\Eloquent\Builder;

class TriplekHasilHpForm
{
    public static function configure(Schema $schema): Schema
    {
        // Fungsi pembantu untuk mengambil Rencana Kerja terakhir
        $getLastRencana = fn($livewire) => 
            $livewire->ownerRecord
                ?->rencanaKerjaHp()
                ->latest()
                ->with('barangSetengahJadiHp')
                ->first();

        return $schema
            ->columns(2)
            ->components([

                // =========================================================================
                // 1. FILTER GRADE (OPSIONAL) - Sesuai PlatformHasilHpForm
                // =========================================================================
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
                    ->dehydrated(false), // HANYA FILTER

                // =========================================================================
                // 2. FILTER JENIS BARANG (OPSIONAL) - Sesuai PlatformHasilHpForm
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
                    ->dehydrated(false), // HANYA FILTER

                // =========================================================================
                // 3. BARANG SETENGAH JADI (SELECT UTAMA) - Disimpan sebagai id_barang_setengah_jadi
                // =========================================================================
                Select::make('id_barang_setengah_jadi')
                    ->label('Barang Setengah Jadi')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {

                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang']);

                        // FILTER BERDASARKAN INPUT
                        if ($get('grade_id')) {
                            $query->where('id_grade', $get('grade_id'));
                        }

                        if ($get('jenis_barang_id_filter')) {
                            $query->where('id_jenis_barang', $get('jenis_barang_id_filter'));
                        }

                        // Batasi hasil jika tidak ada filter
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
                    ->afterStateHydrated(function (callable $set, callable $get, $livewire) use ($getLastRencana) {
                        if ($get('id_barang_setengah_jadi')) return;

                        $last = $getLastRencana($livewire);

                        if ($last?->barangSetengahJadiHp) {
                            $set('id_barang_setengah_jadi', $last->barangSetengahJadiHp->id);
                        }
                    })
                    ->columnSpanFull(), 

                // Field `barang_setengah_jadi_text`, `jenis_barang_id`, `id_grade`, dan `id_ukuran` 
                // dari skema Select Berantai DIHAPUS karena digantikan oleh Select tunggal di atas.
                // Logika hidden ID juga digabung ke Select utama.

                // =========================================================================
                // 4. FIELD LAIN
                // =========================================================================
                Select::make('id_mesin')
                    ->label('Mesin Hotpress')
                    ->options(
                        Mesin::whereHas('kategoriMesin', fn ($q) =>
                            $q->where('nama_kategori_mesin', 'HOTPRESS')
                        )
                        ->orderBy('nama_mesin')
                        ->pluck('nama_mesin', 'id')
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('isi')->numeric()->required()->label('Isi'),
                TextInput::make('no_palet')->numeric()->required()->label('Nomor Palet'),
            ]);
    }
}