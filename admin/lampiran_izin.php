<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    http_response_code(400);
    exit('ID lampiran tidak valid.');
}

$query = $pdo->prepare('SELECT lampiran FROM pengajuan_izin WHERE id = :id');
$query->execute(['id' => $requestId]);
$filename = $query->fetchColumn();
if (!is_string($filename) || !preg_match('/^[a-f0-9]{32}\.(?:pdf|jpg|png)$/', $filename)) {
    http_response_code(404);
    exit('Lampiran tidak ditemukan.');
}

$path = __DIR__ . '/../storage/izin/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('File lampiran tidak ditemukan di penyimpanan.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$allowedTypes = [
    'application/pdf' => 'application/pdf',
    'image/jpeg' => 'image/jpeg',
    'image/png' => 'image/png',
];
if (!isset($allowedTypes[$mime])) {
    http_response_code(415);
    exit('Jenis file lampiran tidak didukung.');
}

header('Content-Type: ' . $allowedTypes[$mime]);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="surat-sakit-' . $requestId . '.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
