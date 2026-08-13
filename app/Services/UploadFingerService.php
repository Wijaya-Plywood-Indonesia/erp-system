<?php

namespace App\Services;

use App\Models\NewAbsensiUpload;
use App\Models\NewDataFinger;
use App\Services\FingerParsers\FingerParserManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadFingerService
{
    protected const DISK = 'public'; // sesuaikan dengan disk yang dipakai file_path yang sudah ada

    protected const UPLOAD_FOLDER = 'absensi-logs';

    public function __construct(
        protected FingerParserManager $parserManager,
    ) {}

    /**
     * @param  UploadedFile[]  $files  Bisa 1 atau lebih file (multi-mesin), diproses sebagai 1 batch.
     * @param  string  $tanggalBatch  Tanggal dari datepicker di UI (filter tanggal
     *                                halaman). Dipakai SEBAGAI-ADANYA untuk kolom
     *                                `tanggal` di NewAbsensiUpload (label/filter
     *                                riwayat batch saja). TIDAK memengaruhi tanggal
     *                                per-tap yang tersimpan ke NewDataFinger — itu
     *                                tetap murni hasil parsing isi file mentah.
     */
    public function handle(array $files, string $uploadedBy, string $tanggalBatch): NewAbsensiUpload
    {
        // 1. Simpan semua file fisik ke storage dulu, dengan nama yang lebih manusiawi.
        $storedPaths = collect($files)->map(
            fn (UploadedFile $file) => $file->storeAs(
                self::UPLOAD_FOLDER,
                $this->generateHumanFileName($file),
                self::DISK
            )
        )->values();

        // 2. Parse semua file jadi tap mentah yang seragam.
        //    Setiap tap: ['kode_pegawai' => ..., 'waktu' => Carbon, 'tanggal' => 'Y-m-d']
        $semuaTap = $storedPaths->flatMap(function (string $relativePath) {
            $absolutePath = Storage::disk(self::DISK)->path($relativePath);
            $parser = $this->parserManager->resolve($absolutePath);

            return $parser->parse($absolutePath)->map(function ($tap) {
                $tap['tanggal'] = $tap['waktu']->format('Y-m-d');

                return $tap;
            });
        });

        if ($semuaTap->isEmpty()) {
            throw new \RuntimeException('Tidak ada data tap yang berhasil dibaca dari file yang diupload. Cek kembali format file.');
        }

        // 3. Tanggal batch = dari datepicker UI ($tanggalBatch, parameter),
        //    BUKAN lagi hasil min() dari tanggal parsing file. Ini cuma
        //    label/filter riwayat upload, sama sekali tidak dipakai untuk
        //    menentukan tanggal tap per pegawai di new_data_finger.
        return DB::transaction(function () use ($storedPaths, $uploadedBy, $tanggalBatch, $semuaTap) {
            // 4. Buat 1 row batch upload.
            $upload = NewAbsensiUpload::create([
                'tanggal' => $tanggalBatch,
                'file_path' => $storedPaths->all(),
                'uploaded_by' => $uploadedBy,
            ]);

            // 5. Agregasi per (kode_pegawai, tanggal): ambil MIN & MAX waktu tap.
            $agregat = $semuaTap
                ->groupBy(fn ($tap) => $tap['kode_pegawai'].'|'.$tap['tanggal'])
                ->map(function ($taps) {
                    $sorted = $taps->sortBy('waktu');

                    return [
                        'kode_pegawai' => $sorted->first()['kode_pegawai'],
                        'tanggal' => $sorted->first()['tanggal'],
                        'jam_masuk' => $sorted->first()['waktu'],
                        'jam_pulang' => $sorted->last()['waktu'],
                    ];
                })
                ->values();

            // 6. Merge ke new_data_finger: kalau row (kode_pegawai+tanggal) sudah
            //    ada, MIN/MAX-kan dengan nilai lama, jangan overwrite polos.
            foreach ($agregat as $item) {
                $this->mergeRow($item, $upload->id);
            }

            return $upload;
        });
    }

    /**
     * Bikin nama file yang lebih manusiawi berdasarkan sumber & tanggal upload.
     * .txt dianggap dari mesin fingerprint pabrik, selain itu dianggap dari kantor.
     *
     * Contoh hasil: pabrik-12-08-2026.txt / kantor-12-08-2026.dat
     */
    protected function generateHumanFileName(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $sumber = $extension === 'txt' ? 'pabrik' : 'kantor';

        $tanggal = now()->format('d-m-Y');

        return "{$sumber}-{$tanggal}.{$extension}";
    }

    protected function mergeRow(array $item, int $uploadId): void
    {
        $existing = NewDataFinger::query()
            ->where('kode_pegawai', $item['kode_pegawai'])
            ->whereDate('tanggal', $item['tanggal'])
            ->first();

        if (! $existing) {
            NewDataFinger::create([
                'kode_pegawai' => $item['kode_pegawai'],
                'tanggal' => $item['tanggal'],
                'jam_masuk' => $item['jam_masuk']->format('H:i:s'),
                'jam_pulang' => $item['jam_pulang']->format('H:i:s'),
                'id_absensi_masuk' => $uploadId,
                'id_absensi_pulang' => $uploadId,
            ]);

            return;
        }

        $waktuMasukLama = $existing->jam_masuk
            ? Carbon::parse($item['tanggal'].' '.$existing->jam_masuk)
            : null;

        $waktuPulangLama = $existing->jam_pulang
            ? Carbon::parse($item['tanggal'].' '.$existing->jam_pulang)
            : null;

        $update = [];

        // jam_masuk baru = yang paling kecil antara nilai lama vs nilai baru
        if (! $waktuMasukLama || $item['jam_masuk']->lt($waktuMasukLama)) {
            $update['jam_masuk'] = $item['jam_masuk']->format('H:i:s');
            $update['id_absensi_masuk'] = $uploadId;
        }

        // jam_pulang baru = yang paling besar antara nilai lama vs nilai baru
        if (! $waktuPulangLama || $item['jam_pulang']->gt($waktuPulangLama)) {
            $update['jam_pulang'] = $item['jam_pulang']->format('H:i:s');
            $update['id_absensi_pulang'] = $uploadId;
        }

        if (! empty($update)) {
            $existing->update($update);
        }
    }
}
