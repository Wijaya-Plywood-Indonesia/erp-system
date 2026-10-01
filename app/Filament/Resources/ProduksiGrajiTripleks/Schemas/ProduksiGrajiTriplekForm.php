<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use App\Models\ProduksiGrajitriplek;
use Filament\Forms\Components\Select;

class ProduksiGrajiTriplekForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->default(fn() => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()

                    // ✅ VALIDASI TANGGAL TIDAK BOLEH SAMA UNTUK SHIFT DAN STATUS YANG SAMA
                    ->rules([
                        fn (\Filament\Schemas\Components\Utilities\Get $get, ?\Illuminate\Database\Eloquent\Model $record) => function (string $attribute, $value, $fail) use ($get, $record) {
                            $shift = $get('shift');
                            $status = $get('status');

                            if ($shift && $status) {
                                $query = \App\Models\ProduksiGrajitriplek::whereDate('tanggal_produksi', $value)
                                    ->where('shift', $shift)
                                    ->where('status', $status);

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                if ($query->exists()) {
                                    $fail('Tanggal ini sudah digunakan untuk shift dan status tersebut. Pilih yang lain.');
                                }
                            }
                        },
                    ]),

                Select::make('status')
                    ->label('Status Produksi')
                    ->options([
                        'graji manual'   => 'Graji Manual',
                        'graji otomatis' => 'Graji Otomatis',
                    ])
                    ->required()
                    ->validationMessages([
                        'required' => 'Status produksi wajib dipilih.',
                    ]),

                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'pagi' => 'Pagi',
                        'malam' => 'Malam',
                    ])
                    ->required()
                    ->reactive(), // 🔥 penting


            ]);
    }
}
