CREATE DATABASE IF NOT EXISTS db_karyawan
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_karyawan;

CREATE TABLE IF NOT EXISTS karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(30) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    jabatan VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME NULL,
    jam_pulang TIME NULL,
    status VARCHAR(30) DEFAULT 'HADIR',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_absensi (id_karyawan, tanggal),
    CONSTRAINT fk_absensi_karyawan
        FOREIGN KEY (id_karyawan) REFERENCES karyawan(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengaturan_absensi (
    id TINYINT UNSIGNED PRIMARY KEY,
    jam_masuk TIME NOT NULL DEFAULT '08:00:00',
    jam_pulang TIME NOT NULL DEFAULT '17:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pengaturan_absensi (id, jam_masuk, jam_pulang)
VALUES (1, '08:00:00', '17:00:00');

CREATE TABLE IF NOT EXISTS pengajuan_izin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    jenis ENUM('PULANG_CEPAT', 'SAKIT', 'TIDAK_MASUK') NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    alasan TEXT NOT NULL,
    lampiran VARCHAR(255) NULL,
    status ENUM('MENUNGGU', 'DISETUJUI', 'DITOLAK') NOT NULL DEFAULT 'MENUNGGU',
    catatan_admin VARCHAR(500) NULL,
    id_admin INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    INDEX idx_pengajuan_izin_status_tanggal (status, tanggal_mulai, tanggal_selesai),
    INDEX idx_pengajuan_izin_karyawan (id_karyawan, created_at),
    CONSTRAINT fk_pengajuan_izin_karyawan
        FOREIGN KEY (id_karyawan) REFERENCES karyawan(id) ON DELETE CASCADE,
    CONSTRAINT fk_pengajuan_izin_admin
        FOREIGN KEY (id_admin) REFERENCES admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
