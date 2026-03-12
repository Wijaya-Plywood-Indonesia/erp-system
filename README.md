# ERP System — Wijaya Plywood Indonesia

Sistem ERP internal untuk mendukung proses operasional dan administrasi (termasuk modul produksi/rotary, pencatatan inflow–outflow kayu, dan kebutuhan backoffice lain) berbasis **Laravel** dengan panel admin **Filament**.

## Tech Stack

### Backend
- **PHP**: ^8.2 (platform composer di-set ke **8.3.28**)
- **Laravel Framework**: ^12.0
- **Filament**: 4.0
- **Filament Shield**: role & permission (`bezhansalleh/filament-shield`)
- Realtime:
  - `laravel/reverb`
  - `pusher/pusher-php-server`
- Import/Export:
  - `maatwebsite/excel`
- Image processing:
  - `intervention/image` + `intervention/image-laravel`
  - `spatie/image`
- Utility:
  - `doctrine/dbal`
  - `laravel/tinker`
  - `rmsramos/activitylog`

### Frontend / Asset Pipeline
- **Vite**
- **TailwindCSS** (+ `@tailwindcss/vite`)
- **AlpineJS**
- **Axios**
- Realtime client:
  - `laravel-echo`
  - `pusher-js`
- Tooling:
  - `concurrently`
  - `sharp`

## Struktur Project (Ringkas)

Struktur mengikuti standar Laravel:

- `app/` — sumber kode utama aplikasi
  - `app/Filament/...` — Pages/Resources/Widgets Filament (panel admin)
- `routes/` — definisi route
- `database/` — migration, seeder, factory
- `resources/` — Blade views, asset, dan resource lain
- `public/` — entrypoint web + asset publik
- `config/` — konfigurasi Laravel
- `storage/` — log, cache, file runtime
- `tests/` — pengujian aplikasi
- `docs/` — dokumentasi/diagram tambahan
  - `docs/diagrams/`
  - `docs/out/`
- Lainnya:
  - `artisan` — CLI Laravel
  - `vite.config.js`, `tailwind.config.js`
  - `composer.json`, `package.json`

## Instalasi & Menjalankan Project

### Prasyarat
- PHP (sesuai `composer.json`)
- Composer
- Node.js + npm
- Database (sesuaikan `.env`)

### Setup cepat
Project menyediakan script composer `setup`:

```bash
composer run setup
```

Script ini melakukan:
- `composer install`
- copy `.env.example` → `.env` (jika belum ada)
- generate APP_KEY
- migrate database
- `npm install`
- `npm run build`

### Development mode
Jalankan mode dev (server, queue listener, log/pail, dan Vite secara parallel):

```bash
composer run dev
```

Atau manual (opsional):
```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## Testing
```bash
composer run test
```

## Dokumentasi Teknis Terkait Produksi (Rotary)

Terdapat dokumentasi teknis khusus untuk logika perhitungan **Inflow–Outflow Produksi Rotary** di:

- `app/Filament/Pages/README.md`

Ringkasan isi:
- Korelasi batch menggunakan pendekatan **window time correlation** (berdasarkan rentang waktu, lahan, dan jenis kayu).
- Aturan pembulatan & presisi untuk memastikan angka sama dengan laporan manual/Excel.
- Mekanisme audit “harga kosong” (warning jika master harga tidak mencakup diameter tertentu).
- KPI otomatis (rendemen, harga veneer, harga VOP).
- Implementasi memadukan Eloquent + Raw SQL untuk performa.

## Panduan Penulisan Versi (Semantic Version / SemVer)

Format: `vMajor.Minor.Patch` (contoh: `v1.0.0`)

- **Major**: perubahan besar dan/atau breaking changes.
- **Minor**: penambahan fitur baru namun tetap kompatibel.
- **Patch**: bug fix/perbaikan kecil tanpa fitur baru.

> Konten bagian ini berasal dari README root yang sudah ada.

## Lisensi
Ikuti lisensi yang berlaku di repository ini (jika ada).
