<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Schemas;

use App\Models\DetailBarangDikerjakan;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\PegawaiNyusup;
use App\Models\SerahTerimaGudangSatu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DetailBarangDikerjakanForm
{
    /**
     * Hitung sisa REAL dari sebuah SerahTerimaGudangSatu, dikecualikan dari
     * modal milik record yang sedang diedit (kalau ada), supaya waktu edit
     * form tidak salah menganggap modal lama sudah "makan" sisa dua kali.
     */
    private static function hitungSisaUntukForm(?SerahTerimaGudangSatu $serahTerima, $editingRecordId = null): float
    {
        if (! $serahTerima) {
            return 0;
        }

        // getSisaAttribute() di model sudah menghitung total pemakaian dari
        // BahanTerimaGudangSatu + semua DetailBarangDikerjakan (termasuk record
        // yang sedang diedit kalau modalnya sudah tersimpan). Jadi kalau sedang
        // edit, kita tambahkan kembali modal record ini supaya tidak dobel kurang.
        $sisa = $serahTerima->sisa;

        if ($editingRecordId) {
            $modalRecordIni = DetailBarangDikerjakan::where('id', $editingRecordId)->value('modal') ?? 0;
            $sisa += (float) $modalRecordIni;
        }

        return (float) $sisa;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_pegawai_nyusup')
                    ->label('Pegawai')
                    ->required()
                    ->searchable()
                    ->options(function ($livewire) {
                        $produksi = $livewire->getOwnerRecord();

                        if (! $produksi) {
                            return [];
                        }

                        return PegawaiNyusup::with('pegawai')
                            ->where('id_produksi_nyusup', $produksi->id)
                            ->get()
                            ->mapWithKeys(fn ($p) => [
                                $p->id => $p->pegawai->nama_pegawai,
                            ]);
                    })
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (DENGAN KATEGORI)
                |--------------------------------------------------------------------------
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
                                $g->id => $g->nama_grade,
                            ])
                    )
                    ->live()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                Select::make('jenis_barang_id_filter')
                    ->label('Filter Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->live()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang')
                    ->dehydrated(false),

                /*
                |--------------------------------------------------------------------------
                | BARANG: diambil dari SerahTerimaGudangSatu yang sudah diterima
                | dan tujuan = nyusup, lengket ke produksi nyusup ini.
                |--------------------------------------------------------------------------
                */
                Select::make('id_serah_terima_gudang_satu')
                    ->label('Barang (dari Serah Terima)')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(function (callable $get, $livewire, ?DetailBarangDikerjakan $record) {
                        $produksi = $livewire->getOwnerRecord();

                        if (! $produksi) {
                            return [];
                        }

                        $editingRecordId = $record?->id;

                        $records = SerahTerimaGudangSatu::query()
                            ->with([
                                'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                                'hasilPilihPlywood.barangSetengahJadiHp.grade',
                                'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                                'hasilTerimaGudangSatu.jenisBarang',
                                'hasilTerimaGudangSatu.grade',
                                'hasilTerimaGudangSatu.ukuran',
                            ])
                            ->where('tujuan', 'nyusup')
                            ->where('id_produksi_nyusup', $produksi->id)
                            ->where('diterima_oleh', '!=', '-')
                            ->get();

                        return $records
                            ->filter(function ($r) use ($get, $editingRecordId) {
                                $sisaUntukForm = self::hitungSisaUntukForm($r, $editingRecordId);

                                // Sembunyikan yang sisanya sudah habis (atau minus karena race condition),
                                // kecuali ini adalah record yang sedang diedit sendiri (supaya opsi lama tetap muncul).
                                $isCurrentlySelected = $editingRecordId
                                    && (string) $get('id_serah_terima_gudang_satu') === (string) $r->id;

                                if ($sisaUntukForm <= 0 && ! $isCurrentlySelected) {
                                    return false;
                                }

                                $b = $r->hasilPilihPlywood?->barangSetengahJadiHp ?? $r->hasilTerimaGudangSatu;

                                if (! $b) {
                                    return false;
                                }

                                if ($get('grade_id') && $b->id_grade != $get('grade_id')) {
                                    return false;
                                }

                                if ($get('jenis_barang_id_filter') && $b->id_jenis_barang != $get('jenis_barang_id_filter')) {
                                    return false;
                                }

                                return true;
                            })
                            ->mapWithKeys(function ($r) use ($editingRecordId) {
                                $b = $r->hasilPilihPlywood?->barangSetengahJadiHp ?? $r->hasilTerimaGudangSatu;

                                $sisaUntukForm = self::hitungSisaUntukForm($r, $editingRecordId);

                                $label = ($b?->ukuran?->tebal ?? $b?->ukuran?->nama_ukuran ?? '-').' | '.
                                    ($b?->grade?->nama_grade ?? '-').' | '.
                                    ($b?->jenisBarang?->nama_jenis_barang ?? '-').
                                    ' (sisa: '.$sisaUntukForm.')';

                                return [$r->id => $label];
                            });
                    })
                    ->helperText(function (callable $get, ?DetailBarangDikerjakan $record) {
                        $id = $get('id_serah_terima_gudang_satu');

                        if (! $id) {
                            return null;
                        }

                        $serahTerima = SerahTerimaGudangSatu::find($id);
                        $sisa = self::hitungSisaUntukForm($serahTerima, $record?->id);

                        return "Sisa tersedia saat ini: {$sisa}";
                    })
                    ->columnSpanFull(),

                TextInput::make('modal')
                    ->label('Modal Nyusup')
                    ->numeric()
                    ->minValue(1)
                    ->live(onBlur: true)
                    ->required()
                    ->helperText(function (callable $get, ?DetailBarangDikerjakan $record) {
                        $id = $get('id_serah_terima_gudang_satu');

                        if (! $id) {
                            return 'Pilih barang dari Serah Terima terlebih dahulu.';
                        }

                        $serahTerima = SerahTerimaGudangSatu::find($id);
                        $sisa = self::hitungSisaUntukForm($serahTerima, $record?->id);

                        return "Maksimal modal: {$sisa} (sesuai sisa Serah Terima).";
                    })
                    ->rules(function (callable $get, ?DetailBarangDikerjakan $record) {
                        return [
                            function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                $id = $get('id_serah_terima_gudang_satu');

                                if (! $id) {
                                    // Biarkan validasi 'required' di field select yang menangani ini.
                                    return;
                                }

                                $serahTerima = SerahTerimaGudangSatu::find($id);

                                if (! $serahTerima) {
                                    $fail('Data Serah Terima tidak ditemukan.');

                                    return;
                                }

                                $sisa = self::hitungSisaUntukForm($serahTerima, $record?->id);

                                if ((float) $value > $sisa) {
                                    $fail("Modal ({$value}) melebihi sisa yang tersedia dari Serah Terima ini (sisa: {$sisa}).");
                                }
                            },
                        ];
                    }),

                TextInput::make('hasil')
                    ->label('Hasil Nyusup')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                TextInput::make('no_palet')
                    ->label('No Palet')
                    ->numeric()
                    ->required(),
            ]);
    }
}
