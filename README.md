# Sistem Karyawan + WhatsApp Gateway Fonnte

Aplikasi web native PHP 8+ dan MySQL untuk pengelolaan data karyawan, absensi, dan notifikasi WhatsApp melalui Fonnte. Aplikasi ini ditujukan untuk dijalankan secara lokal menggunakan XAMPP.

## Lokasi proyek

Simpan folder proyek di:

```text
C:\xampp\htdocs\karyawan-wa\
```

## Struktur proyek

```text
karyawan-wa/
├── config/
│   ├── config.php
│   ├── config.example.php
│   └── database.php
├── api/
│   └── fonnte.php
├── auth/
│   ├── register.php
│   ├── login.php
│   └── logout.php
├── karyawan/
│   ├── index.php
│   ├── tambah.php
│   ├── edit.php
│   └── hapus.php
├── absensi/
│   ├── index.php
│   ├── masuk.php
│   └── pulang.php
├── admin/
│   └── index.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── auth_check.php
├── seed_admin.php
├── test_wa.php
├── database.sql
├── .gitignore
├── index.php
└── README.md
```

Folder dan file fitur akan dilengkapi bertahap. `config/config.php` nantinya menyimpan konfigurasi rahasia lokal dan tidak dilacak Git.

## Database

Impor `database.sql` melalui phpMyAdmin atau MySQL untuk membuat database `db_karyawan` dan tabelnya. Jalankan `seed_admin.php` untuk membuat akun admin `admin` dengan nomor `6281234567890` dan password acak.

## Konfigurasi lokal

1. Salin `config/config.example.php` menjadi `config/config.php`.
2. Isi pengaturan database XAMPP dan token Fonnte di `config/config.php`. File tersebut dikecualikan dari Git.
3. Dari Command Prompt, jalankan `php seed_admin.php`. Simpan password acak yang ditampilkan, lalu hapus `seed_admin.php`. Seeder tidak mengubah password admin yang sudah aktif.
4. Untuk menguji pengiriman WA, jalankan `php test_wa.php 081234567890` setelah token Fonnte diisi dan cURL PHP aktif.

Kedua skrip setup dan uji hanya dapat dijalankan melalui CLI, bukan browser.

## Menjalankan aplikasi

1. Pastikan Apache, MySQL, dan ekstensi PHP `pdo_mysql` serta `curl` aktif di XAMPP.
2. Impor `database.sql` melalui phpMyAdmin.
3. Salin `config/config.example.php` ke `config/config.php`, lalu sesuaikan kredensial MySQL dan isi token Fonnte.
4. Buka Command Prompt di folder proyek dan jalankan `php seed_admin.php`. Simpan password admin yang ditampilkan, kemudian hapus file `seed_admin.php`.
5. Buka `http://localhost/karyawan-wa/` untuk masuk ke halaman login.
6. Login admin menggunakan username `admin` dan password yang dibuat. Karyawan dapat mendaftar melalui tautan registrasi.

Pastikan waktu PHP dan MySQL sesuai zona waktu lokal. Pengiriman WhatsApp memerlukan token Fonnte aktif, koneksi internet, dan nomor tujuan terdaftar/valid.

## Checklist pengujian

- [ ] Impor `database.sql`; pastikan database, tiga tabel, unique key NIK/email, dan unique absensi terbentuk.
- [ ] Jalankan seeder admin; login admin berhasil, password yang salah ditolak, dan akses admin ditolak untuk karyawan.
- [ ] Registrasi karyawan dengan data valid; data tersimpan dan pesan registrasi dikirim ke karyawan serta admin.
- [ ] Uji validasi registrasi: kolom wajib, NIK bukan angka, email tidak valid, nomor HP di luar 10–15 digit, password berbeda, NIK duplikat, dan email duplikat.
- [ ] Tambahkan karyawan melalui menu admin; data tampil di daftar dan password tidak tampil.
- [ ] Edit karyawan; data berubah dan notifikasi edit dikirim ke nomor baru.
- [ ] Hapus karyawan; konfirmasi muncul dan notifikasi berhasil dikirim sebelum data dihapus. Bila pengiriman gagal, data harus tetap ada.
- [ ] Karyawan absen masuk; data tercatat sekali untuk hari tersebut, pesan masuk terkirim, dan absen kedua ditolak.
- [ ] Karyawan absen pulang setelah masuk; jam pulang dan status diperbarui, pesan pulang terkirim, dan absen pulang kedua ditolak.
- [ ] Uji laporan admin dengan beberapa tanggal serta statistik total, sudah absen, belum absen, dan sudah pulang.
- [ ] Akses halaman admin sebagai karyawan dan halaman karyawan sebagai admin; akses yang tidak sesuai harus ditolak.
- [ ] Pastikan token Fonnte tidak muncul di halaman atau file yang dilacak Git.

Uji kirim pesan satu kali dengan `php test_wa.php 081234567890`; perintah ini benar-benar mengirim WhatsApp ke nomor yang diberikan.

## Status pengerjaan

- [x] Tahap 1: Struktur folder, `.gitignore`, README, dan redirect halaman awal
- [x] Tahap 2: SQL database
- [x] Tahap 3: Konfigurasi, koneksi PDO, helper Fonnte, layout, helper autentikasi, dan skrip CLI
- [x] Tahap 4: Registrasi karyawan
- [x] Tahap 5: Login dan logout
- [x] Tahap 6: Daftar dan tambah karyawan
- [x] Tahap 7: Edit karyawan dengan notifikasi
- [x] Tahap 8: Hapus karyawan dengan notifikasi sebelum delete
- [x] Tahap 9: Halaman absensi dan absen masuk
- [x] Tahap 10: Absen pulang
- [x] Tahap 11: Dashboard admin dan laporan tanggal
- [x] Tahap 12: Panduan pengujian dan checklist jobsheet

Jangan letakkan token Fonnte atau kredensial lain di file yang dilacak Git.
