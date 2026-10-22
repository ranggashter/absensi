<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID karyawan tidak valid.');
}

$select = $pdo->prepare('SELECT nama, no_hp FROM karyawan WHERE id = :id');
$select->execute(['id' => $id]);
$employee = $select->fetch();
if (!$employee) {
    http_response_code(404);
    exit('Data karyawan tidak ditemukan.');
}

$message = "NOTIFIKASI DATA KARYAWAN\n"
    . "Halo {$employee['nama']}.\n"
    . "Data Anda pada sistem karyawan telah dihapus oleh administrator.\n"
    . "Jika Anda merasa tidak melakukan permintaan tersebut, silakan hubungi administrator.";

try {
    $result = kirimWA($employee['no_hp'], $message);
} catch (Throwable $exception) {
    error_log('Notifikasi hapus karyawan gagal: ' . $exception->getMessage());
    http_response_code(502);
    exit('Notifikasi WhatsApp gagal. Data tidak dihapus; coba lagi setelah memeriksa koneksi Fonnte.');
}

if (!$result['success']) {
    http_response_code(502);
    exit('Fonnte menolak notifikasi. Data tidak dihapus; periksa konfigurasi dan coba lagi.');
}

$delete = $pdo->prepare('DELETE FROM karyawan WHERE id = :id');
$delete->execute(['id' => $id]);

header('Location: ' . appUrl('karyawan/index.php'));
exit;
