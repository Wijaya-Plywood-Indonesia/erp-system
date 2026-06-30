<?php

namespace App\Filament\Resources\NotaKayus\Schemas;

use App\Models\DetailTurusanKayu;
use App\Models\KayuMasuk;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class NotaKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_kayu_masuk')
                    ->label('Detail Kayu Masuk')
                    ->options(
                        KayuMasuk::query()
                            ->with('penggunaanSupplier')
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(function ($kayu_masuk) {
                                return [
                                    $kayu_masuk->id => "{$kayu_masuk->tgl_kayu_masuk} - Seri : {$kayu_masuk->seri} - Supplier : {$kayu_masuk->penggunaanSupplier?->nama_supplier}",
                                ];
                            })
                    )
                    ->searchable()
                    ->reactive()
                    /**
                     * LOGIKA KEAMANAN: Validasi Harga
                     * Mencegah pemilihan Kayu Masuk jika ada detail turusan yang harganya 0.
                     */
                    ->rules([
                        fn(): \Closure => function (string $attribute, $value, \Closure $fail) {
                            if (!$value) return;

                            // Cek apakah ada detail turusan yang harganya masih 0 atau null
                            $hasEmptyPrice = DetailTurusanKayu::where('id_kayu_masuk', $value)
                                ->where(function ($query) {
                                    $query->whereNull('harga')->orWhere('harga', '<=', 0);
                                })
                                ->exists();

                            if ($hasEmptyPrice) {
                                $fail("Gagal Memilih: Seri Kayu Masuk ini memiliki batang yang harganya belum terdaftar (0). Harap periksa Master Harga atau Update data turusan terlebih dahulu.");
                            }
                        },
                    ])
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state) {
                            $set('no_nota', null);
                            return;
                        }

                        $kayuMasuk = KayuMasuk::with('penggunaanSupplier')->find($state);
                        if (!$kayuMasuk) {
                            $set('no_nota', null);
                            return;
                        }

                        // Formating No Nota
                        $tgl = Carbon::parse($kayuMasuk->tgl_kayu_masuk)->format('dmY');
                        $seri = $kayuMasuk->seri;
                        $supplierId = $kayuMasuk->id_supplier_kayus;

                        // Hitung urutan berdasarkan tanggal
                        $countToday = KayuMasuk::whereDate('tgl_kayu_masuk', $kayuMasuk->tgl_kayu_masuk)->count() + 1;
                        $noUrut = str_pad($countToday, 3, '0', STR_PAD_LEFT);

                        $noNota = "{$tgl}{$seri}{$supplierId}{$noUrut}";
                        $set('no_nota', $noNota);
                    })
                    ->required(),

                TextInput::make('no_nota')
                    ->label('No Nota')
                    ->disabled()
                    ->dehydrated(true)
                    ->required(),

                TextInput::make('penanggung_jawab')
                    ->label('Penanggung Jawab')
                    ->required(),

                TextInput::make('penerima')
                    ->label('Penerima')
                    ->required(),

                TextInput::make('satpam')
                    ->label('Satpam')
                    ->required(),
            ]);
    }
}
