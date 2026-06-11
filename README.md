# 🪵 ERP System — PT. Wijaya Plywood Indonesia

> Sistem ERP internal untuk mendukung proses operasional dan administrasi PT. Wijaya Plywood Indonesia, mencakup modul produksi rotary, pencatatan inflow–outflow kayu, manajemen stok, laporan produksi, dan kebutuhan back-office lainnya. Dibangun dengan **Laravel 12** dan panel admin **FilamentPHP 4**.

**Production URL:** [kayu.wijayaplywoods.com](https://kayu.wijayaplywoods.com)

---

## 📋 Daftar Isi

- [Fitur Utama](#-fitur-utama)
- [Tech Stack](#️-tech-stack)
- [Requirements](#-requirements)
- [Instalasi](#-instalasi)
- [Konfigurasi Environment](#-konfigurasi-environment)
- [Menjalankan Aplikasi](#️-menjalankan-aplikasi)
- [Struktur Direktori](#-struktur-direktori)
- [CI/CD Pipeline](#-cicd-pipeline)
- [Panduan Versioning](#-panduan-versioning-semver)
- [Changelog](#-changelog)

---

## ✨ Fitur Utama

| Modul | Deskripsi |
|-------|-----------|
| **VK / VM** | Transfer veneer keluar (VK) dan masuk (VM) antar gudang |
| **BK / BM** | Bukti kayu keluar/masuk, terintegrasi dengan modul VK/VM |
| **Tempat Kayu** | Monitoring ketersediaan kayu di lapangan secara real-time |
| **Laporan Produksi** | Laporan Rotary, Dryer, Joint, dan Export Turunan Kayu |
| **KEDI** | Pencatatan produksi LOG & stok veneer basah |
| **Nota Kayu** | Pengiriman otomatis ke jurnal meski ada selisih |
| **Penggunaan Lahan Rotary** | Validasi lahan; log tidak tercatat jika lahan sudah selesai |
| **Stok & HPP Kayu** | Stok dan HPP log berkurang otomatis saat validasi press/dryer |
| **Press Dryer & Stik** | Modal diambil dari kode serah terima nomor palet rotary |
| **Bahan Penolong Produksi** | Sinkronisasi database Hotpress dan Dempul |
| **Activity Log** | Pencatatan aktivitas pengguna via `rmsramos/activitylog` |
| **Role & Permission** | Manajemen hak akses via Filament Shield |

---

## 🛠️ Tech Stack

### Backend

| Komponen | Teknologi / Versi |
|----------|-------------------|
| Framework | Laravel ^12.0 |
| PHP | ^8.2 (platform: 8.3.28) |
| Admin Panel | FilamentPHP ^4.9 |
| Role & Permission | Filament Shield ^4.0 |
| Realtime | Laravel Reverb ^1.7 + Pusher PHP Server ^7.2 |
| Import / Export | Maatwebsite Excel ^3.1 |
| Image Processing | Intervention Image ^3.11 + Intervention Image Laravel ^1.5 + Spatie Image ^3.8 |
| Activity Log | rmsramos/activitylog ^2.0 |
| Schema Diff | doctrine/dbal ^4.3 |
| Helper | `app/Helpers/UkuranParser.php`, `app/Helpers/HariLiburHelper.php` |

### Frontend / Asset Pipeline

| Komponen | Teknologi |
|----------|-----------|
| Build Tool | Vite |
| CSS Framework | Tailwind CSS + @tailwindcss/vite |
| JS Framework | Alpine.js |
| HTTP Client | Axios |
| Realtime Client | Laravel Echo + pusher-js |
| Dev Tooling | concurrently, sharp |

### Database

| Komponen | Keterangan |
|----------|------------|
| Produksi | MySQL / MariaDB (MariaDB 10.6 di CI) |
| Development | SQLite (default `.env.example`) |

---

## ⚙️ Requirements

- PHP >= 8.2
- Composer
- Node.js >= 20 + npm
- MySQL / MariaDB (untuk produksi)
- Extension PHP: `pdo`, `pdo_mysql`, `mbstring`, `dom`, `fileinfo`, `openssl`, `gd` / `imagick`

---

## 🚀 Instalasi

### 1. Clone Repositori

```bash
git clone https://github.com/Wijaya-Plywood-Indonesia/erp-system.git
cd erp-system
```

### 2. Setup Otomatis (Rekomendasi)

```bash
composer run setup
```

Script ini secara otomatis menjalankan:
- `composer install`
- Copy `.env.example` → `.env`
- Generate application key
- Migrasi database
- `npm install` & `npm run build`

### 3. Setup Manual

```bash
# Install dependensi PHP
composer install

# Copy file environment
cp .env.example .env

# Generate application key
php artisan key:generate

# Jalankan migrasi
php artisan migrate

# Install & build frontend
npm install
npm run build
```

---

## 🔧 Konfigurasi Environment

Sesuaikan file `.env` dengan konfigurasi berikut:

```env
APP_NAME="ERP Wijaya"
APP_URL=https://kayu.wijayaplywoods.com

# Database (MySQL untuk produksi)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_wijaya
DB_USERNAME=root
DB_PASSWORD=your_password

# Realtime dengan Laravel Reverb (atau Pusher)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Alternatif: Pusher
# BROADCAST_CONNECTION=pusher
# PUSHER_APP_ID=
# PUSHER_APP_KEY=
# PUSHER_APP_SECRET=
# PUSHER_APP_CLUSTER=ap1

# Queue (gunakan database atau redis di produksi)
QUEUE_CONNECTION=database
```

---

## ▶️ Menjalankan Aplikasi

### Mode Development

```bash
composer run dev
```

Perintah ini menjalankan secara paralel:
- PHP dev server (`php artisan serve`)
- Queue listener (`php artisan queue:listen --tries=1`)
- Log watcher — Laravel Pail (`php artisan pail --timeout=0`)
- Vite dev server (`npm run dev`)

Aplikasi tersedia di: **http://localhost:8000**

### Mode Production

```bash
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🧪 Testing

```bash
composer run test
```

Atau langsung:

```bash
php artisan test
```

> CI menggunakan database **MariaDB 10.6** dengan DB `laravel_testing` saat menjalankan test suite.

---

## 📁 Struktur Direktori

```
erp-system/
├── .github/
│   └── workflows/
│       └── automation.yml      # CI/CD pipeline utama
├── app/
│   ├── Filament/               # Resource, Pages, Widgets panel admin
│   │   └── Pages/
│   │       └── README.md       # Dokumentasi logika Inflow-Outflow Rotary
│   ├── Helpers/
│   │   ├── UkuranParser.php    # Helper parsing ukuran kayu
│   │   └── HariLiburHelper.php # Helper hari libur nasional
│   ├── Models/                 # Eloquent Models
│   └── Services/               # Business logic / Service layer
├── config/                     # Konfigurasi Laravel
├── database/
│   ├── migrations/             # Migrasi database
│   ├── seeders/                # Data awal
│   └── factories/              # Factory untuk testing
├── docs/
│   ├── diagrams/               # Diagram arsitektur & alur proses
│   └── out/                    # Output dokumentasi
├── public/                     # Entry point web + asset publik
├── relations/                  # Dokumentasi relasi antar model/tabel
├── resources/
│   ├── views/                  # Blade templates
│   └── css/ & js/              # Asset frontend
├── routes/                     # Definisi routing
├── scratch/                    # File riset / eksperimen developer
├── storage/                    # Log, cache, file upload
├── tests/                      # Unit & Feature tests
├── accounts.csv                # Data akun COA awal
├── composer.json               # Dependensi PHP
├── package.json                # Dependensi JavaScript
├── tailwind.config.js          # Konfigurasi Tailwind CSS
└── vite.config.js              # Konfigurasi Vite
```

---

## 🔐 Akses Panel Admin

Panel admin tersedia di:

```
http://localhost:8000/admin
```

Setelah instalasi, setup role dan permission dengan:

```bash
# Generate semua policy & permission dari resource Filament
php artisan shield:generate --all

# Jadikan user pertama sebagai super admin
php artisan shield:super-admin --user=1
```

---

## 🚦 CI/CD Pipeline

Repositori ini menggunakan **GitHub Actions** dengan workflow bernama **"Laravel 12 CI/CD Full Automation"**, tersimpan di `.github/workflows/automation.yml`. Pipeline ini telah berjalan lebih dari **624 kali**.

### Trigger / Pemicu

| Event | Kondisi | Job yang Dijalankan |
|-------|---------|---------------------|
| `push` | Branch `main` | CI Testing saja |
| `create tag` | Tag diawali `v*` (contoh: `v2.1.4`) | CI Testing → Deploy Production |

### Alur Pipeline

```
Push ke main
    │
    ▼
┌─────────────────────────┐
│   JOB 1: laravel-tests  │  ← Selalu jalan saat push ke main
│   (CI — Run Tests)      │
└────────────┬────────────┘
             │  Hanya jika trigger = tag v*
             ▼
┌─────────────────────────────────────┐
│  JOB 2: deploy-production           │  ← Hanya saat release (tag)
│  Deploy ke kayu.wijayaplywoods.com  │
└─────────────────────────────────────┘
```

---

### JOB 1 — CI Testing (`laravel-tests`)

Berjalan di **ubuntu-latest** dengan service **MariaDB 10.6**.

| Langkah | Keterangan |
|---------|------------|
| Checkout kode | `actions/checkout@v4` |
| Setup PHP 8.4 | Via `shivammathur/setup-php@v2`, ekstensi: `mbstring`, `dom`, `fileinfo`, `mysql`, `pdo_mysql` |
| Setup Node.js 20 | Via `actions/setup-node@v4` |
| Copy `.env` | Dari `.env.example` jika belum ada |
| Install Composer | `composer install --no-ansi --no-interaction --no-scripts --prefer-dist` |
| Build Vite | `npm install && npm run build` (wajib sebelum artisan agar `manifest.json` terbentuk) |
| Generate App Key | `php artisan key:generate` |
| Set Permissions | `chmod -R 777 storage bootstrap/cache` |
| Run Migrations | Terhadap DB `laravel_testing` di MariaDB |
| **Run Tests** | `php artisan test` |

---

### JOB 2 — Deploy Production (`deploy-production`)

Berjalan **hanya saat push tag** yang diawali `v*` (contoh: `git tag v2.1.4 && git push --tags`), dan hanya jika JOB 1 berhasil (`needs: laravel-tests`).

**Target server:** `kayu.wijayaplywoods.com`

#### Langkah Deployment

| # | Langkah | Keterangan |
|---|---------|------------|
| 1 | Setup PHP 8.4 | Build di runner GitHub |
| 2 | Buat `.env` sementara | Isi dummy APP_KEY + Pusher agar `package:discover` berjalan |
| 3 | Install Composer (prod) | `--no-dev --optimize-autoloader --no-scripts` |
| 4 | Run `package:discover` | Manual setelah `.env` tersedia |
| 5 | Setup Node.js 20 | |
| 6 | Install NPM | `npm ci` |
| 7 | Build Frontend | `npm run build` |
| 8 | Buat ZIP | `rsync` + `zip` — exclude `.git`, `.github`, `node_modules`, `tests`, `.env`, `storage` |
| 9 | Setup SSH Key | Dari secret `SSH_PRIVATE_KEY` |
| 10 | Upload ZIP ke server | Via `appleboy/scp-action@v0.1.7` ke home directory |
| 11 | **Eksekusi SSH di server** | Via `appleboy/ssh-action@v1.0.0` |

#### Proses di Server (Remote SSH)

```bash
# 1. Masuk ke direktori target
cd /home/{SSH_USERNAME}/kayu.wijayaplywoods.com

# 2. Aktifkan maintenance mode
php artisan down

# 3. Hapus file lama (kecuali storage/ dan .env)
find . -mindepth 1 -maxdepth 1 ! -name 'storage' ! -name '.env' -exec rm -rf {} \;

# 4. Ekstrak file baru dari ZIP
unzip -oq ../deploy.zip -d .

# 5. Optimasi cache Laravel
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Matikan maintenance mode
php artisan up

# 7. Hapus ZIP untuk hemat storage
rm ../deploy.zip
```

---

### GitHub Secrets yang Dibutuhkan

Konfigurasi secrets berikut di **Settings → Secrets and variables → Actions** pada repositori:

| Secret | Keterangan |
|--------|------------|
| `SSH_HOST` | IP atau hostname server produksi |
| `SSH_USERNAME` | Username SSH untuk login ke server |
| `SSH_PORT` | Port SSH server (umumnya `22`) |
| `SSH_PRIVATE_KEY` | Private key SSH (format ED25519 atau RSA) |

> **Catatan:** File `.env` produksi dan folder `storage/` **tidak akan tertimpa** saat deployment, karena keduanya dikecualikan secara eksplisit dalam proses ekstraksi ZIP.

---

### Cara Melakukan Release / Deploy ke Produksi

```bash
# 1. Pastikan semua perubahan sudah di-push ke branch main
git push origin main

# 2. Buat tag versi baru (format v*)
git tag v2.1.5

# 3. Push tag — ini yang memicu deployment ke server produksi
git push origin v2.1.5
```

---

## 📦 Panduan Versioning (SemVer)

Format versi: `vMajor.Minor.Patch` — contoh: `v2.1.4`

| Tipe | Kapan digunakan |
|------|-----------------|
| **Major** | Perubahan besar, breaking changes, atau arsitektur ulang |
| **Minor** | Penambahan fitur baru yang tetap backward-compatible |
| **Patch** | Bug fix, perbaikan kecil, tanpa fitur baru |

---

## 📋 Changelog

| Versi | Tanggal | Ringkasan Perubahan |
|-------|---------|---------------------|
| **v2.1.4** *(latest)* | 22 Mei 2026 | VK/VM transfer veneer; BK/BM integrasi VK/VM; Tempat Kayu real-time; Laporan Produksi (Rotary, Dryer, Joint, Export Turunan Kayu); KEDI log & stok veneer basah; Sinkronisasi bahan penolong (Hotpress, Dempul) |
| **2.1.4** | 23 Apr 2026 | Intermediate release |
| **v2.1.3** | 15 Apr 2026 | Perbaikan fitur dan bug dari versi sebelumnya |
| **v2.1.2** | 15 Apr 2026 | Nota kayu → jurnal otomatis meski ada selisih; token service API; Tempat kayu otomatis 0 saat kayu dipakai; revisi penggunaan lahan rotary; stok & HPP log berkurang saat validasi press dryer; modal press dryer dari kode serah terima palet |
| **V.2.1.1** | 10 Apr 2026 | Perbaikan minor lanjutan |
| **V.2.1.0** | 9 Apr 2026 | Update sembarang (hotfix gabungan) |
| **V.2.0.5** | 7 Apr 2026 | Perbaikan lanjutan v2.0.4 |
| **V2.0.4** | 4 Apr 2026 | Perbaikan lanjutan v2.0.3 |
| **V.2.0.3** | 30 Mar 2026 | Perbaikan lanjutan v2.0.2 |
| **V2.0.2** | 17 Mar 2026 | Bug fixing semua divisi; penambahan migrations |
| **V2.0.1-1** | 17 Mar 2026 | Hotfix: excludes `build_deploy/` dari rsync di `automation.yml` |
| **V2.0.1** | 16 Mar 2026 | Exclude `build_deploy/` dari rsync di automation CI/CD |
| **V2.0.0** | 16 Mar 2026 | Rilis mayor versi 2.0 |

Lihat semua rilis: [GitHub Releases](https://github.com/Wijaya-Plywood-Indonesia/erp-system/releases)

---

## 👥 Tim Pengembang

Dikembangkan oleh tim IT **PT. Wijaya Plywood Indonesia**.

---

## 📄 Lisensi

Proyek ini bersifat **privat** dan hanya digunakan untuk keperluan internal PT. Wijaya Plywood Indonesia.

---

*Dokumentasi ini akan terus diperbarui seiring perkembangan aplikasi.*
