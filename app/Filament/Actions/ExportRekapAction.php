<?php

namespace App\Filament\Actions;

use App\Exports\RekapNotaExport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Maatwebsite\Excel\Facades\Excel;

class ExportRekapAction
{
    /** @param 'bk'|'bm' $jenis */
    public static function make(string $jenis): Action
    {
        $namaFile = $jenis === 'bk' ? 'rekap-barang-keluar' : 'rekap-barang-masuk';

        return Action::make('exportRekap')
            ->label('Export Rekap')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->required()
                    ->default(now())
                    ->native(false),
                TextInput::make('nomor_awal')
                    ->label('Nomor jurnal awal')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Kosongkan untuk mulai dari 1')
                    ->helperText('Nomor jurnal berjalan per bulan di sheet jurnal, dipakai bersama sj/bkl/bm/bml. Isi dengan nomor bk pertama pada tanggal ini.'),
            ])
            ->action(fn (array $data) => Excel::download(
                new RekapNotaExport(
                    $jenis,
                    $data['tanggal'],
                    filled($data['nomor_awal'] ?? null) ? (int) $data['nomor_awal'] : null,
                ),
                $namaFile.'-'.$data['tanggal'].'.xlsx'
            ));
    }
}
