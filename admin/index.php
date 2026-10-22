<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';

$settingsMessage = '';
$settingsError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jamMasuk = trim((string) ($_POST['jam_masuk'] ?? ''));
    $jamPulang = trim((string) ($_POST['jam_pulang'] ?? ''));

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $settingsError = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $jamMasuk)
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $jamPulang)) {
        $settingsError = 'Masukkan jam yang valid dalam format 24 jam.';
    } elseif ($jamMasuk >= $jamPulang) {
        $settingsError = 'Jam pulang harus lebih akhir daripada jam masuk.';
    } else {
        $saveSettings = $pdo->prepare(
            'UPDATE pengaturan_absensi SET jam_masuk = :jam_masuk, jam_pulang = :jam_pulang WHERE id = 1'
        );
        $saveSettings->execute([
            'jam_masuk' => $jamMasuk,
            'jam_pulang' => $jamPulang,
        ]);
        $settingsMessage = 'Pengaturan jam absensi berhasil disimpan.';
    }
}

$settingsQuery = $pdo->prepare(
    "SELECT TIME_FORMAT(jam_masuk, '%H:%i') AS jam_masuk,
            TIME_FORMAT(jam_pulang, '%H:%i') AS jam_pulang
     FROM pengaturan_absensi WHERE id = 1"
);
$settingsQuery->execute();
$attendanceSettings = $settingsQuery->fetch();
if (!$attendanceSettings) {
    throw new RuntimeException('Pengaturan absensi belum tersedia. Jalankan pembaruan database.sql.');
}
$formJamMasuk = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $jamMasuk
    : $attendanceSettings['jam_masuk'];
$formJamPulang = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $jamPulang
    : $attendanceSettings['jam_pulang'];

$employeeCountQuery = $pdo->prepare('SELECT COUNT(*) FROM karyawan');
$employeeCountQuery->execute();
$totalEmployees = (int) $employeeCountQuery->fetchColumn();
$todayQuery = $pdo->prepare(
    'SELECT COUNT(*) AS sudah_absen,
            COALESCE(SUM(CASE WHEN jam_pulang IS NOT NULL THEN 1 ELSE 0 END), 0) AS sudah_pulang
     FROM absensi WHERE tanggal = CURDATE()'
);
$todayQuery->execute();
$todayStats = $todayQuery->fetch();
$checkedIn = (int) $todayStats['sudah_absen'];
$checkedOut = (int) $todayStats['sudah_pulang'];
$notCheckedIn = max(0, $totalEmployees - $checkedIn);
$pendingRequestsQuery = $pdo->prepare(
    "SELECT COUNT(*) FROM pengajuan_izin WHERE status = 'MENUNGGU'"
);
$pendingRequestsQuery->execute();
$pendingLeaveRequests = (int) $pendingRequestsQuery->fetchColumn();

$pageTitle = 'Dashboard Admin';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Dashboard Admin</h1>
    <a class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" href="<?= e(appUrl('karyawan/index.php')) ?>">Kelola Karyawan</a>
</div>
<section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ([
        'Jumlah Karyawan' => $totalEmployees,
        'Sudah Absen Hari Ini' => $checkedIn,
        'Belum Absen' => $notCheckedIn,
        'Sudah Pulang' => $checkedOut,
    ] as $label => $number): ?>
        <div class="rounded-lg bg-white p-5 shadow">
            <p class="text-sm text-gray-600"><?= e($label) ?></p>
            <p class="mt-2 text-3xl font-bold text-indigo-700"><?= e($number) ?></p>
        </div>
    <?php endforeach; ?>
</section>
<section class="rounded-lg bg-white p-6 shadow">
    <h2 class="text-xl font-bold">Kelola Kehadiran</h2>
    <p class="mt-2 text-gray-600">Lihat absensi hari ini atau telusuri laporan kehadiran berdasarkan tanggal.</p>
    <div class="mt-5 flex flex-wrap gap-3">
        <a class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" href="<?= e(appUrl('admin/absensi.php')) ?>">Lihat Absensi Hari Ini</a>
        <a class="rounded border border-gray-300 px-4 py-2 text-gray-900 hover:bg-gray-100" href="<?= e(appUrl('admin/laporan.php')) ?>">Buka Laporan</a>
        <a class="rounded border border-gray-300 px-4 py-2 text-gray-900 hover:bg-gray-100" href="<?= e(appUrl('admin/izin.php')) ?>">Tinjau Izin (<?= e($pendingLeaveRequests) ?> menunggu)</a>
    </div>
</section>
<section class="mt-6 rounded-lg bg-white p-6 shadow">
    <h2 class="text-xl font-bold">Pengaturan Jam Absensi</h2>
    <p class="mt-2 mb-5 text-gray-600">Absensi di luar jadwal tetap dicatat dan diberi status terlambat atau pulang awal.</p>
    <?php if ($settingsMessage !== ''): ?>
        <div class="mb-4 rounded border border-green-500 bg-green-100 p-3 text-green-700"><?= e($settingsMessage) ?></div>
    <?php endif; ?>
    <?php if ($settingsError !== ''): ?>
        <div class="mb-4 rounded border border-red-500 bg-red-100 p-3 text-red-700"><?= e($settingsError) ?></div>
    <?php endif; ?>
    <form method="post" class="flex flex-wrap items-end gap-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label class="block font-medium">Batas jam masuk
            <input class="mt-1 block rounded border p-2" type="time" name="jam_masuk" value="<?= e($formJamMasuk) ?>" required>
        </label>
        <label class="block font-medium">Jadwal jam pulang
            <input class="mt-1 block rounded border p-2" type="time" name="jam_pulang" value="<?= e($formJamPulang) ?>" required>
        </label>
        <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Simpan Jadwal</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
