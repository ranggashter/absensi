<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireKaryawan();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

$employeeQuery = $pdo->prepare('SELECT id, nik, nama, no_hp FROM karyawan WHERE id = :id');
$employeeQuery->execute(['id' => $_SESSION['user_id']]);
$employee = $employeeQuery->fetch();
if (!$employee) {
    http_response_code(404);
    exit('Data karyawan tidak ditemukan.');
}

$attendanceQuery = $pdo->prepare(
    'SELECT id, tanggal, jam_masuk, jam_pulang
     FROM absensi WHERE id_karyawan = :id AND tanggal = CURDATE()'
);
$attendanceQuery->execute(['id' => $employee['id']]);
$attendance = $attendanceQuery->fetch();

if (!$attendance) {
    $_SESSION['attendance_flash'] = 'Anda belum absen masuk.';
    header('Location: ' . appUrl('absensi/index.php'));
    exit;
}
if ($attendance['jam_pulang'] !== null) {
    $_SESSION['attendance_flash'] = 'Anda sudah absen pulang.';
    header('Location: ' . appUrl('absensi/index.php'));
    exit;
}

$update = $pdo->prepare(
    'UPDATE absensi SET jam_pulang = CURTIME(), status = :status
     WHERE id = :id AND jam_pulang IS NULL'
);
$update->execute(['status' => 'PULANG', 'id' => $attendance['id']]);
if ($update->rowCount() !== 1) {
    $_SESSION['attendance_flash'] = 'Anda sudah absen pulang.';
    header('Location: ' . appUrl('absensi/index.php'));
    exit;
}

$timeQuery = $pdo->prepare('SELECT jam_pulang FROM absensi WHERE id = :id');
$timeQuery->execute(['id' => $attendance['id']]);
$jamPulang = $timeQuery->fetchColumn();
$message = "ABSENSI PULANG\n"
    . "Nama: {$employee['nama']}\n"
    . "NIK: {$employee['nik']}\n"
    . "Tanggal: {$attendance['tanggal']}\n"
    . "Jam Masuk: {$attendance['jam_masuk']}\n"
    . "Jam Pulang: {$jamPulang}\n"
    . "Status: PULANG\n"
    . "Terima kasih. Absensi pulang Anda telah berhasil dicatat.";

try {
    $result = kirimWA($employee['no_hp'], $message);
    $_SESSION['attendance_flash'] = $result['success']
        ? 'Absensi pulang berhasil dicatat dan notifikasi WhatsApp dikirim.'
        : 'Absensi pulang tercatat, tetapi Fonnte menolak notifikasi.';
} catch (Throwable $exception) {
    error_log('Notifikasi absensi pulang gagal: ' . $exception->getMessage());
    $_SESSION['attendance_flash'] = 'Absensi pulang tercatat, tetapi notifikasi WhatsApp gagal dikirim.';
}

header('Location: ' . appUrl('absensi/index.php'));
exit;
