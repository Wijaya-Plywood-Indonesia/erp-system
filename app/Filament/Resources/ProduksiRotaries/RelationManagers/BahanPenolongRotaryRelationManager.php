<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Filament\Resources\BahanPenolongRotaries\Schemas\BahanPenolongRotaryForm;
use App\Filament\Resources\BahanPenolongRotaries\Tables\BahanPenolongRotariesTable;
use App\Models\ValidasiHasilRotary;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BahanPenolongRotaryRelationManager extends RelationManager
{
    protected static string $relationship = 'BahanPenolongRotary';
    protected static ?string $title = 'Bahan Digunakan';
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
        return BahanPenolongRotaryForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanPenolongRotariesTable::configure($table);
    }
}
