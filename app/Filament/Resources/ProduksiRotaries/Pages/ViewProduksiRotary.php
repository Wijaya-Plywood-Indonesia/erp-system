<?php

namespace App\Filament\Resources\ProduksiRotaries\Pages;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use App\Filament\Resources\ProduksiRotaries\Widgets\ProduksiSummaryWidget;
use App\Models\ValidasiHasilRotary;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiRotary extends ViewRecord
{
    protected static string $resource = ProduksiRotaryResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiSummaryWidget::class,
        ];
    }

    private function getRolesAllowed(): array
    {
        return [
            'super_admin',
            'Super Admin',
            'pengawas_rotary_1',
            'pengawas_rotary_2',
            'kepala_produksi_wijaya',
            'admin_kayu',
        ];
    }

    private function getUserRoles(): array
    {
        $user = Filament::auth()->user();
        return $user ? $user->getRoleNames()->toArray() : [];
    }

    private function isLocked(): bool
{
    $user = Filament::auth()->user();

    $rolesAffectedByLock = [
        'pengawas_rotary_1',
        'pengawas_rotary_2',
        'kepala_produksi_wijaya',
    ];

    // Jika bukan role yang terdampak, tidak terkunci
    if (!$user?->hasAnyRole($rolesAffectedByLock)) {
        return false;
    }

    $validated = ValidasiHasilRotary::where('id_produksi', $this->record->id)
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

    protected function getHeaderActions(): array
    {
        $userRoles    = $this->getUserRoles();
        $allowedRoles = $this->getRolesAllowed();
        $matchedRole  = collect($userRoles)->first(fn($r) => in_array($r, $allowedRoles));

        $isAllowed = $matchedRole !== null;
        $isLocked  = $this->isLocked();

        $actions = [];

        if ($isAllowed && !$isLocked) {
            $actions[] = Action::make('setujui')
                ->label('Disetujui')
                ->color('warning')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Persetujuan')
                ->modalDescription('Apakah Anda sudah mengecek keseluruhan isi produksi rotary ini dan yakin ingin menyetujuinya?')
                ->modalSubmitActionLabel('Ya, Setujui')
                ->modalCancelActionLabel('Batal')
                ->action(function () use ($matchedRole) {
                    ValidasiHasilRotary::create([
                        'id_produksi' => $this->record->id,
                        'role'        => $matchedRole,
                        'status'      => 'disetujui',
                    ]);

                    Notification::make()
                        ->title('Berhasil disetujui')
                        ->body("Role \"{$matchedRole}\" telah mencatat persetujuan.")
                        ->success()
                        ->send();

                    $this->refreshFormData([]);
                });
        }

        if (!$isLocked) {
            $actions[] = EditAction::make();
        }

        return $actions;
    }
}