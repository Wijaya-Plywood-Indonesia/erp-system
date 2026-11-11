<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BkbmOutbox extends Model
{
    protected $table = 'bkbm_outbox';
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'kirim_berikutnya_at' => 'datetime', 'terkirim_at' => 'datetime'];

    public static function untuk(string $siklus, string $jenis): ?self
    {
        return static::where('siklus_uuid', $siklus)->where('jenis', $jenis)->latest('id')->first();
    }

    /** Satu kalimat pendek untuk badge. */
    public function teksStatus(): string
    {
        return match ($this->status) {
            'terkirim' => 'Terkirim ke pabrik lain pada ' . $this->terkirim_at?->format('d M Y H:i'),
            'gagal' => 'Gagal terkirim (sudah dicoba ' . $this->percobaan . ' kali)',
            default => $this->percobaan > 0
                ? 'Belum terkirim ke pabrik lain (sudah dicoba ' . $this->percobaan . ' kali)'
                : 'Sedang dikirim ke pabrik lain',
        };
    }

    /** Penjelasan penyebab dan langkah berikutnya. Kosong bila sudah terkirim. */
    public function teksDetail(): ?string
    {
        if ($this->status === 'terkirim') {
            return null;
        }
        $baris = [];
        if ($this->error) {
            $baris[] = $this->error;
        }
        $baris[] = $this->status === 'gagal'
            ? 'Pengiriman otomatis sudah berhenti. Setelah masalahnya diperbaiki, tekan tombol "Kirim ulang ke pabrik lain" di atas. Data nota tetap aman.'
            : 'Data nota aman. Sistem akan mencoba lagi otomatis'
                . ($this->kirim_berikutnya_at ? ' sekitar ' . $this->kirim_berikutnya_at->diffForHumans() : '')
                . ', atau tekan tombol "Kirim ulang ke pabrik lain" di atas.';
        return implode("\n", $baris);
    }
}
