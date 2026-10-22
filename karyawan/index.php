<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';

$statement = $pdo->prepare(
    'SELECT id, nik, nama, jabatan, no_hp, email, created_at FROM karyawan ORDER BY nama'
);
$statement->execute();
$employees = $statement->fetchAll();

$pageTitle = 'Daftar Karyawan';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Daftar Karyawan</h1>
    <a class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" href="<?= e(appUrl('karyawan/tambah.php')) ?>">Tambah Karyawan</a>
</div>
<div class="overflow-x-auto rounded-lg bg-white shadow">
    <table class="w-full border-collapse">
        <thead class="bg-gray-200"><tr>
            <th class="border p-2 text-left">NIK</th><th class="border p-2 text-left">Nama</th>
            <th class="border p-2 text-left">Jabatan</th><th class="border p-2 text-left">No HP</th>
            <th class="border p-2 text-left">Email</th><th class="border p-2 text-left">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (!$employees): ?><tr><td class="border p-3 text-center" colspan="6">Belum ada data karyawan.</td></tr><?php endif; ?>
        <?php foreach ($employees as $employee): ?>
            <tr>
                <td class="border p-2"><?= e($employee['nik']) ?></td>
                <td class="border p-2"><?= e($employee['nama']) ?></td>
                <td class="border p-2"><?= e($employee['jabatan']) ?></td>
                <td class="border p-2"><?= e($employee['no_hp']) ?></td>
                <td class="border p-2"><?= e($employee['email']) ?></td>
                <td class="border p-2">
                    <div class="flex flex-wrap gap-2">
                        <a class="rounded bg-indigo-600 px-3 py-1 text-white hover:bg-indigo-700" href="<?= e(appUrl('karyawan/edit.php?id=' . $employee['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(appUrl('karyawan/hapus.php')) ?>" onsubmit="return confirm('Hapus data karyawan ini?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($employee['id']) ?>">
                            <button class="rounded bg-red-500 px-3 py-1 text-white hover:bg-red-600" type="submit">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
