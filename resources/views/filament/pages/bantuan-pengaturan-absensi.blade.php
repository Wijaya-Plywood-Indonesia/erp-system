<div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
    <div class="p-4 bg-primary-50 dark:bg-primary-900/30 rounded-xl border border-primary-100 dark:border-primary-800">
        <h3 class="font-semibold text-primary-800 dark:text-primary-300 mb-2">1. Jadwal Default (Fallback)</h3>
        <p class="mb-2"><strong>Masuk Pagi & Pulang Pagi:</strong> Digunakan sebagai patokan untuk menebak apakah sebuah scan adalah check-in atau check-out <strong>jika pegawai hanya finger 1 kali</strong> dan di hari tersebut ia <strong>tidak memiliki jam kerja produksi</strong>.</p>
        <p><strong>Masuk Malam & Pulang Malam:</strong> Digunakan untuk validasi shift malam. Jika pegawai shift malam tapi tidak punya jadwal produksi di hari tersebut, sistem akan memakai jam ini untuk mencegah sisa scan kemarin terekam ke hari ini.</p>
    </div>

    <div class="p-4 bg-green-50 dark:bg-green-900/30 rounded-xl border border-green-100 dark:border-green-800">
        <h3 class="font-semibold text-green-800 dark:text-green-300 mb-2">2. Toleransi Shift Malam</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Masuk Terlalu Cepat:</strong> Mencegah sisa scan pulang dari shift sebelumnya agar tidak terhitung sebagai scan masuk. Jika scan masuk mendahului jadwal lebih dari batas ini (misal lebih awal 5 jam), maka dianulir.</li>
            <li><strong>Pulang Terlalu Lambat:</strong> Mencegah scan masuk shift berikutnya agar tidak terhitung sebagai scan pulang shift malam ini.</li>
        </ul>
    </div>

    <div class="p-4 bg-amber-50 dark:bg-amber-900/30 rounded-xl border border-amber-100 dark:border-amber-800">
        <h3 class="font-semibold text-amber-800 dark:text-amber-300 mb-2">3. Auto Fix (Raw Finger)</h3>
        <p>Fitur ini membandingkan jam finger hasil kalkulasi (yang sudah dimasukkan ke rekap) dengan jam produksi aktual. Jika selisihnya melampaui batas yang ditentukan (misal lebih dari 60 menit), maka sistem akan "memaksa" mengambil data mentah dari mesin finger:</p>
        <ul class="list-disc pl-5 mt-1">
            <li><strong>Jam Masuk:</strong> Mengambil scan pertama (paling pagi) di raw finger.</li>
            <li><strong>Jam Pulang:</strong> Mengambil scan terakhir (paling malam) di raw finger.</li>
        </ul>
    </div>
</div>
