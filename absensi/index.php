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
$settingsQuery = $pdo->prepare(
    "SELECT TIME_FORMAT(jam_masuk, '%H:%i') AS jam_masuk,
            TIME_FORMAT(jam_pulang, '%H:%i') AS jam_pulang
     FROM pengaturan_absensi WHERE id = 1"
);
$settingsQuery->execute();
$attendanceSettings = $settingsQuery->fetch();
if (!$attendanceSettings) {
    throw new RuntimeException('Pengaturan absensi belum tersedia. Hubungi administrator.');
}
$requestsQuery = $pdo->prepare(
    'SELECT id, jenis, tanggal_mulai, tanggal_selesai, alasan, status, catatan_admin, created_at
     FROM pengajuan_izin WHERE id_karyawan = :id
     ORDER BY created_at DESC LIMIT 20'
);
$requestsQuery->execute(['id' => $employee['id']]);
$leaveRequests = $requestsQuery->fetchAll();
$earlyLeaveQuery = $pdo->prepare(
    "SELECT id FROM pengajuan_izin
     WHERE id_karyawan = :id AND jenis = 'PULANG_CEPAT'
       AND status = 'DISETUJUI' AND tanggal_mulai = CURDATE()
     LIMIT 1"
);
$earlyLeaveQuery->execute(['id' => $employee['id']]);
$earlyLeaveApproved = (bool) $earlyLeaveQuery->fetch();
$flash = $_SESSION['attendance_flash'] ?? '';
$flashType = $_SESSION['attendance_flash_type'] ?? 'success';
unset($_SESSION['attendance_flash']);
unset($_SESSION['attendance_flash_type']);

$pageTitle = 'Absensi';
require __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-2xl rounded-lg bg-white p-6 shadow">
    <h1 class="mb-5 text-2xl font-bold">Absensi Harian</h1>
    <?php if ($flash !== ''): ?>
        <div class="mb-4 rounded border p-3 <?= $flashType === 'error' ? 'border-red-500 bg-red-100 text-red-700' : 'border-green-500 bg-green-100 text-green-700' ?>"><?= e($flash) ?></div>
    <?php endif; ?>
    <dl class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div><dt class="font-medium">Nama</dt><dd><?= e($employee['nama']) ?></dd></div>
        <div><dt class="font-medium">NIK</dt><dd><?= e($employee['nik']) ?></dd></div>
        <div><dt class="font-medium">Tanggal</dt><dd><?= e(date('Y-m-d')) ?></dd></div>
        <div><dt class="font-medium">Status</dt><dd><?= e($attendance['status'] ?? 'BELUM ABSEN') ?></dd></div>
        <div><dt class="font-medium">Jam Masuk</dt><dd><?= e($attendance['jam_masuk'] ?? '-') ?></dd></div>
        <div><dt class="font-medium">Jam Pulang</dt><dd><?= e($attendance['jam_pulang'] ?? '-') ?></dd></div>
        <div><dt class="font-medium">Jadwal Masuk</dt><dd><?= e($attendanceSettings['jam_masuk']) ?></dd></div>
        <div><dt class="font-medium">Jadwal Pulang</dt><dd><?= e($attendanceSettings['jam_pulang']) ?></dd></div>
    </dl>
    <?php if ($earlyLeaveApproved): ?>
        <p class="mb-4 rounded border border-gray-300 bg-gray-50 p-3">Izin pulang cepat Anda untuk hari ini telah disetujui.</p>
    <?php endif; ?>
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
<section class="mx-auto mt-6 max-w-2xl rounded-lg bg-white p-6 shadow">
    <h2 class="mb-2 text-xl font-bold">Ajukan Izin</h2>
    <p class="mb-5 text-sm text-gray-600">Pengajuan akan ditinjau admin. Surat sakit wajib dilampirkan; file PDF, JPG, atau PNG maksimal 5 MB.</p>
    <form method="post" action="<?= e(appUrl('absensi/ajukan_izin.php')) ?>" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label class="block font-medium">Jenis izin
            <select class="mt-1 block w-full rounded border p-2" name="jenis" id="jenis-izin" required>
                <option value="">Pilih jenis izin</option>
                <option value="PULANG_CEPAT">Izin pulang cepat</option>
                <option value="SAKIT">Izin sakit</option>
                <option value="TIDAK_MASUK">Izin tidak masuk</option>
            </select>
        </label>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block font-medium">Tanggal mulai
                <input class="mt-1 block w-full rounded border p-2" type="date" name="tanggal_mulai" min="<?= e(date('Y-m-d')) ?>" required>
            </label>
            <label class="block font-medium" id="tanggal-selesai-wrap">Tanggal selesai
                <input class="mt-1 block w-full rounded border p-2" type="date" name="tanggal_selesai" min="<?= e(date('Y-m-d')) ?>" required>
            </label>
        </div>
        <label class="block font-medium">Alasan
            <textarea class="mt-1 block w-full rounded border p-2" name="alasan" rows="3" maxlength="2000" required></textarea>
        </label>
        <label class="block font-medium" id="lampiran-wrap">Lampiran surat sakit
            <input class="mt-1 block w-full rounded border p-2" type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
            <span class="mt-1 block text-sm font-normal text-gray-600">Wajib untuk izin sakit.</span>
        </label>
        <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Kirim Pengajuan</button>
    </form>
</section>
<section class="mx-auto mt-6 max-w-2xl rounded-lg bg-white p-6 shadow">
    <h2 class="mb-4 text-xl font-bold">Riwayat Pengajuan Izin</h2>
    <?php if (!$leaveRequests): ?>
        <p class="text-gray-600">Belum ada pengajuan izin.</p>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($leaveRequests as $request): ?>
                <article class="rounded border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-2">
                        <strong><?= e([
                            'PULANG_CEPAT' => 'Izin Pulang Cepat',
                            'SAKIT' => 'Izin Sakit',
                            'TIDAK_MASUK' => 'Izin Tidak Masuk',
                        ][$request['jenis']] ?? $request['jenis']) ?></strong>
                        <span><?= e($request['status']) ?></span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600"><?= e($request['tanggal_mulai']) ?> sampai <?= e($request['tanggal_selesai']) ?></p>
                    <p class="mt-2"><?= nl2br(e($request['alasan'])) ?></p>
                    <?php if ($request['catatan_admin']): ?><p class="mt-2 text-sm text-gray-600">Catatan admin: <?= e($request['catatan_admin']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script>
    const leaveType = document.getElementById('jenis-izin');
    const endDateWrap = document.getElementById('tanggal-selesai-wrap');
    const endDate = endDateWrap.querySelector('input');
    const attachment = document.querySelector('input[name="lampiran"]');

    function updateLeaveFields() {
        const earlyLeave = leaveType.value === 'PULANG_CEPAT';
        const sickLeave = leaveType.value === 'SAKIT';
        endDateWrap.hidden = earlyLeave;
        endDate.disabled = earlyLeave;
        attachment.required = sickLeave;
    }

    leaveType.addEventListener('change', updateLeaveFields);
    updateLeaveFields();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
