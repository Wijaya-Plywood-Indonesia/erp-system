/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `absensis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absensis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `anak_akuns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anak_akuns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_induk_akun` bigint unsigned NOT NULL,
  `kode_anak_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_anak_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parent` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `anak_akuns_kode_anak_akun_unique` (`kode_anak_akun`),
  KEY `anak_akuns_id_induk_akun_foreign` (`id_induk_akun`),
  KEY `anak_akuns_parent_foreign` (`parent`),
  KEY `anak_akuns_created_by_foreign` (`created_by`),
  CONSTRAINT `anak_akuns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `anak_akuns_id_induk_akun_foreign` FOREIGN KEY (`id_induk_akun`) REFERENCES `induk_akuns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anak_akuns_parent_foreign` FOREIGN KEY (`parent`) REFERENCES `anak_akuns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan_dempuls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan_dempuls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_dempul` bigint unsigned NOT NULL,
  `nama_bahan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_dempuls_id_produksi_dempul_foreign` (`id_produksi_dempul`),
  CONSTRAINT `bahan_dempuls_id_produksi_dempul_foreign` FOREIGN KEY (`id_produksi_dempul`) REFERENCES `produksi_dempuls` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan_hotpress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan_hotpress` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_hp` bigint unsigned NOT NULL,
  `no_palet` int NOT NULL,
  `id_barang_setengah_jadi` bigint unsigned DEFAULT NULL,
  `isi` int NOT NULL,
  `ket` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_hotpress_id_produksi_hp_foreign` (`id_produksi_hp`),
  KEY `bahan_hotpress_id_barang_setengah_jadi_foreign` (`id_barang_setengah_jadi`),
  CONSTRAINT `bahan_hotpress_id_barang_setengah_jadi_foreign` FOREIGN KEY (`id_barang_setengah_jadi`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `bahan_hotpress_id_produksi_hp_foreign` FOREIGN KEY (`id_produksi_hp`) REFERENCES `produksi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan_penolong_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan_penolong_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_hp` bigint unsigned NOT NULL,
  `nama_bahan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_penolong_hp_id_produksi_hp_foreign` (`id_produksi_hp`),
  CONSTRAINT `bahan_penolong_hp_id_produksi_hp_foreign` FOREIGN KEY (`id_produksi_hp`) REFERENCES `produksi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan_pilih_plywood`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan_pilih_plywood` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_plywood` bigint unsigned NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned NOT NULL,
  `no_palet` int NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_pilih_plywood_id_produksi_pilih_plywood_foreign` (`id_produksi_pilih_plywood`),
  KEY `bahan_pilih_plywood_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `bahan_pilih_plywood_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `bahan_pilih_plywood_id_produksi_pilih_plywood_foreign` FOREIGN KEY (`id_produksi_pilih_plywood`) REFERENCES `produksi_pilih_plywood` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan_produksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan_produksi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_joint` bigint unsigned NOT NULL,
  `nama_bahan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_produksi_id_produksi_joint_foreign` (`id_produksi_joint`),
  CONSTRAINT `bahan_produksi_id_produksi_joint_foreign` FOREIGN KEY (`id_produksi_joint`) REFERENCES `produksi_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan_repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan_repairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_repair` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `id_jenis` bigint unsigned NOT NULL,
  `kw` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_lembar` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_repairs_id_repair_foreign` (`id_repair`),
  KEY `bahan_repairs_id_ukuran_foreign` (`id_ukuran`),
  KEY `bahan_repairs_id_jenis_foreign` (`id_jenis`),
  CONSTRAINT `bahan_repairs_id_jenis_foreign` FOREIGN KEY (`id_jenis`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bahan_repairs_id_repair_foreign` FOREIGN KEY (`id_repair`) REFERENCES `repairs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bahan_repairs_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `barang_setengah_jadi_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barang_setengah_jadi_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_barang` bigint unsigned DEFAULT NULL,
  `id_grade` bigint unsigned DEFAULT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barang_setengah_jadi_hp_id_ukuran_foreign` (`id_ukuran`),
  KEY `barang_setengah_jadi_hp_id_jenis_barang_foreign` (`id_jenis_barang`),
  KEY `barang_setengah_jadi_hp_id_grade_foreign` (`id_grade`),
  CONSTRAINT `barang_setengah_jadi_hp_id_grade_foreign` FOREIGN KEY (`id_grade`) REFERENCES `grades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `barang_setengah_jadi_hp_id_jenis_barang_foreign` FOREIGN KEY (`id_jenis_barang`) REFERENCES `jenis_barang` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `barang_setengah_jadi_hp_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `barang_ujis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barang_ujis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_kategori_barang` bigint unsigned DEFAULT NULL,
  `id_jenis_barang` bigint unsigned DEFAULT NULL,
  `id_grade` bigint unsigned DEFAULT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barang_ujis_id_ukuran_foreign` (`id_ukuran`),
  KEY `barang_ujis_id_kategori_barang_foreign` (`id_kategori_barang`),
  KEY `barang_ujis_id_jenis_barang_foreign` (`id_jenis_barang`),
  KEY `barang_ujis_id_grade_foreign` (`id_grade`),
  CONSTRAINT `barang_ujis_id_grade_foreign` FOREIGN KEY (`id_grade`) REFERENCES `grade_barang_ujis` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `barang_ujis_id_jenis_barang_foreign` FOREIGN KEY (`id_jenis_barang`) REFERENCES `jenis_barang_ujis` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `barang_ujis_id_kategori_barang_foreign` FOREIGN KEY (`id_kategori_barang`) REFERENCES `ketegori_barang_ujis` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `barang_ujis_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_kelamin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `karyawan_di` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_perusahaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempat_tanggal_lahir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `no_telepon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontrak_mulai` date DEFAULT NULL,
  `kontrak_selesai` date DEFAULT NULL,
  `durasi_kontrak` int DEFAULT NULL,
  `tanggal_kontrak` date DEFAULT NULL,
  `no_kontrak` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_dokumen` enum('draft','dicetak','ditandatangani') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `bukti_ttd` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dibuat_oleh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `divalidasi_oleh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_kontrak` enum('active','soon','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_absensis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_absensis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_absensi` bigint unsigned NOT NULL,
  `kode_pegawai` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `detail_absensis_kode_pegawai_tanggal_unique` (`kode_pegawai`,`tanggal`),
  KEY `detail_absensis_id_absensi_foreign` (`id_absensi`),
  KEY `detail_absensis_kode_pegawai_index` (`kode_pegawai`),
  CONSTRAINT `detail_absensis_id_absensi_foreign` FOREIGN KEY (`id_absensi`) REFERENCES `absensis` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_barang_dikerjakan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_barang_dikerjakan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_nyusup` bigint unsigned NOT NULL,
  `id_pegawai_nyusup` bigint unsigned NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `no_palet` int NOT NULL,
  `modal` int NOT NULL,
  `hasil` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_barang_dikerjakan_id_produksi_nyusup_foreign` (`id_produksi_nyusup`),
  KEY `detail_barang_dikerjakan_id_pegawai_nyusup_foreign` (`id_pegawai_nyusup`),
  KEY `detail_barang_dikerjakan_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `detail_barang_dikerjakan_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_id_pegawai_nyusup_foreign` FOREIGN KEY (`id_pegawai_nyusup`) REFERENCES `pegawai_nyusup` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_id_produksi_nyusup_foreign` FOREIGN KEY (`id_produksi_nyusup`) REFERENCES `produksi_nyusup` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_barang_dikerjakan_pot_jelek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_barang_dikerjakan_pot_jelek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_jelek` bigint unsigned NOT NULL,
  `id_pegawai_pot_jelek` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tinggi` int NOT NULL,
  `no_palet` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_barang_dikerjakan_pot_jelek_id_produksi_pot_jelek_foreign` (`id_produksi_pot_jelek`),
  KEY `detail_barang_dikerjakan_pot_jelek_id_pegawai_pot_jelek_foreign` (`id_pegawai_pot_jelek`),
  KEY `detail_barang_dikerjakan_pot_jelek_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_barang_dikerjakan_pot_jelek_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `detail_barang_dikerjakan_pot_jelek_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_pot_jelek_id_pegawai_pot_jelek_foreign` FOREIGN KEY (`id_pegawai_pot_jelek`) REFERENCES `pegawai_pot_jelek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_pot_jelek_id_produksi_pot_jelek_foreign` FOREIGN KEY (`id_produksi_pot_jelek`) REFERENCES `produksi_pot_jelek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_pot_jelek_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_barang_dikerjakan_pot_siku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_barang_dikerjakan_pot_siku` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_siku` bigint unsigned NOT NULL,
  `id_pegawai_pot_siku` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tinggi` int NOT NULL,
  `no_palet` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_barang_dikerjakan_pot_siku_id_produksi_pot_siku_foreign` (`id_produksi_pot_siku`),
  KEY `detail_barang_dikerjakan_pot_siku_id_pegawai_pot_siku_foreign` (`id_pegawai_pot_siku`),
  KEY `detail_barang_dikerjakan_pot_siku_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_barang_dikerjakan_pot_siku_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `detail_barang_dikerjakan_pot_siku_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_pot_siku_id_pegawai_pot_siku_foreign` FOREIGN KEY (`id_pegawai_pot_siku`) REFERENCES `pegawai_pot_siku` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_pot_siku_id_produksi_pot_siku_foreign` FOREIGN KEY (`id_produksi_pot_siku`) REFERENCES `produksi_pot_siku` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_barang_dikerjakan_pot_siku_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_bongkar_kedi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_bongkar_kedi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `no_palet` int NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `kw` int NOT NULL,
  `jumlah` int NOT NULL,
  `id_produksi_kedi` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_bongkar_kedi_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `detail_bongkar_kedi_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_bongkar_kedi_id_produksi_kedi_foreign` (`id_produksi_kedi`),
  CONSTRAINT `detail_bongkar_kedi_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_bongkar_kedi_id_produksi_kedi_foreign` FOREIGN KEY (`id_produksi_kedi`) REFERENCES `produksi_kedi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_bongkar_kedi_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_dempul_pegawai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_dempul_pegawai` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_detail_dempul` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_dempul_pegawai_id_detail_dempul_foreign` (`id_detail_dempul`),
  KEY `detail_dempul_pegawai_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `detail_dempul_pegawai_id_detail_dempul_foreign` FOREIGN KEY (`id_detail_dempul`) REFERENCES `detail_dempuls` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_dempul_pegawai_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_dempuls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_dempuls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_dempul` bigint unsigned NOT NULL,
  `id_rencana_pegawai_dempul` bigint unsigned NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned NOT NULL,
  `modal` int NOT NULL,
  `hasil` int NOT NULL,
  `nomor_palet` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_dempuls_id_produksi_dempul_foreign` (`id_produksi_dempul`),
  KEY `detail_dempuls_id_rencana_pegawai_dempul_foreign` (`id_rencana_pegawai_dempul`),
  KEY `detail_dempuls_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `detail_dempuls_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_dempuls_id_produksi_dempul_foreign` FOREIGN KEY (`id_produksi_dempul`) REFERENCES `produksi_dempuls` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_dempuls_id_rencana_pegawai_dempul_foreign` FOREIGN KEY (`id_rencana_pegawai_dempul`) REFERENCES `rencana_pegawai_dempuls` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_hasil_palet_rotaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_hasil_palet_rotaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi` bigint unsigned NOT NULL,
  `id_penggunaan_lahan` bigint unsigned DEFAULT NULL,
  `timestamp_laporan` datetime NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `palet` int DEFAULT NULL,
  `total_lembar` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_hasil_palet_rotaries_id_produksi_foreign` (`id_produksi`),
  KEY `detail_hasil_palet_rotaries_id_penggunaan_lahan_foreign` (`id_penggunaan_lahan`),
  KEY `detail_hasil_palet_rotaries_id_ukuran_foreign` (`id_ukuran`),
  CONSTRAINT `detail_hasil_palet_rotaries_id_penggunaan_lahan_foreign` FOREIGN KEY (`id_penggunaan_lahan`) REFERENCES `penggunaan_lahan_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_hasil_palet_rotaries_id_produksi_foreign` FOREIGN KEY (`id_produksi`) REFERENCES `produksi_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_hasil_palet_rotaries_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_hasil_stik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_hasil_stik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `no_palet` int NOT NULL,
  `kw` int NOT NULL,
  `total_lembar` int NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `id_produksi_stik` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_hasil_stik_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_hasil_stik_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `detail_hasil_stik_id_produksi_stik_foreign` (`id_produksi_stik`),
  CONSTRAINT `detail_hasil_stik_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_hasil_stik_id_produksi_stik_foreign` FOREIGN KEY (`id_produksi_stik`) REFERENCES `produksi_stik` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_hasil_stik_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_hasils`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_hasils` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `no_palet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `id_produksi_dryer` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_hasil_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_hasil_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `detail_hasil_id_produksi_dryer_foreign` (`id_produksi_dryer`),
  CONSTRAINT `detail_hasil_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_hasil_id_produksi_dryer_foreign` FOREIGN KEY (`id_produksi_dryer`) REFERENCES `produksi_press_dryers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_hasil_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_kayu_masuks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_kayu_masuks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_kayu_masuk` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `id_lahan` bigint unsigned DEFAULT NULL,
  `diameter` int NOT NULL,
  `panjang` int NOT NULL,
  `grade` int NOT NULL,
  `jumlah_batang` int NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_kayu_masuks_id_kayu_masuk_foreign` (`id_kayu_masuk`),
  KEY `detail_kayu_masuks_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `detail_kayu_masuks_id_lahan_foreign` (`id_lahan`),
  KEY `detail_kayu_masuks_created_by_foreign` (`created_by`),
  KEY `detail_kayu_masuks_updated_by_foreign` (`updated_by`),
  CONSTRAINT `detail_kayu_masuks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_kayu_masuks_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_kayu_masuks_id_kayu_masuk_foreign` FOREIGN KEY (`id_kayu_masuk`) REFERENCES `kayu_masuks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_kayu_masuks_id_lahan_foreign` FOREIGN KEY (`id_lahan`) REFERENCES `lahans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_kayu_masuks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_komposisi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_komposisi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_komposisi` bigint unsigned DEFAULT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `lapisan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_komposisi_id_komposisi_foreign` (`id_komposisi`),
  KEY `detail_komposisi_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `detail_komposisi_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_komposisi_id_komposisi_foreign` FOREIGN KEY (`id_komposisi`) REFERENCES `komposisi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_lain_lains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_lain_lains` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_masuk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_masuk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `no_palet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `id_produksi_dryer` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_masuk_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_masuk_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `detail_masuk_id_produksi_dryer_foreign` (`id_produksi_dryer`),
  CONSTRAINT `detail_masuk_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_masuk_id_produksi_dryer_foreign` FOREIGN KEY (`id_produksi_dryer`) REFERENCES `produksi_press_dryers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_masuk_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_masuk_kedi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_masuk_kedi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `no_palet` int NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` int NOT NULL,
  `jumlah` int NOT NULL,
  `rencana_bongkar` date NOT NULL,
  `id_produksi_kedi` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_masuk_kedi_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_masuk_kedi_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `detail_masuk_kedi_id_produksi_kedi_foreign` (`id_produksi_kedi`),
  CONSTRAINT `detail_masuk_kedi_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_masuk_kedi_id_produksi_kedi_foreign` FOREIGN KEY (`id_produksi_kedi`) REFERENCES `produksi_kedi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_masuk_kedi_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_masuk_stik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_masuk_stik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `no_palet` int NOT NULL,
  `kw` int NOT NULL,
  `isi` int NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `id_produksi_stik` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_masuk_stik_id_ukuran_foreign` (`id_ukuran`),
  KEY `detail_masuk_stik_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `detail_masuk_stik_id_produksi_stik_foreign` (`id_produksi_stik`),
  CONSTRAINT `detail_masuk_stik_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_masuk_stik_id_produksi_stik_foreign` FOREIGN KEY (`id_produksi_stik`) REFERENCES `produksi_stik` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_masuk_stik_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_mesin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_mesin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_mesin_dryer` bigint unsigned DEFAULT NULL,
  `jam_kerja_mesin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_produksi_dryer` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_mesin_id_produksi_dryer_foreign` (`id_produksi_dryer`),
  CONSTRAINT `detail_mesin_id_produksi_dryer_foreign` FOREIGN KEY (`id_produksi_dryer`) REFERENCES `produksi_press_dryers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_mesins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_mesins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_mesin_dryer` bigint unsigned NOT NULL,
  `jam_kerja_mesin` int NOT NULL DEFAULT '12',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_produksi_dryer` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_mesins_id_produksi_dryer_foreign` (`id_produksi_dryer`),
  CONSTRAINT `detail_mesins_id_produksi_dryer_foreign` FOREIGN KEY (`id_produksi_dryer`) REFERENCES `produksi_press_dryers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_nota_barang_keluar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_nota_barang_keluar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_nota_bk` bigint unsigned NOT NULL,
  `nama_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `satuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_nota_barang_keluar_id_nota_bk_foreign` (`id_nota_bk`),
  CONSTRAINT `detail_nota_barang_keluar_id_nota_bk_foreign` FOREIGN KEY (`id_nota_bk`) REFERENCES `nota_barang_keluar` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_nota_barang_masuks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_nota_barang_masuks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_nota_bm` bigint unsigned NOT NULL,
  `nama_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `satuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_nota_barang_masuks_id_nota_bm_foreign` (`id_nota_bm`),
  CONSTRAINT `detail_nota_barang_masuks_id_nota_bm_foreign` FOREIGN KEY (`id_nota_bm`) REFERENCES `nota_barang_masuks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_pegawai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pegawai` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_pegawai` bigint unsigned NOT NULL,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `masuk` time DEFAULT NULL,
  `pulang` time DEFAULT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_produksi_dryer` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_pegawai_id_produksi_dryer_foreign` (`id_produksi_dryer`),
  CONSTRAINT `detail_pegawai_id_produksi_dryer_foreign` FOREIGN KEY (`id_produksi_dryer`) REFERENCES `produksi_press_dryers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_pegawai_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pegawai_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_hp` bigint unsigned NOT NULL,
  `id_mesin` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_pegawai_hp_id_produksi_hp_foreign` (`id_produksi_hp`),
  KEY `detail_pegawai_hp_id_mesin_foreign` (`id_mesin`),
  KEY `detail_pegawai_hp_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `detail_pegawai_hp_id_mesin_foreign` FOREIGN KEY (`id_mesin`) REFERENCES `mesins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_pegawai_hp_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_pegawai_hp_id_produksi_hp_foreign` FOREIGN KEY (`id_produksi_hp`) REFERENCES `produksi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_pegawai_kedi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pegawai_kedi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_kedi` bigint unsigned NOT NULL,
  `id_mesin` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_pegawai_kedi_id_produksi_kedi_foreign` (`id_produksi_kedi`),
  KEY `detail_pegawai_kedi_id_mesin_foreign` (`id_mesin`),
  KEY `detail_pegawai_kedi_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `detail_pegawai_kedi_id_mesin_foreign` FOREIGN KEY (`id_mesin`) REFERENCES `mesins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_pegawai_kedi_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_pegawai_kedi_id_produksi_kedi_foreign` FOREIGN KEY (`id_produksi_kedi`) REFERENCES `produksi_kedi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_pegawai_stik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pegawai_stik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_produksi_stik` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_pegawai_stik_id_produksi_stik_foreign` (`id_produksi_stik`),
  KEY `detail_pegawai_stik_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `detail_pegawai_stik_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_pegawai_stik_id_produksi_stik_foreign` FOREIGN KEY (`id_produksi_stik`) REFERENCES `produksi_stik` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_pegawais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pegawais` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_produksi_dryer` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_pegawais_id_produksi_dryer_foreign` (`id_produksi_dryer`),
  KEY `detail_pegawais_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `detail_pegawais_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_pegawais_id_produksi_dryer_foreign` FOREIGN KEY (`id_produksi_dryer`) REFERENCES `produksi_press_dryers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_turun_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_turun_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_turun_kayu` bigint unsigned DEFAULT NULL,
  `id_kayu_masuk` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `nama_supir` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_kayu` int NOT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_turun_kayus_id_turun_kayu_foreign` (`id_turun_kayu`),
  KEY `detail_turun_kayus_id_kayu_masuk_foreign` (`id_kayu_masuk`),
  CONSTRAINT `detail_turun_kayus_id_kayu_masuk_foreign` FOREIGN KEY (`id_kayu_masuk`) REFERENCES `kayu_masuks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_turun_kayus_id_turun_kayu_foreign` FOREIGN KEY (`id_turun_kayu`) REFERENCES `turun_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_turusan_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_turusan_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_kayu_masuk` bigint unsigned DEFAULT NULL,
  `nomer_urut` int NOT NULL,
  `lahan_id` bigint unsigned DEFAULT NULL,
  `jenis_kayu_id` bigint unsigned DEFAULT NULL,
  `panjang` int NOT NULL,
  `grade` int NOT NULL,
  `diameter` int NOT NULL,
  `kuantitas` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_turusan_kayus_id_kayu_masuk_foreign` (`id_kayu_masuk`),
  KEY `detail_turusan_kayus_lahan_id_foreign` (`lahan_id`),
  KEY `detail_turusan_kayus_jenis_kayu_id_foreign` (`jenis_kayu_id`),
  KEY `detail_turusan_kayus_created_by_foreign` (`created_by`),
  KEY `detail_turusan_kayus_updated_by_foreign` (`updated_by`),
  CONSTRAINT `detail_turusan_kayus_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_turusan_kayus_id_kayu_masuk_foreign` FOREIGN KEY (`id_kayu_masuk`) REFERENCES `kayu_masuks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_turusan_kayus_jenis_kayu_id_foreign` FOREIGN KEY (`jenis_kayu_id`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_turusan_kayus_lahan_id_foreign` FOREIGN KEY (`lahan_id`) REFERENCES `lahans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `detail_turusan_kayus_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dokumen_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dokumen_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_legal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `upload_ktp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dokumen_legal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upload_dokumen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_dokumen_legal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_lokasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_tempat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_lengkap` text COLLATE utf8mb4_unicode_ci,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ganti_pisau_rotaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ganti_pisau_rotaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi` bigint unsigned NOT NULL,
  `jam_mulai_ganti_pisau` time NOT NULL,
  `jam_selesai_ganti` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ganti_pisau_rotaries_id_produksi_foreign` (`id_produksi`),
  CONSTRAINT `ganti_pisau_rotaries_id_produksi_foreign` FOREIGN KEY (`id_produksi`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grade_barang_ujis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grade_barang_ujis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_grade` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_kategori_barang` bigint unsigned DEFAULT NULL,
  `nama_grade` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grades_id_kategori_barang_foreign` (`id_kategori_barang`),
  CONSTRAINT `grades_id_kategori_barang_foreign` FOREIGN KEY (`id_kategori_barang`) REFERENCES `kategori_barang` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `graji_stiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `graji_stiks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `harga_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `harga_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `panjang` int NOT NULL DEFAULT '0',
  `diameter_terkecil` decimal(10,2) DEFAULT NULL,
  `diameter_terbesar` decimal(10,2) DEFAULT NULL,
  `grade` int NOT NULL,
  `harga_beli` int NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `harga_kayus_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `harga_kayus_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `harga_pegawais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `harga_pegawais` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `harga` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `harga_solasis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `harga_solasis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `harga` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hari_libur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hari_libur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('national','cuti_bersama','religion','company','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'national',
  `is_repeat_yearly` tinyint(1) NOT NULL DEFAULT '0',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hari_libur_date_index` (`date`),
  KEY `hari_libur_is_repeat_yearly_index` (`is_repeat_yearly`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_graji_balken`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_graji_balken` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_graji_balken` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `jumlah` int NOT NULL,
  `no_palet` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_graji_balken_id_produksi_graji_balken_foreign` (`id_produksi_graji_balken`),
  KEY `hasil_graji_balken_id_ukuran_foreign` (`id_ukuran`),
  KEY `hasil_graji_balken_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `hasil_graji_balken_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_graji_balken_id_produksi_graji_balken_foreign` FOREIGN KEY (`id_produksi_graji_balken`) REFERENCES `produksi_graji_balken` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_graji_balken_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_graji_stiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_graji_stiks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_graji_stiks` bigint unsigned NOT NULL,
  `id_modal_graji_stiks` bigint unsigned NOT NULL,
  `hasil_graji` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_graji_stiks_id_graji_stiks_foreign` (`id_graji_stiks`),
  KEY `hasil_graji_stiks_id_modal_graji_stiks_foreign` (`id_modal_graji_stiks`),
  CONSTRAINT `hasil_graji_stiks_id_graji_stiks_foreign` FOREIGN KEY (`id_graji_stiks`) REFERENCES `graji_stiks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_graji_stiks_id_modal_graji_stiks_foreign` FOREIGN KEY (`id_modal_graji_stiks`) REFERENCES `modal_graji_stiks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_graji_triplek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_graji_triplek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_graji_triplek` bigint unsigned NOT NULL,
  `no_palet` int NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `isi` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_graji_triplek_id_produksi_graji_triplek_foreign` (`id_produksi_graji_triplek`),
  KEY `hasil_graji_triplek_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `hasil_graji_triplek_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_graji_triplek_id_produksi_graji_triplek_foreign` FOREIGN KEY (`id_produksi_graji_triplek`) REFERENCES `produksi_graji_triplek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_guellotine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_guellotine` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_guellotine` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `jumlah` int NOT NULL,
  `no_palet` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_guellotine_id_produksi_guellotine_foreign` (`id_produksi_guellotine`),
  KEY `hasil_guellotine_id_ukuran_foreign` (`id_ukuran`),
  KEY `hasil_guellotine_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `hasil_guellotine_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_guellotine_id_produksi_guellotine_foreign` FOREIGN KEY (`id_produksi_guellotine`) REFERENCES `produksi_guellotine` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_guellotine_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_guellotine_pegawai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_guellotine_pegawai` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_hasil_guellotine` bigint unsigned NOT NULL,
  `id_pegawai_guellotine` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_guellotine_pegawai_id_hasil_guellotine_foreign` (`id_hasil_guellotine`),
  KEY `hasil_guellotine_pegawai_id_pegawai_guellotine_foreign` (`id_pegawai_guellotine`),
  CONSTRAINT `hasil_guellotine_pegawai_id_hasil_guellotine_foreign` FOREIGN KEY (`id_hasil_guellotine`) REFERENCES `hasil_guellotine` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_guellotine_pegawai_id_pegawai_guellotine_foreign` FOREIGN KEY (`id_pegawai_guellotine`) REFERENCES `pegawai_guellotine` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_joint` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_palet` int NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_joint_id_produksi_joint_foreign` (`id_produksi_joint`),
  KEY `hasil_joint_id_ukuran_foreign` (`id_ukuran`),
  KEY `hasil_joint_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `hasil_joint_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_joint_id_produksi_joint_foreign` FOREIGN KEY (`id_produksi_joint`) REFERENCES `produksi_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_joint_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_pilih_plywood`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_pilih_plywood` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_plywood` bigint unsigned NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `jenis_cacat` enum('mengelupas','pecah','delaminasi/melembung','kropos','dll') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `jumlah_bagus` int NOT NULL,
  `kondisi` enum('reject','reparasi','selesai') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_pilih_plywood_id_produksi_pilih_plywood_foreign` (`id_produksi_pilih_plywood`),
  KEY `hasil_pilih_plywood_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `hasil_pilih_plywood_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_pilih_plywood_id_produksi_pilih_plywood_foreign` FOREIGN KEY (`id_produksi_pilih_plywood`) REFERENCES `produksi_pilih_plywood` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_pilih_plywood_pegawai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_pilih_plywood_pegawai` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_hasil_pilih_plywood` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_pilih_plywood_pegawai_id_hasil_pilih_plywood_foreign` (`id_hasil_pilih_plywood`),
  KEY `hasil_pilih_plywood_pegawai_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `hasil_pilih_plywood_pegawai_id_hasil_pilih_plywood_foreign` FOREIGN KEY (`id_hasil_pilih_plywood`) REFERENCES `hasil_pilih_plywood` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `hasil_pilih_plywood_pegawai_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_pilih_veneer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_pilih_veneer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_veneer` bigint unsigned NOT NULL,
  `id_modal_pilih_veneer` bigint unsigned NOT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_palet` int NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_pilih_veneer_id_produksi_pilih_veneer_foreign` (`id_produksi_pilih_veneer`),
  KEY `hasil_pilih_veneer_id_modal_pilih_veneer_foreign` (`id_modal_pilih_veneer`),
  CONSTRAINT `hasil_pilih_veneer_id_modal_pilih_veneer_foreign` FOREIGN KEY (`id_modal_pilih_veneer`) REFERENCES `modal_pilih_veneer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_pilih_veneer_id_produksi_pilih_veneer_foreign` FOREIGN KEY (`id_produksi_pilih_veneer`) REFERENCES `produksi_pilih_veneer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_pilih_veneer_pegawai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_pilih_veneer_pegawai` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_hasil_pilih_veneer` bigint unsigned NOT NULL,
  `id_pegawai_pilih_veneer` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_pilih_veneer_pegawai_id_hasil_pilih_veneer_foreign` (`id_hasil_pilih_veneer`),
  KEY `hasil_pilih_veneer_pegawai_id_pegawai_pilih_veneer_foreign` (`id_pegawai_pilih_veneer`),
  CONSTRAINT `hasil_pilih_veneer_pegawai_id_hasil_pilih_veneer_foreign` FOREIGN KEY (`id_hasil_pilih_veneer`) REFERENCES `hasil_pilih_veneer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hasil_pilih_veneer_pegawai_id_pegawai_pilih_veneer_foreign` FOREIGN KEY (`id_pegawai_pilih_veneer`) REFERENCES `pegawai_pilih_veneer` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_pot_af_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_pot_af_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_af_joint` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_palet` int NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_pot_af_joint_id_produksi_pot_af_joint_foreign` (`id_produksi_pot_af_joint`),
  KEY `hasil_pot_af_joint_id_ukuran_foreign` (`id_ukuran`),
  KEY `hasil_pot_af_joint_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `hasil_pot_af_joint_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_pot_af_joint_id_produksi_pot_af_joint_foreign` FOREIGN KEY (`id_produksi_pot_af_joint`) REFERENCES `produksi_pot_af_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_pot_af_joint_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_repairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_repair` bigint unsigned NOT NULL,
  `id_rencana_repair` bigint unsigned NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_repairs_id_produksi_repair_foreign` (`id_produksi_repair`),
  KEY `hasil_repairs_id_rencana_repair_foreign` (`id_rencana_repair`),
  CONSTRAINT `hasil_repairs_id_produksi_repair_foreign` FOREIGN KEY (`id_produksi_repair`) REFERENCES `produksi_repairs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_repairs_id_rencana_repair_foreign` FOREIGN KEY (`id_rencana_repair`) REFERENCES `rencana_repairs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_sanding_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_sanding_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_sanding_joint` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_palet` int NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_sanding_joint_id_produksi_sanding_joint_foreign` (`id_produksi_sanding_joint`),
  KEY `hasil_sanding_joint_id_ukuran_foreign` (`id_ukuran`),
  KEY `hasil_sanding_joint_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `hasil_sanding_joint_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_sanding_joint_id_produksi_sanding_joint_foreign` FOREIGN KEY (`id_produksi_sanding_joint`) REFERENCES `produksi_sanding_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_sanding_joint_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_sandings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_sandings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_sanding` bigint unsigned DEFAULT NULL,
  `id_barang_setengah_jadi` bigint unsigned DEFAULT NULL,
  `kuantitas` int NOT NULL,
  `jumlah_sanding_face` int NOT NULL,
  `jumlah_sanding_back` int NOT NULL,
  `no_palet` int DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_mesin` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_sandings_id_produksi_sanding_foreign` (`id_produksi_sanding`),
  KEY `hasil_sandings_id_barang_setengah_jadi_foreign` (`id_barang_setengah_jadi`),
  KEY `hasil_sandings_id_mesin_foreign` (`id_mesin`),
  CONSTRAINT `hasil_sandings_id_barang_setengah_jadi_foreign` FOREIGN KEY (`id_barang_setengah_jadi`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_sandings_id_mesin_foreign` FOREIGN KEY (`id_mesin`) REFERENCES `mesins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `hasil_sandings_id_produksi_sanding_foreign` FOREIGN KEY (`id_produksi_sanding`) REFERENCES `produksi_sandings` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `induk_akuns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `induk_akuns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_induk_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_induk_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `induk_akuns_kode_induk_akun_unique` (`kode_induk_akun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jabatan_perusahaan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jabatan_perusahaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `perusahaan_id` bigint unsigned NOT NULL,
  `nama_jabatan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `istirahat_mulai` time DEFAULT NULL,
  `istirahat_selesai` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jabatan_perusahaan_perusahaan_id_foreign` (`perusahaan_id`),
  CONSTRAINT `jabatan_perusahaan_perusahaan_id_foreign` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_barang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_barang` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_jenis_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_jenis_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_barang_ujis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_barang_ujis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_jenis_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_jenis_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_kayu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_kayu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jurnal1sts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jurnal1sts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tgl` date DEFAULT NULL,
  `jurnal` int DEFAULT NULL,
  `no_akun` int NOT NULL,
  `no-dokumen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mm` int DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `map` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hit_kbk` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banyak` int DEFAULT NULL,
  `m3` decimal(10,4) DEFAULT NULL,
  `harga` decimal(15,2) DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jurnal1sts_no_akun_index` (`no_akun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jurnal2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jurnal2` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `modif100` int NOT NULL,
  `no_akun` int NOT NULL,
  `nama_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banyak` int DEFAULT NULL,
  `kubikasi` decimal(8,2) DEFAULT NULL,
  `harga` int DEFAULT NULL,
  `total` int DEFAULT NULL,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_sinkron` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum sinkron',
  `synced_at` datetime DEFAULT NULL,
  `synced_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jurnal_1st`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jurnal_1st` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `modif10` int NOT NULL,
  `no_akun` int NOT NULL,
  `nama_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bagian` enum('d','k') COLLATE utf8mb4_unicode_ci NOT NULL,
  `banyak` int DEFAULT NULL,
  `m3` decimal(12,4) DEFAULT NULL,
  `harga` int DEFAULT NULL,
  `total` int DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jurnal_tigas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jurnal_tigas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `modif1000` int NOT NULL,
  `akun_seratus` int NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banyak` int DEFAULT NULL,
  `kubikasi` decimal(8,2) DEFAULT NULL,
  `harga` int DEFAULT NULL,
  `total` int DEFAULT NULL,
  `createdBy` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum sinkron',
  `synchronized_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `synchronized_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jurnal_umum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jurnal_umum` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_akun` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tgl` date DEFAULT NULL,
  `jurnal` int DEFAULT NULL,
  `no_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no-dokumen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mm` int DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `map` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hit_kbk` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banyak` int DEFAULT NULL,
  `m3` decimal(10,4) DEFAULT NULL,
  `harga` double(20,6) DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum sinkron',
  `synced_at` datetime DEFAULT NULL,
  `synced_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kategori_barang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kategori_barang` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kategori_mesins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kategori_mesins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_kategori_mesin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kayu_compare_temp`;
/*!50001 DROP VIEW IF EXISTS `kayu_compare_temp`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `kayu_compare_temp` AS SELECT 
 1 AS `id`,
 1 AS `id_kayu_masuk`,
 1 AS `id_jenis_kayu`,
 1 AS `id_lahan`,
 1 AS `diameter`,
 1 AS `panjang`,
 1 AS `grade`,
 1 AS `detail_jumlah`,
 1 AS `turusan_jumlah`,
 1 AS `selisih`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `kayu_masuks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kayu_masuks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jenis_dokumen_angkut` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `upload_dokumen_angkut` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tgl_kayu_masuk` datetime NOT NULL,
  `seri` int NOT NULL,
  `id_supplier_kayus` bigint unsigned DEFAULT NULL,
  `id_kendaraan_supplier_kayus` bigint unsigned DEFAULT NULL,
  `id_dokumen_kayus` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kayu_masuks_id_supplier_kayus_foreign` (`id_supplier_kayus`),
  KEY `kayu_masuks_id_kendaraan_supplier_kayus_foreign` (`id_kendaraan_supplier_kayus`),
  KEY `kayu_masuks_id_dokumen_kayus_foreign` (`id_dokumen_kayus`),
  KEY `kayu_masuks_created_by_foreign` (`created_by`),
  KEY `kayu_masuks_updated_by_foreign` (`updated_by`),
  CONSTRAINT `kayu_masuks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kayu_masuks_id_dokumen_kayus_foreign` FOREIGN KEY (`id_dokumen_kayus`) REFERENCES `dokumen_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `kayu_masuks_id_kendaraan_supplier_kayus_foreign` FOREIGN KEY (`id_kendaraan_supplier_kayus`) REFERENCES `kendaraan_supplier_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `kayu_masuks_id_supplier_kayus_foreign` FOREIGN KEY (`id_supplier_kayus`) REFERENCES `supplier_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `kayu_masuks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kayu_pecah_rotaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kayu_pecah_rotaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi` bigint unsigned NOT NULL,
  `id_penggunaan_lahan` bigint unsigned NOT NULL,
  `ukuran` decimal(10,2) NOT NULL,
  `foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kayu_pecah_rotaries_id_produksi_foreign` (`id_produksi`),
  KEY `kayu_pecah_rotaries_id_penggunaan_lahan_foreign` (`id_penggunaan_lahan`),
  CONSTRAINT `kayu_pecah_rotaries_id_penggunaan_lahan_foreign` FOREIGN KEY (`id_penggunaan_lahan`) REFERENCES `penggunaan_lahan_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `kayu_pecah_rotaries_id_produksi_foreign` FOREIGN KEY (`id_produksi`) REFERENCES `produksi_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kendaraan_supplier_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kendaraan_supplier_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_supplier` bigint unsigned DEFAULT NULL,
  `nopol_kendaraan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_kendaraan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pemilik_kendaraan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kendaraan_supplier_kayus_id_supplier_foreign` (`id_supplier`),
  CONSTRAINT `kendaraan_supplier_kayus_id_supplier_foreign` FOREIGN KEY (`id_supplier`) REFERENCES `supplier_kayus` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ketegori_barang_uji`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ketegori_barang_uji` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ketegori_barang_ujis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ketegori_barang_ujis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `komposisi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `komposisi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `komposisi_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `komposisi_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kontrak_kerja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kontrak_kerja` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_kelamin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `karyawan_di` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_perusahaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempat_tanggal_lahir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `no_telepon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontrak_mulai` date DEFAULT NULL,
  `kontrak_selesai` date DEFAULT NULL,
  `durasi_kontrak` int DEFAULT NULL,
  `tanggal_kontrak` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_kontrak` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_dokumen` enum('draft','dicetak','ditandatangani') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `bukti_ttd` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dibuat_oleh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `divalidasi_oleh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_kontrak` enum('active','soon','expired','extended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lahans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lahans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_lahan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_lahan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lain_lain`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lain_lain` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hasil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lain_lain_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `lain_lain_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `list_pekerjaan_menumpuk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `list_pekerjaan_menumpuk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_hasil_pilih_plywood` bigint unsigned NOT NULL,
  `jumlah_asal` int NOT NULL,
  `jumlah_selesai` int NOT NULL DEFAULT '0',
  `jumlah_belum_selesai` int NOT NULL,
  `status` enum('selesai','belum selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum selesai',
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `list_pekerjaan_menumpuk_id_hasil_pilih_plywood_foreign` (`id_hasil_pilih_plywood`),
  CONSTRAINT `list_pekerjaan_menumpuk_id_hasil_pilih_plywood_foreign` FOREIGN KEY (`id_hasil_pilih_plywood`) REFERENCES `hasil_pilih_plywood` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `masuk_graji_triplek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `masuk_graji_triplek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_graji_triplek` bigint unsigned NOT NULL,
  `no_palet` int NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `isi` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `masuk_graji_triplek_id_produksi_graji_triplek_foreign` (`id_produksi_graji_triplek`),
  KEY `masuk_graji_triplek_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `masuk_graji_triplek_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `masuk_graji_triplek_id_produksi_graji_triplek_foreign` FOREIGN KEY (`id_produksi_graji_triplek`) REFERENCES `produksi_graji_triplek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mesins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mesins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kategori_mesin_id` bigint unsigned NOT NULL,
  `nama_mesin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ongkos_mesin` decimal(15,2) NOT NULL,
  `no_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail_mesin` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mesins_kategori_mesin_id_foreign` (`kategori_mesin_id`),
  CONSTRAINT `mesins_kategori_mesin_id_foreign` FOREIGN KEY (`kategori_mesin_id`) REFERENCES `kategori_mesins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modal_graji_stiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modal_graji_stiks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_graji_stiks` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `jumlah_bahan` int NOT NULL,
  `nomor_palet` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modal_graji_stiks_id_graji_stiks_foreign` (`id_graji_stiks`),
  KEY `modal_graji_stiks_id_ukuran_foreign` (`id_ukuran`),
  CONSTRAINT `modal_graji_stiks_id_graji_stiks_foreign` FOREIGN KEY (`id_graji_stiks`) REFERENCES `graji_stiks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_graji_stiks_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modal_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modal_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_joint` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_palet` int NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modal_joint_id_produksi_joint_foreign` (`id_produksi_joint`),
  KEY `modal_joint_id_ukuran_foreign` (`id_ukuran`),
  KEY `modal_joint_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `modal_joint_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_joint_id_produksi_joint_foreign` FOREIGN KEY (`id_produksi_joint`) REFERENCES `produksi_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_joint_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modal_pilih_veneer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modal_pilih_veneer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_veneer` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned DEFAULT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_palet` int NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modal_pilih_veneer_id_produksi_pilih_veneer_foreign` (`id_produksi_pilih_veneer`),
  KEY `modal_pilih_veneer_id_ukuran_foreign` (`id_ukuran`),
  KEY `modal_pilih_veneer_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `modal_pilih_veneer_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_pilih_veneer_id_produksi_pilih_veneer_foreign` FOREIGN KEY (`id_produksi_pilih_veneer`) REFERENCES `produksi_pilih_veneer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_pilih_veneer_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modal_repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modal_repairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_repair` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `jumlah` int NOT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomor_palet` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modal_repairs_id_produksi_repair_foreign` (`id_produksi_repair`),
  KEY `modal_repairs_id_ukuran_foreign` (`id_ukuran`),
  KEY `modal_repairs_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `modal_repairs_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_repairs_id_produksi_repair_foreign` FOREIGN KEY (`id_produksi_repair`) REFERENCES `produksi_repairs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_repairs_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modal_sandings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modal_sandings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_sanding` bigint unsigned DEFAULT NULL,
  `id_barang_setengah_jadi` bigint unsigned DEFAULT NULL,
  `kuantitas` int NOT NULL,
  `jumlah_sanding_face` int NOT NULL,
  `jumlah_sanding_back` int NOT NULL,
  `no_palet` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modal_sandings_id_produksi_sanding_foreign` (`id_produksi_sanding`),
  KEY `modal_sandings_id_barang_setengah_jadi_foreign` (`id_barang_setengah_jadi`),
  CONSTRAINT `modal_sandings_id_barang_setengah_jadi_foreign` FOREIGN KEY (`id_barang_setengah_jadi`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `modal_sandings_id_produksi_sanding_foreign` FOREIGN KEY (`id_produksi_sanding`) REFERENCES `produksi_sandings` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `neracas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `neracas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `akun_seribu` int NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banyak` int DEFAULT NULL,
  `kubikasi` decimal(8,2) DEFAULT NULL,
  `harga` int DEFAULT NULL,
  `total` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nota_barang_keluar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota_barang_keluar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `no_nota` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tujuan_nota` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dibuat_oleh` bigint unsigned DEFAULT NULL,
  `divalidasi_oleh` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nota_barang_keluar_dibuat_oleh_foreign` (`dibuat_oleh`),
  KEY `nota_barang_keluar_divalidasi_oleh_foreign` (`divalidasi_oleh`),
  CONSTRAINT `nota_barang_keluar_dibuat_oleh_foreign` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `nota_barang_keluar_divalidasi_oleh_foreign` FOREIGN KEY (`divalidasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nota_barang_masuks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota_barang_masuks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `no_nota` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tujuan_nota` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dibuat_oleh` bigint unsigned DEFAULT NULL,
  `divalidasi_oleh` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nota_barang_masuks_dibuat_oleh_foreign` (`dibuat_oleh`),
  KEY `nota_barang_masuks_divalidasi_oleh_foreign` (`divalidasi_oleh`),
  CONSTRAINT `nota_barang_masuks_dibuat_oleh_foreign` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `nota_barang_masuks_divalidasi_oleh_foreign` FOREIGN KEY (`divalidasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nota_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_kayu_masuk` bigint unsigned DEFAULT NULL,
  `no_nota` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `penanggung_jawab` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `penerima` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `satpam` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Belum Diperiksa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nota_kayus_id_kayu_masuk_foreign` (`id_kayu_masuk`),
  CONSTRAINT `nota_kayus_id_kayu_masuk_foreign` FOREIGN KEY (`id_kayu_masuk`) REFERENCES `kayu_masuks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_graji_balken`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_graji_balken` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_graji_balken` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_graji_balken_id_produksi_graji_balken_foreign` (`id_produksi_graji_balken`),
  KEY `pegawai_graji_balken_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_graji_balken_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_graji_balken_id_produksi_graji_balken_foreign` FOREIGN KEY (`id_produksi_graji_balken`) REFERENCES `produksi_graji_balken` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_graji_stiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_graji_stiks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_graji_stiks` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_graji_stiks_id_graji_stiks_foreign` (`id_graji_stiks`),
  KEY `pegawai_graji_stiks_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_graji_stiks_id_graji_stiks_foreign` FOREIGN KEY (`id_graji_stiks`) REFERENCES `graji_stiks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_graji_stiks_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_graji_triplek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_graji_triplek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_graji_triplek` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_graji_triplek_id_produksi_graji_triplek_foreign` (`id_produksi_graji_triplek`),
  KEY `pegawai_graji_triplek_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_graji_triplek_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_graji_triplek_id_produksi_graji_triplek_foreign` FOREIGN KEY (`id_produksi_graji_triplek`) REFERENCES `produksi_graji_triplek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_guellotine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_guellotine` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_guellotine` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_guellotine_id_produksi_guellotine_foreign` (`id_produksi_guellotine`),
  KEY `pegawai_guellotine_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_guellotine_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_guellotine_id_produksi_guellotine_foreign` FOREIGN KEY (`id_produksi_guellotine`) REFERENCES `produksi_guellotine` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_joint` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_joint_id_produksi_joint_foreign` (`id_produksi_joint`),
  KEY `pegawai_joint_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_joint_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_joint_id_produksi_joint_foreign` FOREIGN KEY (`id_produksi_joint`) REFERENCES `produksi_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_nyusup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_nyusup` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_nyusup` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_nyusup_id_produksi_nyusup_foreign` (`id_produksi_nyusup`),
  KEY `pegawai_nyusup_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_nyusup_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_nyusup_id_produksi_nyusup_foreign` FOREIGN KEY (`id_produksi_nyusup`) REFERENCES `produksi_nyusup` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_pilih_plywood`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_pilih_plywood` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_plywood` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_pilih_plywood_id_produksi_pilih_plywood_foreign` (`id_produksi_pilih_plywood`),
  KEY `pegawai_pilih_plywood_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_pilih_plywood_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_pilih_plywood_id_produksi_pilih_plywood_foreign` FOREIGN KEY (`id_produksi_pilih_plywood`) REFERENCES `produksi_pilih_plywood` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_pilih_veneer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_pilih_veneer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_veneer` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_pilih_veneer_id_produksi_pilih_veneer_foreign` (`id_produksi_pilih_veneer`),
  KEY `pegawai_pilih_veneer_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_pilih_veneer_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_pilih_veneer_id_produksi_pilih_veneer_foreign` FOREIGN KEY (`id_produksi_pilih_veneer`) REFERENCES `produksi_pilih_veneer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_pot_af_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_pot_af_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_af_joint` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_pot_af_joint_id_produksi_pot_af_joint_foreign` (`id_produksi_pot_af_joint`),
  KEY `pegawai_pot_af_joint_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_pot_af_joint_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_pot_af_joint_id_produksi_pot_af_joint_foreign` FOREIGN KEY (`id_produksi_pot_af_joint`) REFERENCES `produksi_pot_af_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_pot_jelek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_pot_jelek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_jelek` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_pot_jelek_id_produksi_pot_jelek_foreign` (`id_produksi_pot_jelek`),
  KEY `pegawai_pot_jelek_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_pot_jelek_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_pot_jelek_id_produksi_pot_jelek_foreign` FOREIGN KEY (`id_produksi_pot_jelek`) REFERENCES `produksi_pot_jelek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_pot_siku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_pot_siku` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_siku` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_pot_siku_id_produksi_pot_siku_foreign` (`id_produksi_pot_siku`),
  KEY `pegawai_pot_siku_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_pot_siku_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_pot_siku_id_produksi_pot_siku_foreign` FOREIGN KEY (`id_produksi_pot_siku`) REFERENCES `produksi_pot_siku` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_rotaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_rotaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `izin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_rotaries_id_produksi_foreign` (`id_produksi`),
  KEY `pegawai_rotaries_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_rotaries_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_rotaries_id_produksi_foreign` FOREIGN KEY (`id_produksi`) REFERENCES `produksi_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_sanding_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_sanding_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_sanding_joint` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `tugas` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_sanding_joint_id_produksi_sanding_joint_foreign` (`id_produksi_sanding_joint`),
  KEY `pegawai_sanding_joint_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_sanding_joint_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_sanding_joint_id_produksi_sanding_joint_foreign` FOREIGN KEY (`id_produksi_sanding_joint`) REFERENCES `produksi_sanding_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_sandings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_sandings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tugas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `masuk` time NOT NULL,
  `pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_produksi_sanding` bigint unsigned DEFAULT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_sandings_id_produksi_sanding_foreign` (`id_produksi_sanding`),
  KEY `pegawai_sandings_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_sandings_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_sandings_id_produksi_sanding_foreign` FOREIGN KEY (`id_produksi_sanding`) REFERENCES `produksi_sandings` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_turun_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_turun_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_turun_kayu` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pegawai_turun_kayus_id_turun_kayu_foreign` (`id_turun_kayu`),
  KEY `pegawai_turun_kayus_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `pegawai_turun_kayus_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `pegawai_turun_kayus_id_turun_kayu_foreign` FOREIGN KEY (`id_turun_kayu`) REFERENCES `turun_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawais` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_pegawai` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_pegawai` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `no_telepon_pegawai` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis_kelamin_pegawai` tinyint(1) NOT NULL DEFAULT '0',
  `tanggal_masuk` date NOT NULL,
  `karyawan_di` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_perusahaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempat_tanggal_lahir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_ktp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_kk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pegawais_kode_pegawai_unique` (`kode_pegawai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `penggunaan_lahan_rotaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `penggunaan_lahan_rotaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_lahan` bigint unsigned NOT NULL,
  `id_produksi` bigint unsigned NOT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `jumlah_batang` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `penggunaan_lahan_rotaries_id_lahan_foreign` (`id_lahan`),
  KEY `penggunaan_lahan_rotaries_id_produksi_foreign` (`id_produksi`),
  KEY `penggunaan_lahan_rotaries_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `penggunaan_lahan_rotaries_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `penggunaan_lahan_rotaries_id_lahan_foreign` FOREIGN KEY (`id_lahan`) REFERENCES `lahans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `penggunaan_lahan_rotaries_id_produksi_foreign` FOREIGN KEY (`id_produksi`) REFERENCES `produksi_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `perusahaan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `perusahaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `telepon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perusahaan_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_bahan_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_bahan_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_hp` bigint unsigned NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `id_detail_komposisi` bigint unsigned DEFAULT NULL,
  `no_palet` int DEFAULT NULL,
  `isi` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `platform_bahan_hp_id_produksi_hp_foreign` (`id_produksi_hp`),
  KEY `platform_bahan_hp_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  KEY `platform_bahan_hp_id_detail_komposisi_foreign` (`id_detail_komposisi`),
  CONSTRAINT `platform_bahan_hp_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `platform_bahan_hp_id_detail_komposisi_foreign` FOREIGN KEY (`id_detail_komposisi`) REFERENCES `detail_komposisi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `platform_bahan_hp_id_produksi_hp_foreign` FOREIGN KEY (`id_produksi_hp`) REFERENCES `produksi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_dempuls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_dempuls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_graji_balken`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_graji_balken` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_graji_triplek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_graji_triplek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `status` enum('graji manual','graji otomatis') COLLATE utf8mb4_unicode_ci NOT NULL,
  `kendala` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_guellotine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_guellotine` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_kedi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_kedi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kode_kedi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kendala` text COLLATE utf8mb4_unicode_ci,
  `status` enum('bongkar','masuk') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_nyusup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_nyusup` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_pilih_plywood`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_pilih_plywood` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_pilih_veneer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_pilih_veneer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_pot_af_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_pot_af_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_pot_jelek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_pot_jelek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_pot_siku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_pot_siku` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_press_dryers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_press_dryers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `shift` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kendala` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_repairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_rotaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_rotaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_mesin` bigint unsigned NOT NULL,
  `tgl_produksi` date NOT NULL,
  `jam_kerja` int NOT NULL DEFAULT '10',
  `kendala` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produksi_rotaries_id_mesin_foreign` (`id_mesin`),
  CONSTRAINT `produksi_rotaries_id_mesin_foreign` FOREIGN KEY (`id_mesin`) REFERENCES `mesins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_sanding_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_sanding_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_sandings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_sandings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `shift` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_mesin` bigint NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produksi_stik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produksi_stik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_produksi` date NOT NULL,
  `kendala` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rencana_kerja_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rencana_kerja_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rencana_kerja_hp_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  CONSTRAINT `rencana_kerja_hp_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rencana_pegawai_dempuls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rencana_pegawai_dempuls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_dempul` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rencana_pegawai_dempuls_id_produksi_dempul_foreign` (`id_produksi_dempul`),
  KEY `rencana_pegawai_dempuls_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `rencana_pegawai_dempuls_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `rencana_pegawai_dempuls_id_produksi_dempul_foreign` FOREIGN KEY (`id_produksi_dempul`) REFERENCES `produksi_dempuls` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rencana_pegawais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rencana_pegawais` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_repair` bigint unsigned NOT NULL,
  `id_pegawai` bigint unsigned NOT NULL,
  `nomor_meja` int NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `ijin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rencana_pegawais_id_produksi_repair_foreign` (`id_produksi_repair`),
  KEY `rencana_pegawais_id_pegawai_foreign` (`id_pegawai`),
  CONSTRAINT `rencana_pegawais_id_pegawai_foreign` FOREIGN KEY (`id_pegawai`) REFERENCES `pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `rencana_pegawais_id_produksi_repair_foreign` FOREIGN KEY (`id_produksi_repair`) REFERENCES `produksi_repairs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rencana_repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rencana_repairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_repair` bigint unsigned NOT NULL,
  `id_rencana_pegawai` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `kw` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_modal_repair` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rencana_repairs_id_produksi_repair_foreign` (`id_produksi_repair`),
  KEY `rencana_repairs_id_rencana_pegawai_foreign` (`id_rencana_pegawai`),
  KEY `rencana_repairs_id_ukuran_foreign` (`id_ukuran`),
  KEY `rencana_repairs_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `rencana_repairs_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `rencana_repairs_id_produksi_repair_foreign` FOREIGN KEY (`id_produksi_repair`) REFERENCES `produksi_repairs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `rencana_repairs_id_rencana_pegawai_foreign` FOREIGN KEY (`id_rencana_pegawai`) REFERENCES `rencana_pegawais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `rencana_repairs_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `repairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `jumlah_meja` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `riwayat_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `riwayat_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_masuk` date NOT NULL,
  `tanggal_digunakan` date NOT NULL,
  `tanggal_habis` date NOT NULL,
  `id_tempat_kayu` bigint unsigned DEFAULT NULL,
  `id_rotary` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `riwayat_kayus_id_tempat_kayu_foreign` (`id_tempat_kayu`),
  KEY `riwayat_kayus_id_rotary_foreign` (`id_rotary`),
  CONSTRAINT `riwayat_kayus_id_rotary_foreign` FOREIGN KEY (`id_rotary`) REFERENCES `produksi_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `riwayat_kayus_id_tempat_kayu_foreign` FOREIGN KEY (`id_tempat_kayu`) REFERENCES `tempat_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scheduled_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scheduled_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_anak_akuns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_anak_akuns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_anak_akun` bigint unsigned NOT NULL,
  `kode_sub_anak_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_sub_anak_akun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sub_anak_akuns_kode_sub_anak_akun_unique` (`kode_sub_anak_akun`),
  KEY `sub_anak_akuns_id_anak_akun_foreign` (`id_anak_akun`),
  CONSTRAINT `sub_anak_akuns_id_anak_akun_foreign` FOREIGN KEY (`id_anak_akun`) REFERENCES `anak_akuns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supplier_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_supplier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_telepon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nik` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upload_ktp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis_kelamin` tinyint(1) NOT NULL DEFAULT '1',
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `jenis_bank` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_rekening` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_supplier` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `targets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_mesin` bigint unsigned NOT NULL,
  `id_ukuran` bigint unsigned NOT NULL,
  `id_jenis_kayu` bigint unsigned NOT NULL,
  `kode_ukuran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` int NOT NULL,
  `orang` int NOT NULL,
  `jam` int NOT NULL,
  `targetperjam` decimal(15,2) GENERATED ALWAYS AS ((`target` / `jam`)) VIRTUAL,
  `targetperorang` decimal(15,2) GENERATED ALWAYS AS ((`target` / `orang`)) VIRTUAL,
  `gaji` decimal(15,2) NOT NULL,
  `potongan` decimal(15,2) GENERATED ALWAYS AS ((`gaji` / `targetperorang`)) VIRTUAL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diajukan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `targets_id_mesin_foreign` (`id_mesin`),
  KEY `targets_id_ukuran_foreign` (`id_ukuran`),
  KEY `targets_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  CONSTRAINT `targets_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `targets_id_mesin_foreign` FOREIGN KEY (`id_mesin`) REFERENCES `mesins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `targets_id_ukuran_foreign` FOREIGN KEY (`id_ukuran`) REFERENCES `ukurans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tempat_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tempat_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jumlah_batang` int NOT NULL,
  `poin` int NOT NULL,
  `id_kayu_masuk` bigint unsigned DEFAULT NULL,
  `id_lahan` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tempat_kayus_id_kayu_masuk_foreign` (`id_kayu_masuk`),
  KEY `tempat_kayus_id_lahan_foreign` (`id_lahan`),
  CONSTRAINT `tempat_kayus_id_kayu_masuk_foreign` FOREIGN KEY (`id_kayu_masuk`) REFERENCES `kayu_masuks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `tempat_kayus_id_lahan_foreign` FOREIGN KEY (`id_lahan`) REFERENCES `lahans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `total_solasis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `total_solasis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `total` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `triplek_hasil_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `triplek_hasil_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_hp` bigint unsigned NOT NULL,
  `id_mesin` bigint unsigned NOT NULL,
  `no_palet` int NOT NULL,
  `id_jenis_kayu` bigint unsigned DEFAULT NULL,
  `id_ukuran_setengah_jadi` bigint unsigned DEFAULT NULL,
  `kw` int NOT NULL,
  `isi` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `triplek_hasil_hp_id_produksi_hp_foreign` (`id_produksi_hp`),
  KEY `triplek_hasil_hp_id_mesin_foreign` (`id_mesin`),
  KEY `triplek_hasil_hp_id_jenis_kayu_foreign` (`id_jenis_kayu`),
  KEY `triplek_hasil_hp_id_ukuran_setengah_jadi_foreign` (`id_ukuran_setengah_jadi`),
  CONSTRAINT `triplek_hasil_hp_id_jenis_kayu_foreign` FOREIGN KEY (`id_jenis_kayu`) REFERENCES `jenis_kayus` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `triplek_hasil_hp_id_mesin_foreign` FOREIGN KEY (`id_mesin`) REFERENCES `mesins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `triplek_hasil_hp_id_produksi_hp_foreign` FOREIGN KEY (`id_produksi_hp`) REFERENCES `produksi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `triplek_hasil_hp_id_ukuran_setengah_jadi_foreign` FOREIGN KEY (`id_ukuran_setengah_jadi`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `turun_kayus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turun_kayus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` datetime NOT NULL,
  `kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ukurans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ukurans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `panjang` decimal(10,2) NOT NULL,
  `lebar` decimal(10,2) NOT NULL,
  `tebal` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ukuran` (`panjang`,`lebar`,`tebal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_dempuls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_dempuls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_dempul` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_dempuls_id_produksi_dempul_foreign` (`id_produksi_dempul`),
  CONSTRAINT `validasi_dempuls_id_produksi_dempul_foreign` FOREIGN KEY (`id_produksi_dempul`) REFERENCES `produksi_dempuls` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_graji_balken`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_graji_balken` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_graji_balken` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_graji_balken_id_produksi_graji_balken_foreign` (`id_produksi_graji_balken`),
  CONSTRAINT `validasi_graji_balken_id_produksi_graji_balken_foreign` FOREIGN KEY (`id_produksi_graji_balken`) REFERENCES `produksi_graji_balken` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_graji_stiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_graji_stiks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_graji_stiks` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_graji_stiks_id_graji_stiks_foreign` (`id_graji_stiks`),
  CONSTRAINT `validasi_graji_stiks_id_graji_stiks_foreign` FOREIGN KEY (`id_graji_stiks`) REFERENCES `graji_stiks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_graji_triplek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_graji_triplek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_graji_triplek` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_graji_triplek_id_produksi_graji_triplek_foreign` (`id_produksi_graji_triplek`),
  CONSTRAINT `validasi_graji_triplek_id_produksi_graji_triplek_foreign` FOREIGN KEY (`id_produksi_graji_triplek`) REFERENCES `produksi_graji_triplek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_guellotine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_guellotine` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_guellotine` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_guellotine_id_produksi_guellotine_foreign` (`id_produksi_guellotine`),
  CONSTRAINT `validasi_guellotine_id_produksi_guellotine_foreign` FOREIGN KEY (`id_produksi_guellotine`) REFERENCES `produksi_guellotine` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_hasil_rotaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_hasil_rotaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_hasil_rotaries_id_produksi_foreign` (`id_produksi`),
  CONSTRAINT `validasi_hasil_rotaries_id_produksi_foreign` FOREIGN KEY (`id_produksi`) REFERENCES `produksi_rotaries` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_joint` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_joint_id_produksi_joint_foreign` (`id_produksi_joint`),
  CONSTRAINT `validasi_joint_id_produksi_joint_foreign` FOREIGN KEY (`id_produksi_joint`) REFERENCES `produksi_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_kedi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_kedi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_kedi` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_kedi_id_produksi_kedi_foreign` (`id_produksi_kedi`),
  CONSTRAINT `validasi_kedi_id_produksi_kedi_foreign` FOREIGN KEY (`id_produksi_kedi`) REFERENCES `produksi_kedi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_nyusup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_nyusup` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_nyusup` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_nyusup_id_produksi_nyusup_foreign` (`id_produksi_nyusup`),
  CONSTRAINT `validasi_nyusup_id_produksi_nyusup_foreign` FOREIGN KEY (`id_produksi_nyusup`) REFERENCES `produksi_nyusup` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_pilih_plywood`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_pilih_plywood` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_plywood` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_pilih_plywood_id_produksi_pilih_plywood_foreign` (`id_produksi_pilih_plywood`),
  CONSTRAINT `validasi_pilih_plywood_id_produksi_pilih_plywood_foreign` FOREIGN KEY (`id_produksi_pilih_plywood`) REFERENCES `produksi_pilih_plywood` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_pilih_veneer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_pilih_veneer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pilih_veneer` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_pilih_veneer_id_produksi_pilih_veneer_foreign` (`id_produksi_pilih_veneer`),
  CONSTRAINT `validasi_pilih_veneer_id_produksi_pilih_veneer_foreign` FOREIGN KEY (`id_produksi_pilih_veneer`) REFERENCES `produksi_pilih_veneer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_pot_af_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_pot_af_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_af_joint` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_pot_af_joint_id_produksi_pot_af_joint_foreign` (`id_produksi_pot_af_joint`),
  CONSTRAINT `validasi_pot_af_joint_id_produksi_pot_af_joint_foreign` FOREIGN KEY (`id_produksi_pot_af_joint`) REFERENCES `produksi_pot_af_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_pot_jelek`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_pot_jelek` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_jelek` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_pot_jelek_id_produksi_pot_jelek_foreign` (`id_produksi_pot_jelek`),
  CONSTRAINT `validasi_pot_jelek_id_produksi_pot_jelek_foreign` FOREIGN KEY (`id_produksi_pot_jelek`) REFERENCES `produksi_pot_jelek` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_pot_siku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_pot_siku` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_pot_siku` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_pot_siku_id_produksi_pot_siku_foreign` (`id_produksi_pot_siku`),
  CONSTRAINT `validasi_pot_siku_id_produksi_pot_siku_foreign` FOREIGN KEY (`id_produksi_pot_siku`) REFERENCES `produksi_pot_siku` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_repairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_repair` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_repairs_id_produksi_repair_foreign` (`id_produksi_repair`),
  CONSTRAINT `validasi_repairs_id_produksi_repair_foreign` FOREIGN KEY (`id_produksi_repair`) REFERENCES `produksi_repairs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_sanding_joint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_sanding_joint` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_sanding_joint` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_sanding_joint_id_produksi_sanding_joint_foreign` (`id_produksi_sanding_joint`),
  CONSTRAINT `validasi_sanding_joint_id_produksi_sanding_joint_foreign` FOREIGN KEY (`id_produksi_sanding_joint`) REFERENCES `produksi_sanding_joint` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_sandings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_sandings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_sanding` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_sandings_id_produksi_sanding_foreign` (`id_produksi_sanding`),
  CONSTRAINT `validasi_sandings_id_produksi_sanding_foreign` FOREIGN KEY (`id_produksi_sanding`) REFERENCES `produksi_sandings` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasi_stik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_stik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_stik` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_stik_id_produksi_stik_foreign` (`id_produksi_stik`),
  CONSTRAINT `validasi_stik_id_produksi_stik_foreign` FOREIGN KEY (`id_produksi_stik`) REFERENCES `produksi_stik` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `validasis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_dryer` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasis_id_produksi_dryer_foreign` (`id_produksi_dryer`),
  CONSTRAINT `validasis_id_produksi_dryer_foreign` FOREIGN KEY (`id_produksi_dryer`) REFERENCES `produksi_press_dryers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `veneer_bahan_hp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `veneer_bahan_hp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_produksi_hp` bigint unsigned NOT NULL,
  `id_barang_setengah_jadi_hp` bigint unsigned DEFAULT NULL,
  `id_detail_komposisi` bigint unsigned DEFAULT NULL,
  `no_palet` int DEFAULT NULL,
  `isi` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `veneer_bahan_hp_id_produksi_hp_foreign` (`id_produksi_hp`),
  KEY `veneer_bahan_hp_id_barang_setengah_jadi_hp_foreign` (`id_barang_setengah_jadi_hp`),
  KEY `veneer_bahan_hp_id_detail_komposisi_foreign` (`id_detail_komposisi`),
  CONSTRAINT `veneer_bahan_hp_id_barang_setengah_jadi_hp_foreign` FOREIGN KEY (`id_barang_setengah_jadi_hp`) REFERENCES `barang_setengah_jadi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `veneer_bahan_hp_id_detail_komposisi_foreign` FOREIGN KEY (`id_detail_komposisi`) REFERENCES `detail_komposisi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `veneer_bahan_hp_id_produksi_hp_foreign` FOREIGN KEY (`id_produksi_hp`) REFERENCES `produksi_hp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `kayu_compare_temp`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `kayu_compare_temp` AS with `detail` as (select `detail_kayu_masuks`.`id_kayu_masuk` AS `id_kayu_masuk`,`detail_kayu_masuks`.`id_jenis_kayu` AS `id_jenis_kayu`,`detail_kayu_masuks`.`id_lahan` AS `id_lahan`,`detail_kayu_masuks`.`diameter` AS `diameter`,`detail_kayu_masuks`.`panjang` AS `panjang`,`detail_kayu_masuks`.`grade` AS `grade`,sum(`detail_kayu_masuks`.`jumlah_batang`) AS `detail_jumlah` from `detail_kayu_masuks` group by `detail_kayu_masuks`.`id_kayu_masuk`,`detail_kayu_masuks`.`id_jenis_kayu`,`detail_kayu_masuks`.`id_lahan`,`detail_kayu_masuks`.`diameter`,`detail_kayu_masuks`.`panjang`,`detail_kayu_masuks`.`grade`), `turusan` as (select `detail_turusan_kayus`.`id_kayu_masuk` AS `id_kayu_masuk`,`detail_turusan_kayus`.`jenis_kayu_id` AS `jenis_kayu_id`,`detail_turusan_kayus`.`lahan_id` AS `lahan_id`,`detail_turusan_kayus`.`diameter` AS `diameter`,`detail_turusan_kayus`.`panjang` AS `panjang`,`detail_turusan_kayus`.`grade` AS `grade`,sum(`detail_turusan_kayus`.`kuantitas`) AS `turusan_jumlah` from `detail_turusan_kayus` group by `detail_turusan_kayus`.`id_kayu_masuk`,`detail_turusan_kayus`.`jenis_kayu_id`,`detail_turusan_kayus`.`lahan_id`,`detail_turusan_kayus`.`diameter`,`detail_turusan_kayus`.`panjang`,`detail_turusan_kayus`.`grade`), `left_join` as (select `d`.`id_kayu_masuk` AS `id_kayu_masuk`,`d`.`id_jenis_kayu` AS `id_jenis_kayu`,`d`.`id_lahan` AS `id_lahan`,`d`.`diameter` AS `diameter`,`d`.`panjang` AS `panjang`,`d`.`grade` AS `grade`,`d`.`detail_jumlah` AS `detail_jumlah`,coalesce(`t`.`turusan_jumlah`,0) AS `turusan_jumlah` from (`detail` `d` left join `turusan` `t` on(((`d`.`id_kayu_masuk` = `t`.`id_kayu_masuk`) and (`d`.`id_jenis_kayu` = `t`.`jenis_kayu_id`) and (`d`.`id_lahan` = `t`.`lahan_id`) and (`d`.`diameter` = `t`.`diameter`) and (`d`.`panjang` = `t`.`panjang`) and (`d`.`grade` = `t`.`grade`))))), `right_join` as (select `t`.`id_kayu_masuk` AS `id_kayu_masuk`,`t`.`jenis_kayu_id` AS `id_jenis_kayu`,`t`.`lahan_id` AS `id_lahan`,`t`.`diameter` AS `diameter`,`t`.`panjang` AS `panjang`,`t`.`grade` AS `grade`,0 AS `detail_jumlah`,`t`.`turusan_jumlah` AS `turusan_jumlah` from (`turusan` `t` left join `detail` `d` on(((`d`.`id_kayu_masuk` = `t`.`id_kayu_masuk`) and (`d`.`id_jenis_kayu` = `t`.`jenis_kayu_id`) and (`d`.`id_lahan` = `t`.`lahan_id`) and (`d`.`diameter` = `t`.`diameter`) and (`d`.`panjang` = `t`.`panjang`) and (`d`.`grade` = `t`.`grade`)))) where (`d`.`id_jenis_kayu` is null)) select row_number() OVER ()  AS `id`,`x`.`id_kayu_masuk` AS `id_kayu_masuk`,`x`.`id_jenis_kayu` AS `id_jenis_kayu`,`x`.`id_lahan` AS `id_lahan`,`x`.`diameter` AS `diameter`,`x`.`panjang` AS `panjang`,`x`.`grade` AS `grade`,sum(`x`.`detail_jumlah`) AS `detail_jumlah`,sum(`x`.`turusan_jumlah`) AS `turusan_jumlah`,sum((`x`.`detail_jumlah` - `x`.`turusan_jumlah`)) AS `selisih` from (select `left_join`.`id_kayu_masuk` AS `id_kayu_masuk`,`left_join`.`id_jenis_kayu` AS `id_jenis_kayu`,`left_join`.`id_lahan` AS `id_lahan`,`left_join`.`diameter` AS `diameter`,`left_join`.`panjang` AS `panjang`,`left_join`.`grade` AS `grade`,`left_join`.`detail_jumlah` AS `detail_jumlah`,`left_join`.`turusan_jumlah` AS `turusan_jumlah` from `left_join` union all select `right_join`.`id_kayu_masuk` AS `id_kayu_masuk`,`right_join`.`id_jenis_kayu` AS `id_jenis_kayu`,`right_join`.`id_lahan` AS `id_lahan`,`right_join`.`diameter` AS `diameter`,`right_join`.`panjang` AS `panjang`,`right_join`.`grade` AS `grade`,`right_join`.`detail_jumlah` AS `detail_jumlah`,`right_join`.`turusan_jumlah` AS `turusan_jumlah` from `right_join`) `x` group by `x`.`id_kayu_masuk`,`x`.`id_jenis_kayu`,`x`.`id_lahan`,`x`.`diameter`,`x`.`panjang`,`x`.`grade` order by `x`.`id_kayu_masuk`,`x`.`id_jenis_kayu`,`x`.`id_lahan`,`x`.`diameter`,`x`.`panjang`,`x`.`grade` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_10_20_014835_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_10_20_030821_create_pegawais_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_10_20_031628_create_ukurans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_10_20_031732_create_jenis_kayus_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_10_20_031853_create_kategori_mesins_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_10_20_031858_create_mesins_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_10_20_031905_create_lahans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_10_20_075111_create_produksi_rotaries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_10_20_075112_create_penggunaan_lahan_rotaries_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_10_20_085716_create_pegawai_rotaries_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_10_20_085749_create_detail_hasil_palet_rotaries_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_10_20_085821_create_ganti_pisau_rotaries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_10_23_015246_create_validasi_hasil_rotaries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_10_27_080521_create_kayu_pecah_rotaries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_10_29_095926_create_supplier_kayus_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_10_29_154745_create_harga_kayus_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_10_30_100122_create_kendaraan_supplier_kayus_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_10_30_105056_create_dokumen_kayus_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_10_31_091204_create_kayu_masuks_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_10_31_141705_create_turun_kayus_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_10_31_141850_create_tempat_kayus_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_10_31_153338_create_riwayat_kayus_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_11_03_135037_create_detail_turun_kayus_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_11_03_142016_create_detail_kayu_masuks_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_11_05_113405_create_nota_kayus_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_11_07_150655_create_targets_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_11_10_095126_create_detail_turusan_kayus_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_11_11_112232_create_produksi_press_dryers_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_11_11_112838_create_detail_masuks_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_11_11_130900_create_detail_hasils_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_11_11_131502_create_detail_mesins_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_11_14_085519_create_induk_akuns_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_11_14_085531_create_anak_akuns_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_11_14_085601_create_sub_anak_akuns_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_11_17_102140_create_tabel_detail_pegawai_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_11_17_102839_create_pegawai_turunkayus_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_11_11_131824_create_detail_pegawais_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_11_15_151549_create_tabel_validasis__dryer',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_11_17_085100_create_produksi_stik_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_11_17_085312_create_detail_pegawai_stik_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_11_17_085332_create_detail_masuk_stik_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_11_17_085500_create_detail_hasil_stik_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_11_17_090036_create_validasi_stik_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_11_17_103904_create_produksi_kedi_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_11_17_104139_create_detail_masuk_kedi_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_11_17_104328_create_detail_bongkar_kedi_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_11_17_104420_create_validasi_kedi_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_11_20_153759_create_kayu_compare_view',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_11_21_133606_create_nota_barang_keluar',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_11_21_134053_create_detail_nota_barang_keluar',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_11_22_094120_create_nota_barang_masuks_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_11_22_094131_create_detail_nota_barang_masuks_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_11_15_163316_create_pegawai_turun_kayus_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_11_18_150546_create_repairs_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_11_18_165346_create_bahan_repairs_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_11_18_165527_create_validasi_repairs_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_11_25_141114_create_produksi_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_11_25_141228_create_detail_pegawai_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_11_25_141301_create_veneer_bahan_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_11_25_141318_create_platform_bahan_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_11_25_141339_create_platform_hasil_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_11_25_141349_create_triplek_hasil_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2025_11_25_141406_create_bahan_penolong_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_11_25_154938_create_validasi_hp_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_11_29_092105_create_produksi_repairs_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_11_29_114037_create_modal_repairs_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_11_29_132716_create_rencana_pegawais_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_11_29_134546_create_rencana_repairs_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_11_29_140455_create_hasil_repairs_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_12_02_082658_create_kategori_barang_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_12_03_141337_create_grades_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_12_03_141338_create_barang_setengah_jadi_hp_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_12_03_141338_create_rencana_kerja_hp_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_12_03_141338_create_triplek_hasil_hp_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_12_03_141339_create_platform_hasil_hp_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_12_04_092957_create_produksi_sandings_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_10_20_085000_create_penggunaan_lahan_rotaries_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_11_25_141328_create_kategori_barang_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_11_03_141329_create_jenis_barang_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_11_25_141225_create_produksi_hp_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_11_25_141329_create_grades_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_11_25_141330_create_barang_setengah_jadi_hp_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2025_12_08_111412_create_triplek_hasil_hp_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_11_27_161723_create_detail_turun_kayus_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_11_29_114532_create_validasi_repairs_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_12_04_162231_create_validasis_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_12_04_164734_create_detail_pegawais_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_12_05_150323_create_modal_sandings_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_12_06_115720_create_hasil_sandings_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_12_08_115610_create_pegawai_sandings_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_12_11_102723_create_validasi_sandings_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_11_25_141331_create_komposisi_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_11_25_141332_create_detail_komposisi_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_11_25_141338_create_platform_bahan_hp_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_11_25_141338_create_veneer_bahan_hp_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_12_11_081326_create_bahan_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2025_12_15_153155_create_detail_pegawai_kedi_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2025_12_16_084159_create_lain_lain_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2025_10_20_032004_create_pegawais_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2025_11_25_141340_create_triplek_hasil_hp_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2025_12_16_152623_create_produksi_graji_triplek_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2025_12_16_152702_create_pegawai_graji_triplek_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2025_12_16_152721_create_masuk_graji_triplek_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2025_12_16_152733_create_hasil_graji_triplek_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2025_12_17_114237_create_validasi_graji_triplek_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2025_12_22_164042_create_produksi_nyusup_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2025_12_22_164112_create_pegawai_nyusup_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2025_12_22_164127_create_detail_barang_dikerjakan_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2025_12_22_164144_create_validasi_nyusup_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2025_12_23_163529_create_produksi_dempuls_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2025_12_23_164053_create_rencana_pegawai_dempuls_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2025_12_23_164502_create_detail_dempuls_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2025_12_23_165058_create_validasi_dempuls_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2025_12_24_082711_create_bahan_dempuls_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2025_12_24_143051_create_detail_dempul_pegawai_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2025_12_26_091538_create_produksi_sanding_joint_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2025_12_26_091721_create_pegawai_sanding_joint_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2025_12_26_091803_create_hasil_sanding_joint_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2025_12_26_092149_create_validasi_sanding_joint_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2025_12_26_144024_create_detail_lain_lains_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,' 2025_12_26_154159_create_lain_lain_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,' 2025_12_26_154159_create_lain_lain_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2025_12_26_154159_create_lain_lain_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2025_12_27_132947_create_produksi_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2025_12_27_133235_create_pegawai_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2025_12_27_133330_create_modal_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2025_12_27_133357_create_hasil_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2025_12_27_133432_create_bahan_produksi_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2025_12_27_142834_create_validasi_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2025_12_29_102212_create_produksi_pot_siku_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2025_12_29_102314_create_pegawai_pot_siku_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2025_12_29_102452_create_detail_barang_dikerjakan_pot_siku_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2025_12_29_102531_create_validasi_pot_siku_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2025_12_29_103653_create_produksi_pot_af_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2025_12_29_104301_create_pegawai_pot_af_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2025_12_29_104312_create_hasil_pot_af_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2025_12_29_104320_create_validasi_pot_af_joint_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2025_12_29_152704_create_produksi_pot_jelek_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2025_12_29_152731_create_pegawai_pot_jelek_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2025_12_29_152817_create_detail_barang_dikerjakan_pot_jelek_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2025_12_29_152904_create_validasi_pot_jelek_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2026_01_06_084904_create_produksi_pilih_plywood_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2026_01_06_085040_create_bahan_pilih_plywood_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_01_06_085130_create_hasil_pilih_plywood_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_01_06_085229_create_list_pekerjaan_menumpuk_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2026_01_06_093822_create_pegawai_pilih_plywood_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_01_07_143419_create_hasil_pilih_plywood_pegawai_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_01_10_104411_create_validasi_pilih_plywood_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_01_27_103602_create_graji_stiks_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2026_01_27_103733_create_produksi_guellotine_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2026_01_27_103747_create_modal_graji_stiks_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2026_01_27_103805_create_pegawai_guellotine_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2026_01_27_103814_create_hasil_guellotine_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2026_01_27_103825_create_validasi_guellotine_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2026_01_27_104543_create_pegawai_graji_stiks_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2026_01_27_105000_create_hasil_graji_stiks_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2026_01_27_113204_create_validasi_graji_stiks_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_01_27_141502_create_hasil_guellotine_pegawai_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_01_27_152416_create_produksi_graji_balken_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_01_27_152426_create_pegawai_graji_balken_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_01_27_152440_create_hasil_graji_balken_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2026_01_27_152448_create_validasi_graji_balken_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2026_01_28_102356_create_produksi_pilih_veneer_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2026_01_28_102416_create_pegawai_pilih_veneer_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2026_01_28_102701_create_modal_pilih_veneer_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2026_01_28_102712_create_hasil_pilih_veneer_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2026_01_28_102834_create_validasi_pilih_veneer_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2026_01_28_142235_create_hasil_pilih_veneer_pegawai_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2026_02_02_135814_create_harga_pegawais_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2026_02_02_140021_create_total_solasis_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2026_02_02_140158_create_harga_solasis_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_02_03_084328_create_add_parent_to_anak_akuns_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_02_03_095859_create_jurnal_1s_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2026_02_03_111100_create__jurnal_umum_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2026_02_03_085816_create_jurnal_tigas_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2026_02_03_090712_create_jurnal2_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2026_02_03_095630_create_table_jurnal_1s_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2026_02_03_111808_create_neracas_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2026_02_04_100913_create_activity_log_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2026_02_04_100914_add_event_column_to_activity_log_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2026_02_04_100915_add_batch_uuid_column_to_activity_log_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2026_02_06_091351_create_kontrak_kerja_table',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2026_02_06_094803_add_fields_for_kontrak_to_pegawais_table',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2026_02_07_105858_create_scheduled_notifications_table',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2026_02_07_154022_create_hari_libur_table',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2026_02_11_142952_create_perusahaan_jabatan_jamkerja_tables',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2026_02_11_102905_create_bahan_hotpress_table',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2026_02_11_105913_create_absensis_table',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2026_02_11_110424_create_detail_absensis_table',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2026_02_12_085051_create_notifications_table',112);
