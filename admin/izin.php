<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';

$requestsQuery = $pdo->prepare(
    'SELECT p.id, p.jenis, p.tanggal_mulai, p.tanggal_selesai, p.alasan,
            p.lampiran, p.status, p.catatan_admin, p.created_at,
            k.nama, k.nik, k.email
     FROM pengajuan_izin p
     INNER JOIN karyawan k ON k.id = p.id_karyawan
     ORDER BY CASE p.status WHEN \'MENUNGGU\' THEN 0 ELSE 1 END,
              p.created_at DESC
     LIMIT 200'
);
$requestsQuery->execute();
$requests = $requestsQuery->fetchAll();
$flash = $_SESSION['leave_flash'] ?? '';
unset($_SESSION['leave_flash']);

$labels = [
    'PULANG_CEPAT' => 'Izin Pulang Cepat',
    'SAKIT' => 'Izin Sakit',
    'TIDAK_MASUK' => 'Izin Tidak Masuk',
];

$pageTitle = 'Pengajuan Izin';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold">Pengajuan Izin</h1>
    <p class="mt-1 text-gray-600">Tinjau izin pulang cepat, sakit, dan tidak masuk. Maksimal 200 pengajuan terbaru ditampilkan.</p>
</div>
<?php if ($flash !== ''): ?>
    <div class="mb-4 rounded border border-gray-300 bg-white p-3"><?= e($flash) ?></div>
<?php endif; ?>
<div class="space-y-4">
    <?php if (!$requests): ?>
        <section class="rounded-lg bg-white p-6 text-gray-600 shadow">Belum ada pengajuan izin.</section>
    <?php endif; ?>
    <?php foreach ($requests as $request): ?>
        <section class="rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold"><?= e($labels[$request['jenis']] ?? $request['jenis']) ?></h2>
                    <p class="mt-1 text-sm text-gray-600"><?= e($request['nama']) ?> · NIK <?= e($request['nik']) ?> · <?= e($request['email']) ?></p>
                </div>
                <span class="rounded border border-gray-300 px-3 py-1 text-sm font-semibold"><?= e($request['status']) ?></span>
            </div>
            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold">Tanggal</dt><dd><?= e($request['tanggal_mulai']) ?> sampai <?= e($request['tanggal_selesai']) ?></dd></div>
                <div><dt class="font-semibold">Diajukan</dt><dd><?= e($request['created_at']) ?></dd></div>
            </dl>
            <p class="mt-3 whitespace-pre-line"><?= e($request['alasan']) ?></p>
            <?php if ($request['lampiran'] !== null): ?>
                <p class="mt-3"><a class="font-semibold underline" href="<?= e(appUrl('admin/lampiran_izin.php?id=' . $request['id'])) ?>">Unduh surat sakit</a></p>
            <?php endif; ?>
            <?php if ($request['catatan_admin']): ?>
                <p class="mt-3 text-sm text-gray-600">Catatan admin: <?= e($request['catatan_admin']) ?></p>
            <?php endif; ?>
            <?php if ($request['status'] === 'MENUNGGU'): ?>
                <form method="post" action="<?= e(appUrl('admin/proses_izin.php')) ?>" class="mt-4 flex flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= e($request['id']) ?>">
                    <label class="block font-medium">Catatan (opsional)
                        <input class="mt-1 block w-full rounded border p-2" type="text" name="catatan_admin" maxlength="500">
                    </label>
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit" name="aksi" value="setujui">Setujui</button>
                    <button class="rounded border border-gray-300 px-4 py-2 text-gray-900 hover:bg-gray-100" type="submit" name="aksi" value="tolak">Tolak</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
