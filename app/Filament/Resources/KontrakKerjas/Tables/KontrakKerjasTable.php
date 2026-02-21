<?php

namespace App\Filament\Resources\KontrakKerjas\Tables;

use App\Models\KontrakKerja;
use App\Services\NomorKontrakService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use League\Uri\Components\Component;

class KontrakKerjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_kontrak')
                    ->label('No Dokumen Kontrak')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kode')
                    ->label('Kode Pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kontrak_mulai')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),

                TextColumn::make('kontrak_selesai')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),

                TextColumn::make('durasi_kontrak')
                    ->label('Durasi (hari)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status_dokumen')
                    ->label('Dokumen')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'draft' => 'gray',
                        'dicetak' => 'warning',
                        'ditandatangani' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status_kontrak')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'soon' => 'warning',
                        'expired' => 'danger',
                        'extended' => 'extended',

                        default => 'gray',
                    }),

                TextColumn::make('dibuat_oleh')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('divalidasi_oleh')
                    ->label('Divalidasi Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated(false)
            ->filters([
                //
                SelectFilter::make('status_kontrak')
                    ->label('Status Pegawai')
                    //['active', 'soon', 'expired']
                    ->options([
                        'active' => 'Aktif',
                        'soon' => 'Segera Habis',
                        'expired' => 'Habis',
                        'extended' => 'Perpanjangan'
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

                Action::make('updateStatusDokumen')
                    ->label('Update Bukti & Status')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->mountUsing(fn(Schema $form, $record) => $form->fill([
                        'status_dokumen' => $record->status_dokumen,
                        'bukti_ttd' => $record->bukti_ttd,
                    ]))
                    ->form([
                        Section::make('Status Dokumen')
                            ->schema([
                                Select::make('status_dokumen')
                                    ->label('Status Dokumen')
                                    ->options([
                                        'draft' => 'Draft',
                                        'dicetak' => 'Dicetak',
                                        'ditandatangani' => 'Ditandatangani',
                                    ])
                                    ->required()
                                    ->columns(1),

                                \App\Forms\Components\CompressedFileUpload::make('bukti_ttd')
                                    ->label('Bukti Kontrak')
                                    ->disk('public')
                                    ->directory('bukti_kontrak')
                                    ->imageEditor()
                                    ->helperText('Upload foto kontrak yang sudah ditandatangani')
                                    ->fileName(function ($record) {
                                        $noKontrak = $record->kode ?: 'NoKontrak';
                                        $nama = $record->nama ?: 'TanpaNama';
                                        return "{$noKontrak}_{$nama}_bukti_kontrak";
                                    })

                                    ->columns(1),
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status_dokumen' => $data['status_dokumen'],
                            'bukti_ttd' => $data['bukti_ttd'],
                            // Otomatis catat siapa yang memvalidasi saat upload bukti
                            'divalidasi_oleh' => auth()->user()->name,
                        ]);

                        Notification::make()
                            ->title('Status dan Bukti Kontrak berhasil diperbarui')
                            ->success()
                            ->send();
                    })
                    ->modalWidth('2xl')
                    ->modalHeading('Pembaharuan Status Dokumen'),

                Action::make('print')
                    ->label('Cetak Kontrak')
                    ->icon('heroicon-o-printer')
                    ->url(fn($record): string => route('kontrak.print', $record))
                    ->openUrlInNewTab(),

                Action::make('perpanjang')
                    ->label('Perpanjang')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('durasi')
                            ->label('Durasi Perpanjangan')
                            ->options([
                                30 => '30 Hari',
                                60 => '60 Hari',
                                90 => '90 Hari',
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {

                        $durasi = intval($data['durasi']);

                        // 👉 mulai dari besok setelah kontrak selesai
                        $mulaiBaru = Carbon::parse($record->kontrak_selesai)->addDay();

                        // 👉 geser kalau jatuh di hari libur
                        $mulaiBaru = nextWorkingDay($mulaiBaru);

                        // 👉 hitung selesai
                        $selesaiBaru = $mulaiBaru->copy()->addDays($durasi);

                        // 👉 pastikan selesai juga hari kerja
                        $selesaiBaru = previousWorkingDay($selesaiBaru);

                        // 👉 tanggal kontrak (hari ini tapi harus hari kerja)
                        $tanggalKontrak = nextWorkingDay(now());

                        KontrakKerja::create([
                            'kode' => $record->kode,
                            'nama' => $record->nama,
                            'jenis_kelamin' => $record->jenis_kelamin,
                            'tanggal_masuk' => $record->tanggal_masuk,
                            'karyawan_di' => $record->karyawan_di,
                            'alamat_perusahaan' => $record->alamat_perusahaan,
                            'jabatan' => $record->jabatan,
                            'nik' => $record->nik,
                            'tempat_tanggal_lahir' => $record->tempat_tanggal_lahir,
                            'alamat' => $record->alamat,
                            'no_telepon' => $record->no_telepon,

                            'kontrak_mulai' => $mulaiBaru,
                            'kontrak_selesai' => $selesaiBaru,
                            'durasi_kontrak' => $durasi,

                            'tanggal_kontrak' => $tanggalKontrak,
                            'no_kontrak' => NomorKontrakService::generate(),

                            'status_dokumen' => 'draft',
                            'status_kontrak' => 'extended',

                            'dibuat_oleh' => auth()->id(),
                            'divalidasi_oleh' => null,
                        ]);
                    })
                    ->requiresConfirmation()
                    ->color('warning'),
            ])
            ->defaultSort('id', 'desc')
            ->toolbarActions([
                Action::make('update_kontrak')
                    ->label('Update Status & Durasi Kontrak')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function () {

                        DB::statement("
            UPDATE kontrak_kerja
            SET 
                durasi_kontrak = 
                    CASE
                        WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                            THEN 0
                        ELSE DATEDIFF(kontrak_selesai, kontrak_mulai)
                    END,

                status_kontrak =
                    CASE
                        WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                            THEN 'expired'
                        WHEN CURDATE() > kontrak_selesai
                            THEN 'expired'
                        WHEN DATEDIFF(kontrak_selesai, CURDATE()) <= 30
                            THEN 'soon'
                        ELSE 'active'
                    END
            WHERE status_kontrak != 'extended'
        ");

                        Notification::make()
                            ->title('Status & durasi kontrak berhasil diperbarui (extended diabaikan)')
                            ->success()
                            ->send();
                    }),
                //         Action::make('update_status_kontrak')
                //             ->label('Update Status Kontrak')
                //             ->icon('heroicon-o-bolt')
                //             ->color('warning')
                //             ->requiresConfirmation()
                //             ->action(function () {

                //                 DB::statement("
                //     UPDATE kontrak_kerja
                //     SET 
                //         durasi_kontrak = 
                //             CASE
                //                 WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                //                     THEN 0
                //                 ELSE DATEDIFF(kontrak_selesai, kontrak_mulai)
                //             END,

                //         status_kontrak =
                //             CASE
                //                 WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL
                //                     THEN 'expired'
                //                 WHEN CURDATE() > kontrak_selesai
                //                     THEN 'expired'
                //                 WHEN DATEDIFF(kontrak_selesai, CURDATE()) <= 30
                //                     THEN 'soon'
                //                 ELSE 'active'
                //             END
                // ");

                //                 Notification::make()
                //                     ->title('Status kontrak berhasil diperbarui')
                //                     ->success()
                //                     ->send();
                //             }),
                //         Action::make('update_semua_durasi')
                //             ->label('Update Semua Durasi')
                //             ->icon('heroicon-o-arrow-path')
                //             ->color('success')
                //             ->requiresConfirmation()
                //             ->action(function () {

                //                 DB::statement("
                //         UPDATE kontrak_kerja
                //         SET durasi_kontrak = DATEDIFF(kontrak_selesai, kontrak_mulai)
                //         WHERE kontrak_mulai IS NOT NULL
                //         AND kontrak_selesai IS NOT NULL
                //     ");

                //                 Notification::make()
                //                     ->title('Durasi semua kontrak berhasil diperbarui')
                //                     ->success()
                //                     ->send();
                //             }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('edit_manual_dev')
                        ->label('Edit Manual (Dev)')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('gray')
                        ->form([

                            Select::make('status_kontrak')
                                ->label('Status Kontrak')
                                ->options([
                                    'active' => 'Active',
                                    'soon' => 'Soon',
                                    'expired' => 'Expired',
                                    'extended' => 'Extended',
                                ]),

                        ])
                        ->action(function ($records, array $data) {

                            foreach ($records as $record) {

                                $updateData = [];

                                if (!empty($data['status_kontrak'])) {
                                    $updateData['status_kontrak'] = $data['status_kontrak'];
                                }

                                // 🔥 INI YANG KURANG
                                if (!empty($updateData)) {
                                    $record->update($updateData);
                                }
                            }

                            Notification::make()
                                ->title('Data terpilih berhasil di edit manual')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    BulkAction::make('bulk_print')
                        ->label('Cetak Kontrak')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->action(function ($records) {

                            $ids = $records->pluck('id')->toArray();

                            return redirect()->route('kontrak.bulk.print', [
                                'ids' => implode(',', $ids)
                            ]);
                        })
                        ->requiresConfirmation()
                        ->openUrlInNewTab(),

                    BulkAction::make('bulkPerpanjang')
                        ->label('Perpanjang Terpilih')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([
                            Select::make('durasi')
                                ->label('Durasi Perpanjangan')
                                ->options([
                                    30 => '30 Hari',
                                    60 => '60 Hari',
                                    90 => '90 Hari',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {

                            $durasi = intval($data['durasi']);

                            foreach ($records as $record) {

                                // mulai baru
                                $mulaiBaru = Carbon::parse($record->kontrak_selesai)->addDay();
                                $mulaiBaru = nextWorkingDay($mulaiBaru);

                                // selesai baru
                                $selesaiBaru = $mulaiBaru->copy()->addDays($durasi);
                                $selesaiBaru = previousWorkingDay($selesaiBaru);

                                // tanggal kontrak
                                $tanggalKontrak = nextWorkingDay(now());

                                KontrakKerja::create([
                                    'kode' => $record->kode,
                                    'nama' => $record->nama,
                                    'jenis_kelamin' => $record->jenis_kelamin,
                                    'tanggal_masuk' => $record->tanggal_masuk,
                                    'karyawan_di' => $record->karyawan_di,
                                    'alamat_perusahaan' => $record->alamat_perusahaan,
                                    'jabatan' => $record->jabatan,
                                    'nik' => $record->nik,
                                    'tempat_tanggal_lahir' => $record->tempat_tanggal_lahir,
                                    'alamat' => $record->alamat,
                                    'no_telepon' => $record->no_telepon,

                                    'kontrak_mulai' => $mulaiBaru,
                                    'kontrak_selesai' => $selesaiBaru,
                                    'durasi_kontrak' => $durasi,

                                    'tanggal_kontrak' => $tanggalKontrak,
                                    'no_kontrak' => NomorKontrakService::generate(),

                                    'status_dokumen' => 'draft',
                                    'status_kontrak' => 'extended',

                                    'dibuat_oleh' => auth()->id(),
                                    'divalidasi_oleh' => null,
                                ]);
                            }
                        })
                        ->requiresConfirmation(),
                ]),

            ]);
    }
}
