<?php

namespace App\Filament\Resources\ReturPenjualans\Pages;

use App\Filament\Resources\ReturPenjualans\ReturPenjualanResource;
use Filament\Resources\Pages\EditRecord;

class EditReturPenjualan extends EditRecord
{
    protected static string $resource = ReturPenjualanResource::class;

    public function mount(int | string $record): void
    {
        // Redirect ke form interaktif khusus retur dengan parameter edit
        redirect()->to(FormReturPenjualan::getUrl(['edit' => $record]));
    }
}

