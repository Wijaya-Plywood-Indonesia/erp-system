<?php

namespace App\Filament\Resources\Absensis\Pages;

use App\Filament\Resources\Absensis\AbsensiResource;
use App\Models\DetailAbsensi;
use App\Models\Pegawai;
use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiHp;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiSanding;
use App\Services\AbsensiPairingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CreateAbsensi extends CreateRecord
{
    protected static string $resource = AbsensiResource::class;

    protected function afterCreate(): void
    {
        $record     = $this->record;
        $targetDate = Carbon::parse($record->tanggal)->format('Y-m-d');

        // Batas toleransi pengambilan data log esok hari untuk cover pulang Shift Malam
        $nextDate   = Carbon::parse($record->tanggal)->addDay()->format('Y-m-d');

        $files = $record->file_path;
        if (empty($files) || !is_array($files)) return;

        // Wadah tunggal raksasa untuk menggabungkan data seluruh kantor (Kantor A, B, dst)
        $rawLogs        = [];
        $totalProcessed = 0;

        // ================================================
        // STEP 1: PARSING — Gabungkan Semua Log Multi-File (TXT / DAT)
        // ================================================
        foreach ($files as $file) {
            if (!Storage::disk('public')->exists($file)) continue;

            $fileContent = Storage::disk('public')->get($file);

            // Bersihkan karakter BOM tersembunyi jika ada di file TXT
            $fileContent = str_replace("\xEF\xBB\xBF", '', $fileContent);
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine) || str_contains($trimmedLine, 'DateTime') || str_contains($trimmedLine, 'Kodep')) {
                    continue;
                }

                $parts = str_contains($trimmedLine, "\t")
                    ? explode("\t", $trimmedLine)
                    : preg_split('/\s+/', $trimmedLine);
                $parts = array_values(array_filter(array_map('trim', $parts), fn($v) => $v !== ''));

                if (count($parts) < 2) continue;

                // Deteksi format lewat JUMLAH KOLOM, bukan nebak posisi tanggal.
                if (count($parts) >= 7 && preg_match('/^\d+$/', $parts[2])) {
                    // Format A (txt/GLogData): EnNo di index 2, selalu 9 digit
                    $rawCode        = $parts[2];
                    $dateTimeString = $parts[6];
                } elseif (preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}/', $parts[1] ?? '')) {
                    // Format B (dat/kantor): kode di index 0, sudah bersih
                    $rawCode        = $parts[0];
                    $dateTimeString = $parts[1];
                } else {
                    continue; // baris tak dikenali, lewati
                }

                if (!preg_match('/^\d+$/', $rawCode)) continue;

                // Kode 5 digit berawalan 99 = event sistem/perangkat, bukan pegawai
                if (strlen($rawCode) === 5 && str_starts_with($rawCode, '99')) continue;

                // Normalisasi ke INTEGER murni — ini kunci penyambungan ke tabel Pegawai
                $empCode = (count($parts) >= 7 && strlen($rawCode) > 4)
                    ? (int) substr($rawCode, -4)   // format A: ambil 4 digit terakhir dari EnNo
                    : (int) $rawCode;              // format B: sudah bersih

                if ($empCode <= 0) continue;

                try {
                    $carbonLog = Carbon::parse(str_replace('/', '-', $dateTimeString), 'Asia/Jakarta');
                } catch (\Exception $e) {
                    continue;
                }

                $dateStr = $carbonLog->format('Y-m-d'); // format tanggal diseragamkan di sini
                $timeStr = $carbonLog->format('H:i:s');

                if (!in_array($dateStr, [$targetDate, $nextDate])) continue;

                $rawLogs[$empCode][] = [
                    'date' => $dateStr,
                    'time' => $timeStr,
                    'full' => $carbonLog,
                ];
            }
        }

        // ===============================================

        $semuaPegawai = Pegawai::all()
            ->keyBy(fn($p) => (int) $p->kode_pegawai);

        $pegawaiTerdeteksi = $semuaPegawai->only(array_keys($rawLogs));       // Collection<Pegawai>
        $kodeTidakDikenal  = array_diff(array_keys($rawLogs), $pegawaiTerdeteksi->keys()->all());

        if (!empty($kodeTidakDikenal)) {
            Log::warning("Kode pegawai tidak ditemukan di master data pada $targetDate", [
                'kode' => $kodeTidakDikenal,
            ]);
        }

        // Pre-load shift produksi untuk targetDate sekaligus
        $shiftDryer   = ProduksiPressDryer::whereDate('tanggal_produksi', $targetDate)
            ->with('detailPegawais')
            ->get();
        $shiftHp      = ProduksiHp::whereDate('tanggal_produksi', $targetDate)
            ->with('detailPegawaiHp')
            ->get();
        $shiftSanding = ProduksiSanding::whereDate('tanggal', $targetDate)
            ->with('pegawaiSandings')
            ->get();
        $shiftGraji   = ProduksiGrajitriplek::whereDate('tanggal_produksi', $targetDate)
            ->with('pegawaiGrajiTriplek')
            ->get();

        $pairingService = app(AbsensiPairingService::class);

        // ================================================
        // STEP 2 + 3: PAIRING & SIMPAN (digabung, pakai Service)
        // ================================================
        foreach ($rawLogs as $empCode => $entries) {
            $pegawai     = $semuaPegawai->get($empCode);
            $forcedShift = null;

            if ($pegawai) {
                $shifts = array_filter([
                    $shiftDryer->first(
                        fn($r) => $r->detailPegawais->contains(fn($d) => (int) $d->id_pegawai === (int) $pegawai->id)
                    )?->shift,
                    $shiftHp->first(
                        fn($r) => $r->detailPegawaiHp->contains(fn($d) => (int) $d->id_pegawai === (int) $pegawai->id)
                    )?->shift,
                    $shiftSanding->first(
                        fn($r) => $r->pegawaiSandings->contains(fn($d) => (int) $d->id_pegawai === (int) $pegawai->id)
                    )?->shift,
                    $shiftGraji->first(
                        fn($r) => $r->pegawaiGrajiTriplek->contains(fn($d) => (int) $d->id_pegawai === (int) $pegawai->id)
                    )?->shift,
                ]);

                if (count($shifts) > 0) {
                    $forcedShift = strtoupper(trim(reset($shifts)));

                    if (count(array_unique($shifts)) > 1) {
                        Log::warning("Conflicting shift for $empCode on $targetDate", compact('shifts'));
                    }
                }

                if (!$forcedShift) {
                    $jamMasukSistem = $pegawai->jam_masuk_sistem ?? '07:00:00';
                    if (Carbon::parse($jamMasukSistem)->hour >= 14) {
                        $forcedShift = 'MALAM';
                    } else {
                        $forcedShift = 'PAGI';
                    }
                }
            }

            // INI YANG SEMPAT HILANG — wajib ada supaya pairing beneran jalan
            $result = $pairingService->pairEmployeeLogs(
                entries: $entries,
                targetDate: $targetDate,
                nextDate: $nextDate,
                forcedShift: $forcedShift,
            );

            if ($result && $pegawai) {
                DetailAbsensi::updateOrCreate(
                    ['kode_pegawai' => $empCode, 'tanggal' => $targetDate],
                    [
                        'id_absensi'   => $record->id,
                        'id_pegawai'   => $pegawai->id,
                        'nama_pegawai' => $pegawai->nama_pegawai,
                        'jam_masuk'    => $result['jam_masuk'],
                        'jam_pulang'   => $result['jam_pulang'],
                        'shift'        => $result['shift'],
                        'status'       => $result['status'],
                        'catatan'      => $result['catatan'],
                    ]
                );
                $totalProcessed++;
            }
        }

        // ================================================
        // STEP 4: MATERIALIZE — di LUAR loop, jalan SEKALI setelah semua
        // pegawai yang scan selesai diproses
        // ================================================
        $pegawaiSudahDiproses = DetailAbsensi::where('id_absensi', $record->id)
            ->pluck('id_pegawai')
            ->all();

        $pegawaiBelumAbsen = $semuaPegawai->reject(
            fn($p) => in_array($p->id, $pegawaiSudahDiproses)
        );

        foreach ($pegawaiBelumAbsen as $pegawai) {
            DetailAbsensi::updateOrCreate(
                ['kode_pegawai' => (int) $pegawai->kode_pegawai, 'tanggal' => $targetDate],
                [
                    'id_absensi'   => $record->id,
                    'id_pegawai'   => $pegawai->id,
                    'nama_pegawai' => $pegawai->nama_pegawai,
                    'jam_masuk'    => null,
                    'jam_pulang'   => null,
                    'shift'        => null,
                    'status'       => 'Tidak Absen',
                    'catatan'      => 'Tidak ada data scan pada tanggal ini',
                ]
            );
        }

        // Kirimkan notifikasi keberhasilan di Filament v4
        Notification::make()
            ->success()
            ->title('Import Berhasil')
            ->body("Berhasil memproses & menyatukan $totalProcessed data absensi pegawai.")
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
