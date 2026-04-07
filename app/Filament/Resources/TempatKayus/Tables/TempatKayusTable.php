<?php

namespace App\Filament\Resources\TempatKayus\Tables;

use App\Models\HppAverageSummarie;
use App\Models\ProduksiRotary;
use App\Models\PenggunaanLahanRotary;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TempatKayusTable
{
    private const ROLE_GRADER   = ['Grader Kayu 1', 'Grader Kayu 2'];
    private const ROLE_PENGAWAS = ['pengawas_rotary_1', 'pengawas_rotary_2'];
    private const ROLE_ADMIN    = ['super_admin', 'Super Admin'];

    public const MESIN_MAP = [
        130 => ['SANJI', 'YUEQUN'],
        260 => ['SPINDLESS', 'MERANTI'],
    ];

    public static function configure(Table $table): Table
    {
        $user = Auth::user();

        $isGrader   = $user->hasAnyRole(self::ROLE_GRADER);
        $isPengawas = $user->hasAnyRole(self::ROLE_PENGAWAS);
        $isAdmin    = $user->hasAnyRole(self::ROLE_ADMIN);

        $bisaSerah  = $isGrader  || $isAdmin;
        $bisaTerima = $isPengawas || $isAdmin;

        return $table
            ->columns([
                TextColumn::make('lahan.kode_lahan')
                    ->label('Kode Lahan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('lahan.nama_lahan')
                    ->label('Nama Lahan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kayuMasuk.seri')
                    ->label('Seri Kayu')
                    ->formatStateUsing(fn($state) => 'Seri - ' . ($state ?? '-'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('jumlah_batang')
                    ->label('Jumlah Batang')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('kubikasi')
                    ->label('Kubikasi (m³)')
                    ->getStateUsing(
                        fn($record) =>
                        number_format(
                            HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                ->sum('stok_kubikasi'),
                            4
                        )
                    ),

                TextColumn::make('panjang_kayu')
                    ->label('Panjang (cm)')
                    ->getStateUsing(
                        fn($record) =>
                        HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->value('panjang') ?? '-'
                    )
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state == 130 => 'info',
                        $state == 260 => 'success',
                        default       => 'gray',
                    }),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->default('-'),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->default('-'),

                TextColumn::make('status_serah')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        $pivot = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_lahan', $record->id_lahan)
                            ->where('tipe', 'lahan_rotary')
                            ->first();

                        if (!$pivot) return 'Belum Diserahkan';

                        return match ($pivot->status) {
                            'Lahan Siap'     => 'Sudah Diserahkan',
                            'Sudah Diterima' => 'Sudah Diterima',
                            default          => $pivot->status,
                        };
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Sudah Diterima'   => 'success',
                        'Sudah Diserahkan' => 'warning',
                        default            => 'gray',
                    }),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->visible($isGrader || $isAdmin),

                // TOMBOL SERAH — Grader: hilang setelah diserahkan | Admin: selalu ada
                Action::make('serah_kayu')
                    ->label('Serah Kayu')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Kayu?')
                    ->modalDescription(
                        fn($record) =>
                        "Kayu dari lahan {$record->lahan?->kode_lahan} " .
                            "({$record->jumlah_batang} batang) akan langsung diserahkan " .
                            "ke produksi rotary berdasarkan panjang kayu."
                    )
                    ->modalSubmitActionLabel('Ya, Serahkan')
                    ->visible(function ($record) use ($bisaSerah, $isAdmin) {
                        if (!$bisaSerah) return false;

                        $sudahDiserahkan = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_lahan', $record->id_lahan)
                            ->where('tipe', 'lahan_rotary')
                            ->exists();

                        // Admin selalu bisa lihat tombol serah
                        if ($isAdmin) return true;

                        // Grader: tombol hilang setelah diserahkan
                        return !$sudahDiserahkan;
                    })
                    ->action(function ($record) {
                        $idLahan = $record->id_lahan;

                        $kubikasi = HppAverageSummarie::where('id_lahan', $idLahan)
                            ->sum('stok_kubikasi');

                        $panjang = (int) HppAverageSummarie::where('id_lahan', $idLahan)
                            ->value('panjang');

                        $namaMesins = self::MESIN_MAP[$panjang] ?? [];

                        $idJenisKayu = $record->kayuMasuk
                            ?->detailMasukanKayu
                            ->first()
                            ?->id_jenis_kayu;

                        if (empty($namaMesins)) {
                            Notification::make()
                                ->title('Panjang kayu tidak dikenali')
                                ->warning()
                                ->send();
                            return;
                        }

                        try {
                            // Jika admin menekan lagi setelah diserahkan, update pivot yang ada
                            $pivotAda = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->where('id_lahan', $idLahan)
                                ->where('tipe', 'lahan_rotary')
                                ->exists();

                            if ($pivotAda) {
                                DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                    ->where('id_lahan', $idLahan)
                                    ->where('tipe', 'lahan_rotary')
                                    ->update([
                                        'diserahkan_oleh' => Auth::user()->name,
                                        'status'          => 'Lahan Siap',
                                        'diterima_oleh'   => '-',
                                        'updated_at'      => now(),
                                    ]);
                            } else {
                                DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                    ->insert([
                                        'id_detail_hasil_palet_rotary' => null,
                                        'id_lahan'                     => $idLahan,
                                        'id_produksi'                  => null,
                                        'jumlah_batang'                => $record->jumlah_batang,
                                        'kubikasi'                     => $kubikasi,
                                        'diserahkan_oleh'              => Auth::user()->name,
                                        'diterima_oleh'                => '-',
                                        'tipe'                         => 'lahan_rotary',
                                        'status'                       => 'Lahan Siap',
                                        'created_at'                   => now(),
                                        'updated_at'                   => now(),
                                    ]);
                            }

                            $record->update([
                                'diserahkan_oleh' => Auth::user()->name,
                                'diterima_oleh'   => null,
                            ]);

                            Log::channel('single')->info('Serah Kayu', [
                                'id_lahan'       => $idLahan,
                                'diserahkan_oleh' => Auth::user()->name,
                            ]);
                        } catch (\Throwable $e) {
                            Log::channel('single')->error('Insert FAILED', [
                                'message' => $e->getMessage(),
                                'code'    => $e->getCode(),
                            ]);

                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            return;
                        }

                        foreach ($namaMesins as $namaMesin) {
                            $produksi = ProduksiRotary::whereHas(
                                'mesin',
                                fn($q) =>
                                $q->where('nama_mesin', $namaMesin)
                            )
                                ->latest()
                                ->first();

                            if (!$produksi) continue;

                            PenggunaanLahanRotary::create([
                                'id_lahan'      => $idLahan,
                                'id_produksi'   => $produksi->id,
                                'id_jenis_kayu' => $idJenisKayu,
                                'jumlah_batang' => $record->jumlah_batang,
                            ]);
                        }

                        Notification::make()
                            ->title('Kayu berhasil diserahkan')
                            ->success()
                            ->send();
                    }),

                // TOMBOL TERIMA — Pengawas: hilang setelah diterima | Admin: selalu ada
                Action::make('terima_kayu')
                    ->label('Terima Kayu')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Kayu dari Grader?')
                    ->modalDescription(
                        fn($record) =>
                        "Kayu dari lahan {$record->lahan?->kode_lahan} " .
                            "({$record->jumlah_batang} batang) akan diterima atas nama " .
                            Auth::user()->name . "."
                    )
                    ->modalSubmitActionLabel('Ya, Terima')
                    ->visible(function ($record) use ($bisaTerima, $isAdmin) {
                        $user = Auth::user();

                        // Debug log
                        Log::channel('single')->info('DEBUG visible terima_kayu', [
                            'user'        => $user->name,
                            'roles'       => $user->getRoleNames(),
                            'bisaTerima'  => $bisaTerima,
                            'isAdmin'     => $isAdmin,
                            'id_lahan'    => $record->id_lahan,
                            'pivot'       => DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->where('id_lahan', $record->id_lahan)
                                ->where('tipe', 'lahan_rotary')
                                ->first(),
                        ]);

                        if (!$bisaTerima) return false;

                        $pivot = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_lahan', $record->id_lahan)
                            ->where('tipe', 'lahan_rotary')
                            ->first();

                        if ($isAdmin) return $pivot !== null;

                        if (!$pivot) return false;

                        return $pivot->status === 'Lahan Siap'
                            && ($pivot->diterima_oleh === '-' || $pivot->diterima_oleh === null);
                    })
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                    ->where('id_lahan', $record->id_lahan)
                                    ->where('tipe', 'lahan_rotary')
                                    ->where('status', 'Lahan Siap')
                                    ->update([
                                        'diterima_oleh' => Auth::user()->name,
                                        'status'        => 'Sudah Diterima',
                                        'updated_at'    => now(),
                                    ]);

                                $record->update([
                                    'diterima_oleh' => Auth::user()->name,
                                ]);

                                Log::channel('single')->info('Kayu Diterima', [
                                    'id_tempat_kayu' => $record->id,
                                    'id_lahan'       => $record->id_lahan,
                                    'diterima_oleh'  => Auth::user()->name,
                                ]);
                            });

                            Notification::make()
                                ->title('Kayu berhasil diterima')
                                ->body('Status lahan diperbarui menjadi Sudah Diterima.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::channel('single')->error('Terima Kayu FAILED', [
                                'message' => $e->getMessage(),
                                'code'    => $e->getCode(),
                                'trace'   => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('Gagal menerima kayu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible($isGrader || $isAdmin),
                ]),
            ]);
    }
}
