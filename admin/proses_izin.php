<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

require __DIR__ . '/../config/database.php';

$requestId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($_POST['aksi'] ?? '');
$adminNote = trim((string) ($_POST['catatan_admin'] ?? ''));
if (!$requestId || $requestId < 1 || !in_array($action, ['setujui', 'tolak'], true)
    || strlen($adminNote) > 500) {
    $_SESSION['leave_flash'] = 'Data keputusan izin tidak valid.';
    header('Location: ' . appUrl('admin/izin.php'));
    exit;
}

$pdo->beginTransaction();
try {
    $requestQuery = $pdo->prepare(
        'SELECT id, id_karyawan, jenis, tanggal_mulai, tanggal_selesai, status
         FROM pengajuan_izin WHERE id = :id FOR UPDATE'
    );
    $requestQuery->execute(['id' => $requestId]);
    $request = $requestQuery->fetch();
    if (!$request || $request['status'] !== 'MENUNGGU') {
        throw new DomainException('Pengajuan sudah diproses atau tidak ditemukan.');
    }

    if ($action === 'setujui') {
        $employeeLock = $pdo->prepare('SELECT id FROM karyawan WHERE id = :id FOR UPDATE');
        $employeeLock->execute(['id' => $request['id_karyawan']]);
        $employeeLock->fetch();

        $overlapQuery = $pdo->prepare(
            "SELECT id FROM pengajuan_izin
             WHERE id_karyawan = :id_karyawan AND id <> :id
               AND status = 'DISETUJUI'
               AND tanggal_mulai <= :tanggal_selesai
               AND tanggal_selesai >= :tanggal_mulai
             LIMIT 1"
        );
        $overlapQuery->execute([
            'id_karyawan' => $request['id_karyawan'],
            'id' => $requestId,
            'tanggal_mulai' => $request['tanggal_mulai'],
            'tanggal_selesai' => $request['tanggal_selesai'],
        ]);
        if ($overlapQuery->fetch()) {
            throw new DomainException('Tidak dapat menyetujui: rentang tanggal bertumpang tindih dengan izin lain yang sudah disetujui.');
        }

        if ($request['jenis'] !== 'PULANG_CEPAT') {
            $conflictQuery = $pdo->prepare(
                'SELECT tanggal FROM absensi
                 WHERE id_karyawan = :id_karyawan
                   AND tanggal BETWEEN :tanggal_mulai AND :tanggal_selesai
                   AND (jam_masuk IS NOT NULL OR jam_pulang IS NOT NULL)
                 LIMIT 1'
            );
            $conflictQuery->execute([
                'id_karyawan' => $request['id_karyawan'],
                'tanggal_mulai' => $request['tanggal_mulai'],
                'tanggal_selesai' => $request['tanggal_selesai'],
            ]);
            if ($conflictQuery->fetch()) {
                throw new DomainException('Tidak dapat menyetujui: terdapat absensi yang sudah memiliki jam masuk/pulang pada rentang tanggal tersebut.');
            }

            $attendanceStatus = $request['jenis'] === 'SAKIT' ? 'SAKIT' : 'IZIN TIDAK MASUK';
            $date = new DateTimeImmutable($request['tanggal_mulai']);
            $lastDate = new DateTimeImmutable($request['tanggal_selesai']);
            $upsertAttendance = $pdo->prepare(
                'INSERT INTO absensi (id_karyawan, tanggal, status)
                 VALUES (:id_karyawan, :tanggal, :status)
                 ON DUPLICATE KEY UPDATE status = VALUES(status)'
            );
            while ($date <= $lastDate) {
                $upsertAttendance->execute([
                    'id_karyawan' => $request['id_karyawan'],
                    'tanggal' => $date->format('Y-m-d'),
                    'status' => $attendanceStatus,
                ]);
                $date = $date->modify('+1 day');
            }
        }
    }

    $newStatus = $action === 'setujui' ? 'DISETUJUI' : 'DITOLAK';
    $updateRequest = $pdo->prepare(
        'UPDATE pengajuan_izin
         SET status = :status, catatan_admin = :catatan_admin,
             id_admin = :id_admin, reviewed_at = NOW()
         WHERE id = :id'
    );
    $updateRequest->execute([
        'status' => $newStatus,
        'catatan_admin' => $adminNote !== '' ? $adminNote : null,
        'id_admin' => $_SESSION['user_id'],
        'id' => $requestId,
    ]);
    $pdo->commit();
    $_SESSION['leave_flash'] = $action === 'setujui'
        ? 'Pengajuan izin berhasil disetujui.'
        : 'Pengajuan izin berhasil ditolak.';
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['leave_flash'] = $exception->getMessage();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

header('Location: ' . appUrl('admin/izin.php'));
exit;
