<?php

namespace App\Livewire;

use App\Models\DetailLainLain;
use App\Models\LainLain;
use App\Models\Pegawai;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AbsenWajibModal extends Component
{
    public ?int $id_pegawai = null;
    public string $masuk = '';
    public string $pulang = '';
    public ?string $ijin = null;
    public ?string $ket = null;
    public ?string $hasil = null;

    public function mount(): void
    {
        // Default jam kerja standar
        $this->masuk = '08:00';
        $this->pulang = '16:00';

        // Default pegawai = pegawai milik user yang sedang login (kalau ada), tetap bisa diganti
        $this->id_pegawai = auth()->user()?->id_pegawai;
    }

    /**
     * Cek apakah user yang sedang login WAJIB mengisi absen hari ini.
     * Aturan:
     * - Kalau user tidak terhubung ke pegawai manapun (id_pegawai null di tabel users),
     *   modal tidak wajib muncul (misal akun admin/HR murni).
     * - Kalau pegawai terkait user ini SUDAH punya record LainLain untuk tanggal hari ini,
     *   modal tidak perlu muncul lagi.
     */
    #[Computed]
    public function wajibAbsen(): bool
    {
        $user = auth()->user();

        if (!$user || !$user->id_pegawai) {
            return false;
        }

        $sudahAbsen = LainLain::where('id_pegawai', $user->id_pegawai)
            ->whereHas('detailLainLain', function ($query) {
                $query->whereDate('tanggal', today());
            })
            ->exists();

        return !$sudahAbsen;
    }

    #[Computed]
    public function pegawais()
    {
        return Pegawai::orderBy('nama_pegawai')->get();
    }

    /**
     * Opsi waktu format 24-jam ("06.00", "16.00", dst) per jam,
     * supaya konsisten dengan tampilan form Lain Lain yang sudah ada (bukan AM/PM).
     */
    #[Computed]
    public function timeOptions(): array
    {
        $options = [];
        for ($h = 0; $h < 24; $h++) {
            $value = sprintf('%02d:00', $h);
            $options[$value] = sprintf('%02d.00', $h);
        }
        return $options;
    }

    public function submit(): void
    {
        $data = $this->validate([
            'id_pegawai' => ['required', 'exists:pegawais,id'],
            'masuk'      => ['required'],
            'pulang'     => ['nullable'],
            'ijin'       => ['nullable', 'string'],
            'ket'        => ['nullable', 'string'],
            'hasil'      => ['nullable', 'string'],
        ]);

        // Cari atau buat DetailLainLain untuk hari ini
        $detail = DetailLainLain::firstOrCreate([
            'tanggal' => today()->toDateString(),
        ]);

        LainLain::create([
            'id_detail_lain_lain' => $detail->id,
            'id_pegawai'          => $data['id_pegawai'],
            'masuk'               => $data['masuk'],
            'pulang'              => $data['pulang'] ?: null,
            'ijin'                => $data['ijin'],
            'ket'                 => $data['ket'],
            'hasil'               => $data['hasil'],
            'created_by'          => auth()->id(),
        ]);

        Notification::make()
            ->title('Absen berhasil dicatat')
            ->success()
            ->send();

        // Refresh computed property supaya modal langsung tertutup
        unset($this->wajibAbsen);

        $this->reset(['id_pegawai', 'pulang', 'ijin', 'ket', 'hasil']);
        $this->masuk = '08:00';
        $this->pulang = '16:00';
        $this->id_pegawai = auth()->user()?->id_pegawai;
    }

    public function render()
    {
        return view('livewire.absen-wajib-modal');
    }

    public function logout()
    {
        auth()->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->to(filament()->getLoginUrl());
    }
}