<?php

namespace App\Filament\Resources\ModalSandings\Pages;

use App\Filament\Resources\ModalSandings\ModalSandingResource;
use App\Models\HasilSanding;
use App\Models\ModalSanding;
use Filament\Resources\Pages\CreateRecord;
use Notification;

class CreateModalSanding extends CreateRecord
{
    protected static string $resource = ModalSandingResource::class;
}
