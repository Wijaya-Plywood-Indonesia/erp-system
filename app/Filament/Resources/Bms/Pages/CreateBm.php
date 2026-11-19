<?php

namespace App\Filament\Resources\Bms\Pages;

use App\Filament\Resources\Bms\BmResource;
use App\Models\Bk;
use App\Services\BkBm\BkBmService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBm extends CreateRecord
{
    protected static string $resource = BmResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $kategori = in_array($data['kategori'] ?? 'bm', ['bm', 'bml'], true) ? $data['kategori'] : 'bm';
        $bkl = null;
        if ($kategori === 'bml' && filled($data['id_bk_referensi'] ?? null)) {
            $bkl = Bk::findOrFail($data['id_bk_referensi']);
            if ($bkl->kategori !== 'bkl' || $bkl->status !== 'divalidasi') {
                throw new \RuntimeException('BKL yang dipilih belum divalidasi atau sudah selesai.');
            }
            if ($bkl->bmTerkait()->where('kategori', 'bml')->exists()) {
                throw new \RuntimeException('BML untuk BKL ini sudah dibuat.');
            }
            $data['dari'] = $data['dari'] ?: $bkl->tujuan_nota;
        }
        unset($data['kategori'], $data['id_bk_referensi']);

        return BkBmService::buatBm($data, $kategori, $bkl);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}