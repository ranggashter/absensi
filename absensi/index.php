<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireKaryawan();
require __DIR__ . '/../config/database.php';

$employeeQuery = $pdo->prepare('SELECT id, nama, nik FROM karyawan WHERE id = :id');
$employeeQuery->execute(['id' => $_SESSION['user_id']]);
$employee = $employeeQuery->fetch();
if (!$employee) {
    http_response_code(404);
    exit('Data karyawan tidak ditemukan.');
}

$attendanceQuery = $pdo->prepare(
    'SELECT tanggal, jam_masuk, jam_pulang, status
     FROM absensi WHERE id_karyawan = :id AND tanggal = CURDATE()'
);
$attendanceQuery->execute(['id' => $employee['id']]);
$attendance = $attendanceQuery->fetch();
$flash = $_SESSION['attendance_flash'] ?? '';
unset($_SESSION['attendance_flash']);

$pageTitle = 'Absensi';
require __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-2xl rounded-lg bg-white p-6 shadow">
    <h1 class="mb-5 text-2xl font-bold">Absensi Harian</h1>
    <?php if ($flash !== ''): ?><div class="mb-4 rounded border border-green-500 bg-green-100 p-3 text-green-700"><?= e($flash) ?></div><?php endif; ?>
    <dl class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div><dt class="font-medium">Nama</dt><dd><?= e($employee['nama']) ?></dd></div>
        <div><dt class="font-medium">NIK</dt><dd><?= e($employee['nik']) ?></dd></div>
        <div><dt class="font-medium">Tanggal</dt><dd><?= e(date('Y-m-d')) ?></dd></div>
        <div><dt class="font-medium">Status</dt><dd><?= e($attendance['status'] ?? 'BELUM ABSEN') ?></dd></div>
        <div><dt class="font-medium">Jam Masuk</dt><dd><?= e($attendance['jam_masuk'] ?? '-') ?></dd></div>
        <div><dt class="font-medium">Jam Pulang</dt><dd><?= e($attendance['jam_pulang'] ?? '-') ?></dd></div>
    </dl>
    <div class="flex flex-wrap gap-3">
        <?php if (!$attendance): ?>
            <form method="post" action="<?= e(appUrl('absensi/masuk.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">ABSEN MASUK</button>
            </form>
        <?php elseif ($attendance['jam_masuk'] !== null && $attendance['jam_pulang'] === null): ?>
            <form method="post" action="<?= e(appUrl('absensi/pulang.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">ABSEN PULANG</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
