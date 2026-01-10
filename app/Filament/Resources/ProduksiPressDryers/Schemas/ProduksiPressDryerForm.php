<?php

namespace App\Filament\Resources\ProduksiPressDryers\Schemas;

use App\Models\ProduksiPressDryer;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class ProduksiPressDryerForm
{
    public static function configure(Schema $schema, $record = null): Schema
    {
        return $schema->components([
            DatePicker::make('tanggal_produksi')
                ->label('Tanggal Produksi')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->format('Y-m-d')
                ->default(now()->addDay())
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, $get, $set) =>
                    self::checkDuplication(
                        $state,
                        $get('shift'),
                        $set,
                        $record
                    )
                )
                ->rules([
                    fn () => fn ($attribute, $value, $fail) =>
                        self::validateSubmit($value, request()->shift, $fail, $record),
                ]),

            Select::make('shift')
                ->label('Shift')
                ->options([
                    'PAGI' => 'Pagi',
                    'MALAM' => 'Malam',
                ])
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn ($state, $get, $set) =>
                    self::checkDuplication(
                        $get('tanggal_produksi'),
                        $state,
                        $set,
                        $record
                    )
                )
                ->rules([
                    fn () => fn ($attribute, $value, $fail) =>
                        self::validateSubmit(request()->tanggal_produksi, $value, $fail, $record),
                ]),
        ]);
    }

    /**
     * 🔴 NOTIFIKASI REALTIME
     */
    protected static function checkDuplication($tanggal, $shift, $set, $record): void
    {
        if (blank($tanggal) || blank($shift)) return;

        $query = ProduksiPressDryer::whereDate('tanggal_produksi', $tanggal)
            ->where('shift', $shift);

        if ($record) {
            $query->where('id', '!=', $record->id);
        }

        if ($query->exists()) {
            Notification::make()
                ->title('Duplikasi Produksi')
                ->body(
                    'Produksi Press Dryer tanggal ' .
                    Carbon::parse($tanggal)->format('d/m/Y') .
                    " shift {$shift} sudah ada."
                )
                ->danger()
                ->persistent()
                ->send();

            // Opsional: reset shift
            $set('shift', null);
        }
    }

    /**
     * 🔒 VALIDASI SAAT SUBMIT
     */
    protected static function validateSubmit($tanggal, $shift, $fail, $record): void
    {
        if (blank($tanggal) || blank($shift)) return;

        $query = ProduksiPressDryer::whereDate('tanggal_produksi', $tanggal)
            ->where('shift', $shift);

        if ($record) {
            $query->where('id', '!=', $record->id);
        }

        if ($query->exists()) {
            $fail('Produksi dengan tanggal dan shift ini sudah terdaftar.');
        }
    }
}
