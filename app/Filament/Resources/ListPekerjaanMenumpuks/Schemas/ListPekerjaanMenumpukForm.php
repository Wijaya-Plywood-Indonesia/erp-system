<?php

namespace App\Filament\Resources\ListPekerjaanMenumpuks\Schemas;

use App\Models\HasilPilihPlywood;
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
                    ->options(
                        HasilPilihPlywood::query()
                            ->where('kondisi', 'reparasi')
                            ->with(['barangSetengahJadiHp.jenisBarang', 'barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade'])
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => 
                                    ($item->barangSetengahJadiHp->jenisBarang->nama_jenis_barang ?? '-') . " | " .
                                    ($item->barangSetengahJadiHp->ukuran->nama_ukuran ?? '-') . " | " .
                                    ($item->barangSetengahJadiHp->grade->nama_grade ?? '-') . " — " .
                                    $item->jenis_cacat . " ({$item->jumlah} Lbr)"
                            ])
                    )
                    ->required()
                    ->searchable() // Menambahkan fitur pencarian agar lebih mudah
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $item = HasilPilihPlywood::find($state);
                        $jumlahAsal = $item?->jumlah ?? 0;
                        $set('jumlah_asal', $jumlahAsal);
                        $set('jumlah_belum_selesai', $jumlahAsal);
                        $set('status', 'belum selesai');
                    })
                    ->columnSpanFull(),

                TextInput::make('jumlah_asal')
                    ->label('Total Harus Direparasi')
                    ->numeric()
                    ->readOnly() // Menggunakan readOnly agar tetap terlihat aktif tapi tidak bisa diubah manual
                    ->dehydrated(),

                TextInput::make('jumlah_selesai')
                    ->label('Jumlah Sudah Dikerjakan')
                    ->numeric()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $get, $set) {
                        $asal = (int) $get('jumlah_asal');
                        $selesai = (int) $state;
                        $sisa = $asal - $selesai;
                        
                        $set('jumlah_belum_selesai', $sisa < 0 ? 0 : $sisa);
                        
                        // Logika otomatis update status saat input jumlah
                        if ($selesai >= $asal && $asal > 0) {
                            $set('status', 'selesai');
                        } else {
                            $set('status', 'belum selesai');
                        }
                    }),

                TextInput::make('jumlah_belum_selesai')
                    ->label('Sisa Yang Belum Selesai')
                    ->numeric()
                    ->readOnly()
                    ->prefix('Pcs')
                    ->helperText('Otomatis berkurang saat jumlah selesai diisi'),

                Select::make('status')
                    ->label('Status Pekerjaan')
                    ->options([
                        'belum selesai' => 'Belum Selesai',
                        'selesai' => 'Selesai',
                    ])
                    ->required()
                    // Hapus ->disabled() agar bisa dipencet/disimpan, 
                    // atau gunakan ->selectable(false) jika hanya ingin display
                    ->native(false),
            ]);
    }
}