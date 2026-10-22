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
    'SELECT id, tanggal, jam_masuk, jam_pulang, status
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

$settingsQuery = $pdo->prepare(
    "SELECT TIME_FORMAT(jam_pulang, '%H:%i') FROM pengaturan_absensi WHERE id = 1"
);
$settingsQuery->execute();
$jamPulangTarget = $settingsQuery->fetchColumn();
if ($jamPulangTarget === false) {
    throw new RuntimeException('Pengaturan absensi belum tersedia. Hubungi administrator.');
}

$currentTimeQuery = $pdo->prepare("SELECT TIME_FORMAT(CURTIME(), '%H:%i')");
$currentTimeQuery->execute();
$currentTime = $currentTimeQuery->fetchColumn();
$earlyLeaveQuery = $pdo->prepare(
    "SELECT id FROM pengajuan_izin
     WHERE id_karyawan = :id_karyawan AND jenis = 'PULANG_CEPAT'
       AND status = 'DISETUJUI' AND tanggal_mulai = CURDATE()
     LIMIT 1"
);
$earlyLeaveQuery->execute(['id_karyawan' => $employee['id']]);
$hasApprovedEarlyLeave = (bool) $earlyLeaveQuery->fetch();
$pulangAwal = $currentTime < $jamPulangTarget;
$earlyLeaveStatus = $pulangAwal && $hasApprovedEarlyLeave
    ? 'IZIN PULANG CEPAT'
    : ($pulangAwal ? 'PULANG AWAL' : 'PULANG');
$status = $attendance['status'] === 'TERLAMBAT'
    ? 'TERLAMBAT, ' . ($pulangAwal && $hasApprovedEarlyLeave
        ? 'IZIN PULANG CEPAT'
        : ($pulangAwal ? 'PULANG AWAL' : 'PULANG'))
    : $earlyLeaveStatus;

$update = $pdo->prepare(
    'UPDATE absensi SET jam_pulang = CURTIME(), status = :status
     WHERE id = :id AND jam_pulang IS NULL'
);
$update->execute(['status' => $status, 'id' => $attendance['id']]);
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
    . "Status: {$status}\n"
    . "Terima kasih. Absensi pulang Anda telah berhasil dicatat.";

try {
    $result = kirimWA($employee['no_hp'], $message);
    $attendanceStatus = $pulangAwal
        ? ($hasApprovedEarlyLeave ? ' Izin pulang cepat disetujui.' : ' Status: PULANG AWAL.')
        : '';
    $_SESSION['attendance_flash'] = $result['success']
        ? 'Absensi pulang berhasil dicatat.' . $attendanceStatus . ' Notifikasi WhatsApp dikirim.'
        : 'Absensi pulang tercatat.' . $attendanceStatus . ' Fonnte menolak notifikasi.';
} catch (Throwable $exception) {
    error_log('Notifikasi absensi pulang gagal: ' . $exception->getMessage());
    $_SESSION['attendance_flash'] = 'Absensi pulang tercatat dengan status ' . $status . ', tetapi notifikasi WhatsApp gagal dikirim.';
}

header('Location: ' . appUrl('absensi/index.php'));
exit;
