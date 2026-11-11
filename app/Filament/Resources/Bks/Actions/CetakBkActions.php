<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Models\Bk;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * Tombol cetak BK. Sama dengan menu lama: Cetak Nota, Cetak Surat Jalan, Nota Kantor, Nota Sales.
 * Nota Kantor/Sales (berisi harga) hanya untuk role edmeros/super_admin/Super Admin.
 */
class CetakBkActions
{
    private static function admin(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']);
    }

    public static function make(): ActionGroup
    {
        $sudahValidasi = fn (Bk $record) => $record->divalidasi_oleh !== null || self::admin();

        return ActionGroup::make([
            Action::make('cetak_nota')->label('Cetak Nota')->icon('heroicon-o-printer')->color('info')
                ->url(fn (Bk $record) => route('bkbm.nota-bk.print', $record))->openUrlInNewTab()
                ->visible($sudahValidasi),

            Action::make('cetak_surat_jalan')->label('Cetak Surat Jalan')->icon('heroicon-o-truck')->color('warning')
                ->url(fn (Bk $record) => route('bkbm.surat-jalan.bk', $record))->openUrlInNewTab()
                ->visible($sudahValidasi),

            Action::make('nota_kantor')->label('Nota Kantor')->icon('heroicon-o-building-office')->color('success')
                ->url(fn (Bk $record) => route('bkbm.nota-bk.preview', ['record' => $record, 'jenis' => 'kantor']))->openUrlInNewTab()
                ->visible(fn () => self::admin()),

            Action::make('nota_sales')->label('Nota Sales')->icon('heroicon-o-shopping-bag')->color('primary')
                ->url(fn (Bk $record) => route('bkbm.nota-bk.preview', ['record' => $record, 'jenis' => 'sales']))->openUrlInNewTab()
                ->visible(fn () => self::admin()),
        ])->label('Cetak')->icon('heroicon-o-printer')->button()->color('gray');
    }
}
