<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';

$date = trim((string) ($_GET['tanggal'] ?? date('Y-m-d')));
$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    $date = date('Y-m-d');
}

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

$reportQuery = $pdo->prepare(
    'SELECT k.nama, k.nik, a.tanggal, a.jam_masuk, a.jam_pulang, a.status
     FROM absensi a
     INNER JOIN karyawan k ON k.id = a.id_karyawan
     WHERE a.tanggal = :tanggal
     ORDER BY k.nama
     LIMIT 500'
);
$reportQuery->execute(['tanggal' => $date]);
$report = $reportQuery->fetchAll();

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
<section id="laporan" class="rounded-lg bg-white p-6 shadow">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <h2 class="text-xl font-bold">Laporan Absensi</h2>
        <form method="get" class="flex flex-wrap items-end gap-2">
            <label class="font-medium">Filter tanggal
                <input class="mt-1 block rounded border p-2" type="date" name="tanggal" value="<?= e($date) ?>" required>
            </label>
            <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Tampilkan</button>
        </form>
    </div>
    <p class="mb-3 text-sm text-gray-600">Menampilkan maksimal 500 catatan untuk tanggal <?= e($date) ?>.</p>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead class="bg-gray-200"><tr>
                <th class="border p-2 text-left">No</th>
                <th class="border p-2 text-left">Nama</th>
                <th class="border p-2 text-left">NIK</th>
                <th class="border p-2 text-left">Tanggal</th>
                <th class="border p-2 text-left">Masuk</th>
                <th class="border p-2 text-left">Pulang</th>
                <th class="border p-2 text-left">Status</th>
            </tr></thead>
            <tbody>
            <?php if (!$report): ?><tr><td class="border p-3 text-center" colspan="7">Tidak ada catatan absensi pada tanggal ini.</td></tr><?php endif; ?>
            <?php foreach ($report as $index => $row): ?>
                <tr>
                    <td class="border p-2"><?= e($index + 1) ?></td>
                    <td class="border p-2"><?= e($row['nama']) ?></td>
                    <td class="border p-2"><?= e($row['nik']) ?></td>
                    <td class="border p-2"><?= e($row['tanggal']) ?></td>
                    <td class="border p-2"><?= e($row['jam_masuk'] ?? '-') ?></td>
                    <td class="border p-2"><?= e($row['jam_pulang'] ?? '-') ?></td>
                    <td class="border p-2"><?= e($row['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
