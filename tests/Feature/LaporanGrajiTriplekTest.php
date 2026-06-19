<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Filament\Pages\LaporanGrajiTriplek;
use Livewire\Livewire;
use Carbon\Carbon;

class LaporanGrajiTriplekTest extends TestCase
{
    public function test_default_date_is_today()
    {
        $component = Livewire::test(LaporanGrajiTriplek::class);
        $tanggal = $component->get('data.tanggal');
        $this->assertStringStartsWith(now()->format('Y-m-d'), $tanggal);
    }

    public function test_date_update_with_slash_format()
    {
        Livewire::test(LaporanGrajiTriplek::class)
            ->call('onTanggalUpdated', '18/03/2026')
            ->assertSet('data.tanggal', '2026-03-18');
    }

    public function test_date_update_with_dash_format()
    {
        Livewire::test(LaporanGrajiTriplek::class)
            ->call('onTanggalUpdated', '2026-03-18')
            ->assertSet('data.tanggal', '2026-03-18');
    }

    public function test_date_update_with_invalid_format_defaults_to_today()
    {
        Livewire::test(LaporanGrajiTriplek::class)
            ->call('onTanggalUpdated', 'invalid-date-string')
            ->assertSet('data.tanggal', now()->format('Y-m-d'));
    }
}
