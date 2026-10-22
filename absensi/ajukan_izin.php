<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireKaryawan();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

require __DIR__ . '/../config/database.php';

$types = [
    'PULANG_CEPAT' => 'Izin Pulang Cepat',
    'SAKIT' => 'Izin Sakit',
    'TIDAK_MASUK' => 'Izin Tidak Masuk',
];
$type = (string) ($_POST['jenis'] ?? '');
$startDate = trim((string) ($_POST['tanggal_mulai'] ?? ''));
$endDate = trim((string) ($_POST['tanggal_selesai'] ?? ''));
if ($type === 'PULANG_CEPAT') {
    $endDate = $startDate;
}
$reason = trim((string) ($_POST['alasan'] ?? ''));
$start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
$end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
$today = new DateTimeImmutable('today');
$error = '';

if (!isset($types[$type])) {
    $error = 'Pilih jenis izin yang valid.';
} elseif (!$start || $start->format('Y-m-d') !== $startDate
    || !$end || $end->format('Y-m-d') !== $endDate) {
    $error = 'Tanggal pengajuan tidak valid.';
} elseif ($start < $today || $end < $start || $end > $start->modify('+30 days')) {
    $error = 'Tanggal harus hari ini atau setelahnya, dengan rentang maksimal 31 hari.';
} elseif ($type === 'PULANG_CEPAT' && $startDate !== $endDate) {
    $error = 'Izin pulang cepat hanya berlaku untuk satu tanggal.';
} elseif ($reason === '' || strlen($reason) > 2000) {
    $error = 'Alasan wajib diisi dan maksimal 2.000 karakter.';
} elseif ($type === 'SAKIT' && (!isset($_FILES['lampiran']) || $_FILES['lampiran']['error'] === UPLOAD_ERR_NO_FILE)) {
    $error = 'Surat sakit wajib dilampirkan.';
}

$attachmentPath = null;
if ($error === '' && isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['lampiran'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Lampiran gagal diunggah. Silakan coba kembali.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = 'Ukuran lampiran maksimal 5 MB.';
    } else {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        if (!isset($extensions[$mimeType])) {
            $error = 'Lampiran harus berupa PDF, JPG, atau PNG.';
        }
    }
}

if ($error === '') {
    $duplicateQuery = $pdo->prepare(
        "SELECT id FROM pengajuan_izin
         WHERE id_karyawan = :id_karyawan
           AND status IN ('MENUNGGU', 'DISETUJUI')
           AND tanggal_mulai <= :tanggal_selesai
           AND tanggal_selesai >= :tanggal_mulai
         LIMIT 1"
    );
    $duplicateQuery->execute([
        'id_karyawan' => $_SESSION['user_id'],
        'tanggal_mulai' => $startDate,
        'tanggal_selesai' => $endDate,
    ]);
    if ($duplicateQuery->fetch()) {
        $error = 'Sudah ada pengajuan aktif yang tanggalnya bertumpang tindih.';
    }
}

if ($error === '' && isset($file)) {
    $directory = __DIR__ . '/../storage/izin';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Direktori lampiran izin tidak dapat dibuat.');
    }
    $attachmentPath = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $attachmentPath)) {
        throw new RuntimeException('Lampiran izin gagal disimpan.');
    }
}

if ($error === '') {
    try {
        $insert = $pdo->prepare(
            'INSERT INTO pengajuan_izin
                (id_karyawan, jenis, tanggal_mulai, tanggal_selesai, alasan, lampiran)
             VALUES (:id_karyawan, :jenis, :tanggal_mulai, :tanggal_selesai, :alasan, :lampiran)'
        );
        $insert->execute([
            'id_karyawan' => $_SESSION['user_id'],
            'jenis' => $type,
            'tanggal_mulai' => $startDate,
            'tanggal_selesai' => $endDate,
            'alasan' => $reason,
            'lampiran' => $attachmentPath,
        ]);
        $_SESSION['attendance_flash'] = 'Pengajuan izin berhasil dikirim dan menunggu persetujuan admin.';
        $_SESSION['attendance_flash_type'] = 'success';
    } catch (Throwable $exception) {
        if ($attachmentPath !== null && is_file(__DIR__ . '/../storage/izin/' . $attachmentPath)) {
            unlink(__DIR__ . '/../storage/izin/' . $attachmentPath);
        }
        throw $exception;
    }
} else {
    if ($attachmentPath !== null && is_file(__DIR__ . '/../storage/izin/' . $attachmentPath)) {
        unlink(__DIR__ . '/../storage/izin/' . $attachmentPath);
    }
    $_SESSION['attendance_flash'] = $error;
    $_SESSION['attendance_flash_type'] = 'error';
}

header('Location: ' . appUrl('absensi/index.php'));
exit;
