<?php

namespace App\Filament\Resources\ListPekerjaanMenumpuks\Schemas;

use App\Models\HasilPilihPlywood;
use App\Models\ListPekerjaanMenumpuk;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class ListPekerjaanMenumpukForm
{
    public static function configure($schema)
    {
        return $schema
            ->components([
                Select::make('id_hasil_pilih_plywood')
                    ->label('Pilih Bahan Reparasi')
                    ->options(function () {
                        return HasilPilihPlywood::query()
                            ->where('kondisi', 'reparasi')
                            ->with(['barangSetengahJadiHp.jenisBarang', 'barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade'])
                            ->get()
                            ->mapWithKeys(function ($item) {
                                // 1. Hitung total yang SUDAH dikerjakan untuk barang ini di tabel ListPekerjaanMenumpuk
                                $totalSelesai = ListPekerjaanMenumpuk::where('id_hasil_pilih_plywood', $item->id)
                                    ->sum('jumlah_selesai');

                                // 2. Hitung sisa riil yang tersedia
                                $sisaTersedia = $item->jumlah - $totalSelesai;

                                // 3. Hanya tampilkan jika sisa masih lebih dari 0
                                if ($sisaTersedia > 0) {
                                    return [
                                        $item->id => 
                                            ($item->barangSetengahJadiHp->jenisBarang->nama_jenis_barang ?? '-') . " | " .
                                            ($item->barangSetengahJadiHp->ukuran->nama_ukuran ?? '-') . " | " .
                                            ($item->barangSetengahJadiHp->grade->nama_grade ?? '-') . " — " .
                                            $item->jenis_cacat . " (Sisa: {$sisaTersedia} Lbr)"
                                    ];
                                }

                                return [];
                            })
                            ->filter(); // Menghapus baris kosong
                    })
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state) return;

                        $item = HasilPilihPlywood::find($state);
                        
                        // Hitung sisa riil untuk mengisi input jumlah_asal
                        $totalSelesai = ListPekerjaanMenumpuk::where('id_hasil_pilih_plywood', $state)
                            ->sum('jumlah_selesai');
                        
                        $sisaReal = ($item?->jumlah ?? 0) - $totalSelesai;

                        $set('jumlah_asal', $sisaReal);
                        $set('jumlah_belum_selesai', $sisaReal);
                        $set('status', 'belum selesai');
                    })
                    ->columnSpanFull(),

                TextInput::make('jumlah_asal')
                    ->label('Sisa Tersedia Untuk Direparasi')
                    ->numeric()
                    ->readOnly()
                    ->dehydrated()
                    ->helperText('Jumlah sisa yang belum terdaftar di pekerjaan manapun'),

                TextInput::make('jumlah_selesai')
                    ->label('Jumlah Dikerjakan Saat Ini')
                    ->numeric()
                    ->required()
                    ->reactive()
                    ->minValue(1)
                    ->maxValue(fn ($get) => $get('jumlah_asal')) // Mencegah input melebihi sisa
                    ->afterStateUpdated(function ($state, $get, $set) {
                        $asal = (int) $get('jumlah_asal');
                        $selesai = (int) $state;
                        $sisa = $asal - $selesai;
                        
                        $set('jumlah_belum_selesai', $sisa < 0 ? 0 : $sisa);
                        
                        // Otomatis set status selesai jika input sama dengan sisa tersedia
                        if ($selesai >= $asal && $asal > 0) {
                            $set('status', 'selesai');
                        } else {
                            $set('status', 'belum selesai');
                        }
                    }),

                TextInput::make('jumlah_belum_selesai')
                    ->label('Sisa Setelah Pekerjaan Ini')
                    ->numeric()
                    ->readOnly()
                    ->prefix('Pcs')
                    ->helperText('Otomatis berkurang saat jumlah dikerjakan diisi'),

                Select::make('status')
                    ->label('Status Pekerjaan')
                    ->options([
                        'belum selesai' => 'Belum Selesai',
                        'selesai' => 'Selesai',
                    ])
                    ->required()
                    ->native(false),
            ]);
    }
}