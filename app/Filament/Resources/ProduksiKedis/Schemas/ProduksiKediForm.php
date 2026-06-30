<?php

namespace App\Filament\Resources\ProduksiKedis\Schemas;

use App\Models\ProduksiKedi;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProduksiKediForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * ==========================
             * 📅 TANGGAL PRODUKSI
             * ==========================
             */
            DatePicker::make('tanggal')
                ->label('Tanggal Produksi')
                ->default(fn () => now())
                ->displayFormat('d F Y')
                ->required()
                ->reactive()
                ->live() // Pastikan live agar mentrigger perubahan dropdown mesin
                ->rule(function (callable $get, ?ProduksiKedi $record) {
                    // Rule unik hanya berlaku untuk status 'masuk'
                    if ($get('status') !== 'masuk') {
                        return null;
                    }

                    return Rule::unique('produksi_kedi', 'tanggal')
                        ->where(fn ($query) =>
                            $query->where('id_mesin', $get('id_mesin'))
                                ->where('status', 'masuk')
                        )
                        ->ignore($record?->id);
                })
                ->validationMessages([
                    'unique' => 'Produksi Masuk dengan mesin ini pada tanggal tersebut sudah ada.',
                ]),

            DatePicker::make('tanggal_bongkar')
                ->label('Tanggal Bongkar')
                ->displayFormat('d F Y')
                ->visible(fn(?ProduksiKedi $record) => $record && in_array($record->status, ['bongkar', 'selesai']))
                ->required(fn($get) => $get('status') === 'bongkar')
                ->default(now()),

            /**
             * ==========================
             * ⚙️ MESIN KEDI
             * ==========================
             */
            Select::make('id_mesin')
                ->label('Mesin Kedi')
                ->options(function ($get, ?ProduksiKedi $record) {
                    $tanggal = $get('tanggal');
                    $status = $get('status');

                    if (!$tanggal) {
                        return \App\Models\Mesin::whereHas('kategoriMesin', fn ($q) => 
                            $q->where('nama_kategori_mesin', 'DRYER')
                        )->pluck('nama_mesin', 'id');
                    }

                    // Ambil daftar mesin yang sudah digunakan pada tanggal & status tersebut
                    $usedMachineIds = ProduksiKedi::whereDate('tanggal', $tanggal)
                        ->where('status', $status)
                        ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                        ->pluck('id_mesin')
                        ->filter()
                        ->toArray();

                    return \App\Models\Mesin::whereHas('kategoriMesin', fn ($q) => 
                        $q->where('nama_kategori_mesin', 'DRYER')
                    )
                    ->get()
                    ->mapWithKeys(function ($mesin) use ($usedMachineIds) {
                        $isUsed = in_array($mesin->id, $usedMachineIds);
                        $label = $isUsed ? "{$mesin->nama_mesin} (Sudah digunakan)" : $mesin->nama_mesin;
                        return [$mesin->id => $label];
                    });
                })
                ->disableOptionWhen(function ($value, $get, ?ProduksiKedi $record) {
                    $tanggal = $get('tanggal');
                    $status = $get('status');
                    if (!$tanggal) return false;

                    return ProduksiKedi::whereDate('tanggal', $tanggal)
                        ->where('status', $status)
                        ->where('id_mesin', $value)
                        ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                        ->exists();
                })
                ->required()
                ->searchable()
                ->preload()
                ->live(),

            DatePicker::make('tanggal_actual_bongkar')
                ->label('Tanggal Aktual Bongkar')
                ->displayFormat('d F Y')
                ->default(fn () => now())
                ->visible(fn (?ProduksiKedi $record) => $record && $record->status !== 'masuk')
                ->required(fn (?ProduksiKedi $record) => $record && $record->status !== 'masuk'),

            DatePicker::make('rencana_bongkar')
                ->label('Rencana Bongkar')
                ->displayFormat('d F Y')
                ->default(fn () => now()->addDays(2)) // Default to 2 days after
                ->required(),

            /**
             * ==========================
             * ⚙️ STATUS PRODUKSI
             * ==========================
             */
            Select::make('status')
                ->label('Status Produksi')
                ->options([
                    'masuk'   => 'Masuk',
                    'bongkar' => 'Bongkar',
                ])
                ->default('masuk')
                ->required()
                ->dehydrated() // Pastikan nilai dikirim ke server meskipun hidden
                ->hidden(), // Disembunyikan karena manual create selalu 'masuk'
        ]);
    }
}
