<?php

namespace App\Filament\Resources\TurunKayus\RelationManagers;

use App\Filament\Resources\DetailTurunKayus\Schemas\DetailTurunKayuForm;
use App\Filament\Resources\DetailTurunKayus\Tables\DetailTurunKayusTable;
use App\Models\DetailTurunKayu;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailTurunKayuRelationManager extends RelationManager
{

    public function isReadOnly(): bool
    {
        return false;
    }
    protected static string $relationship = 'detailTurunKayu';

    public function form(Schema $schema): Schema
    {
        return DetailTurunKayuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailTurunKayusTable::configure($table);
    }

    /**
     * Membuat 1 DetailTurunKayu, lalu banyak PegawaiTurunKayu
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ambil lalu hapus pegawai dari data utama
        $pegawaiIds = $data['id_pegawai'];
        unset($data['id_pegawai']);

        // Buat detail turunkayu
        $detail = DetailTurunKayu::create([
            'id_turun_kayu' => $this->ownerRecord->id,
            'id_kayu_masuk' => $data['id_kayu_masuk'],
            'status' => $data['status'],
            'nama_supir' => $data['nama_supir'],
            'jumlah_kayu' => $data['jumlah_kayu'],
            'foto' => $data['foto'],
        ]);

        // Buat banyak pegawai pivot
        foreach ($pegawaiIds as $pegawaiId) {
            $detail->pegawaiTurunKayu()->create([
                'id_pegawai' => $pegawaiId,
                'role' => 'pegawai',
                'jam_masuk' => now(),
                'jam_pulang' => now(),
            ]);
        }

        // Kosong, agar Filament tidak insert 2x
        return [];
    }
}
