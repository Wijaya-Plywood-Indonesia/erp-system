# 🪵 Sistem Inflow-Outflow Produksi Veneer Rotary

Dokumentasi ini menjelaskan logika teknis dan arsitektur perhitungan yang digunakan dalam `ProduksiInflowService` untuk mensinkronisasi data kayu masuk (Inflow) dengan hasil produksi (Outflow).

## 📌 1. Konsep Korelasi Batch
Sistem ini menggunakan metode **Window Time Correlation**. Data tidak dihubungkan dengan ID manual, melainkan dijahit berdasarkan rentang waktu produksi:

- **Start Window**: Diambil dari waktu penutupan (`closure`) batch sebelumnya pada lahan yang sama.
- **End Window**: Diambil dari waktu pembukaan/penutupan batch saat ini.
- **Filter**: Dilakukan berdasarkan `id_lahan` dan `id_jenis_kayu`.

## 📐 2. Algoritma Perhitungan Presisi
Untuk memastikan angka di sistem identik dengan laporan manual (Excel Mentor), digunakan aturan pembulatan berikut:

### A. Kubikasi Kayu Log (Inflow)
Rumus menggunakan konstanta silinder standar:
\[ Volume = \frac{Panjang \times Diameter \times Diameter \times 0.785}{1.000.000} \]
- **Akurasi**: Dihitung di level database dengan `DECIMAL(20,4)`.
- **Rounding**: Menggunakan `ROUND(..., 4)` per baris sebelum di-SUM.

### B. Perhitungan Poin (Nilai Rupiah)
Untuk menghindari selisih akumulasi desimal, digunakan metode **Floor-Per-Line**:
- **Rumus SQL**: `SUM( FLOOR( Harga_Beli * Kubikasi_Baris * 1000 ) )`
- **Tujuan**: Memangkas desimal di setiap baris (meniru perilaku Excel yang menyembunyikan desimal) sehingga hasil total akhir sinkron dengan pembukuan akuntansi.

## 🛠 3. Fitur Pencegahan Data Gaib (Price Audit)
Sistem memiliki mekanisme deteksi otomatis jika ada kayu yang masuk namun harganya tidak ditemukan (Diameter tidak tercover di Master Harga):

- **`harga_kosong_count`**: Menghitung baris yang gagal mendapatkan harga beli.
- **UI Warning**: Jika `count > 0`, sistem akan memunculkan indikator ⚠️ pada nomor seri nota untuk memberitahu admin agar segera melengkapi Master Harga.

## 📊 4. Indikator Performa (KPI)
Laporan ini secara otomatis menghitung:
1. **Rendemen**: Persentase efisiensi perubahan Log menjadi Veneer.
2. **Harga Veneer**: Nilai bahan baku murni per m3.
3. **Harga VOP**: Total biaya produksi (Bahan Baku + Ongkos Kerja + Penyusutan Mesin).

## 💻 5. Teknis Implementasi
Data diproses menggunakan **Laravel Eloquent** yang dikombinasikan dengan **Raw SQL** untuk performa tinggi:
- **Eager Loading**: Meminimalkan N+1 query pada relasi mesin dan pegawai.
- **SQL Join**: Menggunakan `Left Join` pada tabel `harga_kayus` dengan kriteria range diameter (`BETWEEN`).

---
*Dokumentasi ini dibuat untuk memastikan transparansi logika perhitungan antara developer dan tim akuntansi.*