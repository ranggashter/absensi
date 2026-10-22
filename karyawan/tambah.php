<?php

require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

$errors = [];
$values = ['nik' => '', 'nama' => '', 'jabatan' => '', 'no_hp' => '', 'email' => ''];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['konfirmasi_password'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    }
    if (in_array('', $values, true)) {
        $errors[] = 'Semua kolom wajib diisi.';
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
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Password dan konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM karyawan WHERE nik = :nik OR email = :email');
        $check->execute(['nik' => $values['nik'], 'email' => $values['email']]);
        if ($check->fetch()) {
            $errors[] = 'NIK atau email sudah digunakan.';
        }
    }

    if (!$errors) {
        try {
            $insert = $pdo->prepare(
                'INSERT INTO karyawan (nik, nama, jabatan, no_hp, email, password)
                 VALUES (:nik, :nama, :jabatan, :no_hp, :email, :password)'
            );
            $insert->execute([
                'nik' => $values['nik'],
                'nama' => $values['nama'],
                'jabatan' => $values['jabatan'],
                'no_hp' => normalisasiNomor($values['no_hp']),
                'email' => $values['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            header('Location: ' . appUrl('karyawan/index.php'));
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'NIK atau email sudah digunakan.';
            } else {
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Tambah Karyawan';
require __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-xl rounded-lg bg-white p-6 shadow">
    <h1 class="mb-5 text-2xl font-bold">Tambah Karyawan</h1>
    <?php if ($errors): ?><div class="mb-4 rounded border border-red-500 bg-red-100 p-3 text-red-700"><ul class="list-inside list-disc"><?php foreach (array_unique($errors) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <?php foreach (['nik' => 'NIK', 'nama' => 'Nama', 'jabatan' => 'Jabatan', 'no_hp' => 'No HP', 'email' => 'Email'] as $name => $label): ?>
            <label class="block font-medium"><?= e($label) ?><input class="mt-1 w-full rounded border p-2" name="<?= e($name) ?>" value="<?= e($values[$name]) ?>" <?= $name === 'email' ? 'type="email"' : 'type="text"' ?> required></label>
        <?php endforeach; ?>
        <label class="block font-medium">Password<input class="mt-1 w-full rounded border p-2" type="password" name="password" minlength="8" required></label>
        <label class="block font-medium">Konfirmasi Password<input class="mt-1 w-full rounded border p-2" type="password" name="konfirmasi_password" minlength="8" required></label>
        <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Simpan</button>
        <a class="ml-2 text-indigo-700 hover:underline" href="<?= e(appUrl('karyawan/index.php')) ?>">Kembali</a>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
