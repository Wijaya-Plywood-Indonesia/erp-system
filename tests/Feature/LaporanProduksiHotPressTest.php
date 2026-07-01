<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Filament\Pages\LaporanProduksiHotPress;
use Livewire\Livewire;
use Carbon\Carbon;

class LaporanProduksiHotPressTest extends TestCase
{
    public function test_default_date_is_today()
    {
        Livewire::test(LaporanProduksiHotPress::class)
            ->assertSet('tanggal', now()->format('Y-m-d'));
    }

    public function test_date_update_in_hot_press()
    {
        Livewire::test(LaporanProduksiHotPress::class)
            ->set('tanggal', '2026-05-16')
            ->assertSet('tanggal', '2026-05-16');
    }
}
