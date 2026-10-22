<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';

$date = date('Y-m-d');
$attendanceQuery = $pdo->prepare(
    'SELECT k.nama, k.nik, a.jam_masuk, a.jam_pulang, a.status
     FROM karyawan k
     LEFT JOIN absensi a ON a.id_karyawan = k.id AND a.tanggal = :tanggal
     ORDER BY k.nama
     LIMIT 500'
);
$attendanceQuery->execute(['tanggal' => $date]);
$attendance = $attendanceQuery->fetchAll();

$pageTitle = 'Absensi Hari Ini';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">Absensi Hari Ini</h1>
        <p class="mt-1 text-gray-600">Status kehadiran karyawan pada <?= e($date) ?>.</p>
    </div>
    <a class="rounded border border-gray-300 px-4 py-2 text-gray-900 hover:bg-gray-100" href="<?= e(appUrl('admin/laporan.php')) ?>">Lihat Laporan</a>
</div>
<section class="overflow-x-auto rounded-lg bg-white shadow">
    <table class="w-full border-collapse">
        <thead class="bg-gray-200"><tr>
            <th class="border p-3 text-left">No</th>
            <th class="border p-3 text-left">Nama</th>
            <th class="border p-3 text-left">NIK</th>
            <th class="border p-3 text-left">Jam Masuk</th>
            <th class="border p-3 text-left">Jam Pulang</th>
            <th class="border p-3 text-left">Status</th>
        </tr></thead>
        <tbody>
        <?php if (!$attendance): ?>
            <tr><td class="border p-4 text-center text-gray-600" colspan="6">Belum ada data karyawan.</td></tr>
        <?php endif; ?>
        <?php foreach ($attendance as $index => $row): ?>
            <tr>
                <td class="border p-3"><?= e($index + 1) ?></td>
                <td class="border p-3"><?= e($row['nama']) ?></td>
                <td class="border p-3"><?= e($row['nik']) ?></td>
                <td class="border p-3"><?= e($row['jam_masuk'] ?? '-') ?></td>
                <td class="border p-3"><?= e($row['jam_pulang'] ?? '-') ?></td>
                <td class="border p-3"><?= e($row['status'] ?? 'BELUM ABSEN') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
