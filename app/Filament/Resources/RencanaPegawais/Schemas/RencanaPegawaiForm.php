<?php

namespace App\Filament\Resources\RencanaPegawais\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema; // PAKAI Form, bukan Schema!
use App\Models\Pegawai;
use App\Models\RencanaPegawai;
use Carbon\CarbonPeriod;

class RencanaPegawaiForm
{

    public static function timeOptions(): array
    {
        return collect(
            CarbonPeriod::create('00:00', '1 hour', '23:00')->toArray()
        )->mapWithKeys(fn($time) => [
            $time->format('H:i') => $time->format('H.i'),
        ])->toArray();
    }

    public static function configure(Schema $form, $record = null): Schema
    {
        // Ambil ID produksi dari owner (RelationManager) atau dari record
        $produksiId = $record?->id_produksi_repair
            ?? request()->query('produksi_id')
            ?? $form->getLivewire()->ownerRecord?->id
            ?? request()->route('record');

        return $form->schema([

            Select::make('id_pegawai')
                ->label('Pegawai')
                ->options(function ($livewire) {
                    $produksiId = $livewire->ownerRecord?->id;

                    // Ambil record yang sedang diedit dari Livewire
                    $editingRecord = method_exists($livewire, 'getMountedTableActionRecord')
                        ? $livewire->getMountedTableActionRecord()
                        : null;

                    $usedPegawaiIds = RencanaPegawai::where('id_produksi_repair', $produksiId)
                        ->when($editingRecord, fn($q) => $q->where('id', '!=', $editingRecord->id))
                        ->pluck('id_pegawai')
                        ->toArray();

                    return Pegawai::whereNotIn('id', $usedPegawaiIds)
                        ->get()
                        ->mapWithKeys(fn($p) => [$p->id => "{$p->kode_pegawai} - {$p->nama_pegawai}"]);
                })
                ->searchable()
                ->required()
                ->columnSpanFull()
                ->rules([
                    fn($livewire) => function ($attribute, $value, $fail) use ($livewire) {
                        $editingRecord = method_exists($livewire, 'getMountedTableActionRecord')
                            ? $livewire->getMountedTableActionRecord()
                            : null;

                        $exists = RencanaPegawai::where('id_produksi_repair', $livewire->ownerRecord?->id)
                            ->where('id_pegawai', $value)
                            ->when($editingRecord, fn($q) => $q->where('id', '!=', $editingRecord->id))
                            ->exists();

                        if ($exists) $fail('Pegawai sudah ditugaskan.');
                    }
                ]),

            Select::make('jam_masuk')
                ->label('Jam Masuk')
                ->options(self::timeOptions())
                ->default('06:00')
                ->required()
                ->searchable()
                ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),

            // --- JAM PULANG ---
            Select::make('jam_pulang')
                ->label('Jam Pulang')
                ->options(self::timeOptions())
                ->default('16:00')
                ->required()
                ->searchable()
                ->dehydrateStateUsing(fn($state) => $state ? $state . ':00' : null)
                ->formatStateUsing(fn($state) => $state ? substr($state, 0, 5) : null),
        ]);
    }
}
