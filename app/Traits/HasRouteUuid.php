<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasRouteUuid
{
    use HasUuids;
    /**
     * Memastikan Primary Key internal tetap 'id'
     */
    public function getKeyName(): string
    {
        return 'id';
    }

    /**
     * Memastikan Tipe Data Primary Key tetap String/Int sesuai konfigurasi Eloquent
     */
    public function getKeyType(): string
    {
        return 'int';
    }

    /**
     * Memastikan Primary Key tetap Auto Increment
     */
    public function getIncrementing(): bool
    {
        return true;
    }

    /**
     * Memberitahu HasUuids Laravel untuk mengisi kolom 'uuid' (bukan 'id')
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Menggunakan kolom 'uuid' untuk route URL di Filament/Browser
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
