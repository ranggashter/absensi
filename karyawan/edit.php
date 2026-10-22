<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
}
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID karyawan tidak valid.');
}

$query = $pdo->prepare(
    'SELECT id, nik, nama, jabatan, no_hp, email FROM karyawan WHERE id = :id'
);
$query->execute(['id' => $id]);
$employee = $query->fetch();
if (!$employee) {
    http_response_code(404);
    exit('Data karyawan tidak ditemukan.');
}

$errors = [];
$success = '';
$values = $employee;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    }
    foreach (['nik', 'nama', 'jabatan', 'no_hp', 'email'] as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
        if ($values[$key] === '') {
            $errors[] = 'Semua kolom wajib diisi.';
        }
    }
    if (!preg_match('/^\d+$/', $values['nik'])) {
        $errors[] = 'NIK harus berupa angka.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    $phoneDigits = preg_replace('/\D+/', '', $values['no_hp']) ?? '';
    if (!preg_match('/^\+?[\d\s().-]+$/', $values['no_hp'])
        || !preg_match('/^\d{10,15}$/', $phoneDigits)) {
        $errors[] = 'Nomor HP harus terdiri dari 10–15 digit.';
    }

    if (!$errors) {
        $duplicate = $pdo->prepare(
            'SELECT id FROM karyawan
             WHERE (nik = :nik OR email = :email) AND id <> :id'
        );
        $duplicate->execute([
            'nik' => $values['nik'],
            'email' => $values['email'],
            'id' => $id,
        ]);
        if ($duplicate->fetch()) {
            $errors[] = 'NIK atau email sudah digunakan oleh karyawan lain.';
        }
    }

    if (!$errors) {
        try {
            $newPhone = normalisasiNomor($values['no_hp']);
            $update = $pdo->prepare(
                'UPDATE karyawan
                 SET nik = :nik, nama = :nama, jabatan = :jabatan,
                     no_hp = :no_hp, email = :email
                 WHERE id = :id'
            );
            $update->execute([
                'nik' => $values['nik'],
                'nama' => $values['nama'],
                'jabatan' => $values['jabatan'],
                'no_hp' => $newPhone,
                'email' => $values['email'],
                'id' => $id,
            ]);
            $message = "PEMBARUAN DATA\n"
                . "Halo {$values['nama']}.\n"
                . "Data karyawan Anda telah diperbarui oleh administrator.\n"
                . "Data terbaru:\n"
                . "Jabatan: {$values['jabatan']}\n"
                . "No. HP: {$newPhone}\n"
                . "Email: {$values['email']}\n"
                . "Jika perubahan ini tidak Anda lakukan, silakan hubungi administrator.";

            try {
                $result = kirimWA($newPhone, $message);
                $success = $result['success']
                    ? 'Data berhasil diperbarui dan notifikasi WhatsApp dikirim.'
                    : 'Data berhasil diperbarui, tetapi Fonnte menolak notifikasi.';
            } catch (Throwable $exception) {
                error_log('Notifikasi edit karyawan gagal: ' . $exception->getMessage());
                $success = 'Data berhasil diperbarui, tetapi notifikasi WhatsApp gagal dikirim.';
            }
            $values['no_hp'] = $newPhone;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'NIK atau email sudah digunakan oleh karyawan lain.';
            } else {
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Edit Karyawan';
require __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-xl rounded-lg bg-white p-6 shadow">
    <h1 class="mb-5 text-2xl font-bold">Edit Karyawan</h1>
    <?php if ($success !== ''): ?><div class="mb-4 rounded border border-green-500 bg-green-100 p-3 text-green-700"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="mb-4 rounded border border-red-500 bg-red-100 p-3 text-red-700"><ul class="list-inside list-disc"><?php foreach (array_unique($errors) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <?php foreach (['nik' => 'NIK', 'nama' => 'Nama', 'jabatan' => 'Jabatan', 'no_hp' => 'No HP', 'email' => 'Email'] as $name => $label): ?>
            <label class="block font-medium"><?= e($label) ?><input class="mt-1 w-full rounded border p-2" name="<?= e($name) ?>" value="<?= e($values[$name]) ?>" <?= $name === 'email' ? 'type="email"' : 'type="text"' ?> required></label>
        <?php endforeach; ?>
        <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Simpan Perubahan</button>
        <a class="ml-2 text-indigo-700 hover:underline" href="<?= e(appUrl('karyawan/index.php')) ?>">Kembali</a>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
