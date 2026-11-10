<?php

namespace App\Filament\Resources\DetailTurunKayus\Schemas;

use App\Forms\Components\CompressedFileUpload;
use App\Models\DetailTurunKayu;
use App\Models\KayuMasuk;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DetailTurunKayuForm
{
    protected const PRIVILEGED_ROLES = ['super_admin', 'Super Admin', 'admin_kayu'];

    protected static function isPrivileged(): bool
    {
        $user = Auth::user();

        return $user && $user->hasAnyRole(self::PRIVILEGED_ROLES);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('id_kayu_masuk')
                    ->label('Kayu Masuk')
                    ->options(function (?DetailTurunKayu $record) {
                        return KayuMasuk::query()
                            ->with(['penggunaanSupplier', 'penggunaanKendaraanSupplier'])
                            ->where(function ($query) use ($record) {
                                // 1. Belum terelasi (tidak ada detail_turun_kayus dengan status selesai)
                                $query->whereDoesntHave('detailTurunKayus', function ($q) {
                                    $q->where('status', 'selesai');
                                });

                                // 2. Jika sedang edit, sertakan record yang sedang diedit saat ini
                                if ($record && $record->id_kayu_masuk) {
                                    $query->orWhere('id', $record->id_kayu_masuk);
                                }
                            })
                            ->get()
                            ->mapWithKeys(function ($kayu) {
                                $supplier = $kayu->penggunaanSupplier?->nama_supplier ?? '—';
                                $nopol = $kayu->penggunaanKendaraanSupplier?->nopol_kendaraan ?? '—';
                                $jenis = $kayu->penggunaanKendaraanSupplier?->jenis_kendaraan ?? '—';
                                $seri = $kayu->seri ?? '—';

                                return [
                                    $kayu->id => "$supplier | $nopol ($jenis) | Seri: $seri",
                                ];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Pilih kayu masuk'),

                // STATUS
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'menunggu' => 'Menunggu',
                        'selesai' => 'Selesai',
                    ])
                    ->default('selesai')
                    ->native(false)
                    ->required(),

                TextInput::make('nama_supir')
                    ->label('Nama Supir')
                    ->required(),

                TextInput::make('jumlah_kayu')
                    ->label('Jumlah Kayu')
                    ->required()
                    ->numeric(),

                CompressedFileUpload::make('foto')
                    ->label('Foto Bukti')
                    ->imageEditor()
                    ->disk('public')
                    ->directory('turun-kayu/foto-bukti')
                    ->visibility('public')
                    ->required()
                    ->fileName(function (Get $get) {
                        // 1. Ambil Nama Supir
                        $namaSupir = $get('nama_supir') ?: 'Tanpa-Nama';

                        // 2. Ambil Data dari Kayu Masuk (Seri & Tanggal)
                        $kayuMasukId = $get('id_kayu_masuk');

                        $noSeri = 'Tanpa-Seri';
                        $tanggalTurun = now()->format('Y-m-d');

                        if ($kayuMasukId) {
                            $kayu = KayuMasuk::find($kayuMasukId);

                            if ($kayu) {
                                $noSeri = "Seri-{$kayu->seri}";

                                if (! empty($kayu->tgl_kayu_masuk)) {
                                    $tanggalTurun = Carbon::parse($kayu->tgl_kayu_masuk)->format('Y-m-d');
                                }
                            }
                        }

                        // Contoh hasil: "2026-01-19_Supir-Budi_Seri-105"
                        return "{$tanggalTurun}_Supir-{$namaSupir}_{$noSeri}";
                    }),
            ]);
    }
}
