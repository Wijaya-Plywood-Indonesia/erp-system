<?php

namespace App\Filament\Resources\KayuMasuks\RelationManagers;

use App\Filament\Resources\DetailKayuMasuks\Schemas\DetailKayuMasukForm;
use App\Filament\Resources\DetailKayuMasuks\Tables\DetailKayuMasuksTable;
use App\Models\DetailTurunKayu;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailMasukanKayuRelationManager extends RelationManager
{
    protected static string $relationship = 'DetailMasukanKayu';
    protected static ?string $title = 'Detail Kayu Masuk';

    protected $listeners = ['refreshDatatable' => '$refresh'];

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        $detailTurun = DetailTurunKayu::where('id_kayu_masuk', $ownerRecord->id)->first();
        if (!$detailTurun) return false;
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return DetailKayuMasukForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        // Semua logika tombol dan tabel sekarang ditarik dari class configure pusat
        return DetailKayuMasuksTable::configure($table, $this);
    }
}
