<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Filament\Resources\PenggunaanLahanRotaries\Schemas\PenggunaanLahanRotaryForm;
use App\Filament\Resources\PenggunaanLahanRotaries\Tables\PenggunaanLahanRotariesTable;
use App\Models\ValidasiHasilRotary;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailLahanRotaryRelationManager extends RelationManager
{
    protected static ?string $title = 'Lahan';
    protected static string $relationship = 'detailLahanRotary';
    public function isReadOnly(): bool
{
    $user = \Filament\Facades\Filament::auth()->user();

    // Hanya role ini yang terdampak lock
    $rolesAffectedByLock = [
        'pengawas_rotary_1',
        'pengawas_rotary_2',
        'kepala_produksi_wijaya',
    ];

    // Jika user bukan salah satu dari role di atas, tidak terkunci
    if (!$user?->hasAnyRole($rolesAffectedByLock)) {
        return false;
    }

    $ownerRecord = $this->getOwnerRecord();

    $validated = \App\Models\ValidasiHasilRotary::where('id_produksi', $ownerRecord->id)
        ->where('status', 'disetujui')
        ->pluck('role')
        ->toArray();

    $kepalaSudah = collect($validated)->contains(
        fn($role) => str_contains(strtolower($role), 'kepala_produksi')
    );

    $pengawasSudah = collect($validated)->contains(
        fn($role) => str_contains(strtolower($role), 'pengawas_rotary')
    );

    return $kepalaSudah && $pengawasSudah;
}
    public function form(Schema $schema): Schema
    {
        return PenggunaanLahanRotaryForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PenggunaanLahanRotariesTable::configure($table);
    }
}
