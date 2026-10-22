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

$settingsQuery = $pdo->prepare(
    "SELECT TIME_FORMAT(jam_masuk, '%H:%i') FROM pengaturan_absensi WHERE id = 1"
);
$settingsQuery->execute();
$jamMasukTarget = $settingsQuery->fetchColumn();
if ($jamMasukTarget === false) {
    throw new RuntimeException('Pengaturan absensi belum tersedia. Hubungi administrator.');
}

$existing = $pdo->prepare(
    'SELECT id FROM absensi WHERE id_karyawan = :id AND tanggal = CURDATE()'
);
$existing->execute(['id' => $employee['id']]);
if ($existing->fetch()) {
    $_SESSION['attendance_flash'] = 'Anda sudah absen masuk hari ini.';
    header('Location: ' . appUrl('absensi/index.php'));
    exit;
}

try {
    $currentTimeQuery = $pdo->prepare('SELECT TIME_FORMAT(CURTIME(), "%H:%i")');
    $currentTimeQuery->execute();
    $currentTime = $currentTimeQuery->fetchColumn();
    $status = $currentTime > $jamMasukTarget ? 'TERLAMBAT' : 'HADIR';

    $insert = $pdo->prepare(
        'INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, status)
         VALUES (:id, CURDATE(), CURTIME(), :status)'
    );
    $insert->execute(['id' => $employee['id'], 'status' => $status]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        $_SESSION['attendance_flash'] = 'Anda sudah absen masuk hari ini.';
        header('Location: ' . appUrl('absensi/index.php'));
        exit;
    }
    throw $exception;
}

$timeQuery = $pdo->prepare(
    'SELECT tanggal, jam_masuk FROM absensi WHERE id = :id'
);
$timeQuery->execute(['id' => $pdo->lastInsertId()]);
$time = $timeQuery->fetch();
$message = "ABSENSI MASUK\n"
    . "Nama: {$employee['nama']}\n"
    . "NIK: {$employee['nik']}\n"
    . "Tanggal: {$time['tanggal']}\n"
    . "Jam: {$time['jam_masuk']}\n"
    . "Status: {$status}\n"
    . "Absensi masuk Anda telah berhasil dicatat.";

try {
    $result = kirimWA($employee['no_hp'], $message);
    $attendanceStatus = $status === 'TERLAMBAT' ? 'Status: TERLAMBAT.' : 'Status: HADIR.';
    $_SESSION['attendance_flash'] = $result['success']
        ? 'Absensi masuk berhasil dicatat. ' . $attendanceStatus . ' Notifikasi WhatsApp dikirim.'
        : 'Absensi masuk tercatat. ' . $attendanceStatus . ' Fonnte menolak notifikasi.';
} catch (Throwable $exception) {
    error_log('Notifikasi absensi masuk gagal: ' . $exception->getMessage());
    $_SESSION['attendance_flash'] = 'Absensi masuk tercatat dengan status ' . $status . ', tetapi notifikasi WhatsApp gagal dikirim.';
}

header('Location: ' . appUrl('absensi/index.php'));
exit;
