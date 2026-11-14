<?php

namespace App\Support;

use App\Services\ValidasiOtomatisProduksiService;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Model;

class KolomValidasi
{
    public static function make(): BadgeColumn
    {
        return BadgeColumn::make('kolom_validasi')
            ->label('Validasi')
            ->getStateUsing(fn (Model $record): ?string => self::status($record))
            ->colors([
                'success' => 'divalidasi',
                'info'    => ['disetujui', 'otomatis'],
                'warning' => ['ditangguhkan', 'sebagian'],
                'danger'  => 'ditolak',
            ])
            ->icons([
                'heroicon-o-check-circle'       => ['divalidasi', 'disetujui'],
                'heroicon-o-clock'              => 'otomatis',
                'heroicon-o-x-circle'           => 'ditolak',
                'heroicon-o-exclamation-circle' => ['ditangguhkan', 'sebagian'],
            ]);
    }

    /** Status validasi terakhir produksi (nilai asli dari sistem), null bila belum ada. */
    protected static function status(Model $record): ?string
    {
        foreach (ValidasiOtomatisProduksiService::daftar() as $c) {
            if (! is_a($record, $c['produksi'])) {
                continue;
            }

            $query = $c['validasi']::query()->where($c['fk'], $record->getKey());

            // Rotary: lengkap ('disetujui') bila SEMUA peran sudah menyetujui.
            if (! empty($c['peran'])) {
                $roles = $query->where('status', 'disetujui')->pluck('role')
                    ->map(fn ($r) => strtolower((string) $r))->all();

                $terpenuhi = collect($c['peran'])->filter(
                    fn ($p) => collect($roles)->contains(fn ($r) => str_contains($r, $p))
                )->count();

                if ($terpenuhi >= count($c['peran'])) {
                    return 'disetujui';
                }

                return $terpenuhi > 0 ? 'divalidasi' : null;
            }

            $status = $query->orderByDesc('id')->first()?->status;

            return $status ? strtolower($status) : null;
        }

        return null;
    }
}