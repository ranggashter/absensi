<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';

$date = trim((string) ($_GET['tanggal'] ?? date('Y-m-d')));
$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    $date = date('Y-m-d');
}

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

$pageTitle = 'Laporan Absensi';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold">Laporan Absensi</h1>
    <p class="mt-1 text-gray-600">Pilih tanggal untuk melihat catatan kehadiran.</p>
</div>
<section class="rounded-lg bg-white p-6 shadow">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <h2 class="text-xl font-bold">Catatan <?= e($date) ?></h2>
        <form method="get" class="flex flex-wrap items-end gap-2">
            <label class="font-medium">Filter tanggal
                <input class="mt-1 block rounded border p-2" type="date" name="tanggal" value="<?= e($date) ?>" required>
            </label>
            <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Tampilkan</button>
        </form>
    </div>
    <p class="mb-3 text-sm text-gray-600">Menampilkan maksimal 500 catatan untuk tanggal yang dipilih.</p>
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
            <?php if (!$report): ?>
                <tr><td class="border p-3 text-center text-gray-600" colspan="7">Tidak ada catatan absensi pada tanggal ini.</td></tr>
            <?php endif; ?>
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
