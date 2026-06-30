<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\GantiPisauRotaries\Tables\GantiPisauRotariesTable;
use App\Filament\Resources\GantiPisauRotaries\Schemas\GantiPisauRotaryForm;
use App\Models\ValidasiHasilRotary;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;

class DetailGantiPisauRotaryRelationManager extends RelationManager
{
    protected static ?string $title = 'Kendala';
    protected static string $relationship = 'detailGantiPisauRotary';
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
        return GantiPisauRotaryForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return GantiPisauRotariesTable::configure($table) ;
    }
}
