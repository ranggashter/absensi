<?php

require_once __DIR__ . '/../includes/auth_check.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl(isAdmin() ? 'admin/index.php' : 'absensi/index.php'));
    exit;
}

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

$errors = [];
$success = '';
$values = [
    'nik' => '',
    'nama' => '',
    'jabatan' => '',
    'no_hp' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['konfirmasi_password'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    }
    foreach ($values as $label => $value) {
        if ($value === '') {
            $errors[] = 'Semua kolom wajib diisi.';
            break;
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
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Password dan konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $check = $pdo->prepare(
            'SELECT nik, email FROM karyawan WHERE nik = :nik OR email = :email'
        );
        $check->execute(['nik' => $values['nik'], 'email' => $values['email']]);
        $duplicates = $check->fetchAll();
        foreach ($duplicates as $duplicate) {
            if ($duplicate['nik'] === $values['nik']) {
                $errors[] = 'NIK sudah terdaftar.';
            }
            if (strcasecmp($duplicate['email'], $values['email']) === 0) {
                $errors[] = 'Email sudah terdaftar.';
            }
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
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'NIK atau email sudah digunakan. Periksa kembali data Anda.';
            } else {
                throw $exception;
            }
        }

        if (!$errors) {
            $employeeMessage = "REGISTRASI BERHASIL\n"
                . "Halo {$values['nama']}.\n"
                . "Data Anda telah berhasil terdaftar sebagai karyawan.\n"
                . "NIK: {$values['nik']}\n"
                . "Jabatan: {$values['jabatan']}\n"
                . "Silakan login menggunakan akun yang telah dibuat.\n"
                . "Terima kasih.";
            $adminMessage = "NOTIFIKASI KARYAWAN BARU\n"
                . "Telah terdaftar karyawan baru:\n"
                . "Nama: {$values['nama']}\n"
                . "NIK: {$values['nik']}\n"
                . "Jabatan: {$values['jabatan']}\n"
                . "No. HP: {$values['no_hp']}\n"
                . "Silakan cek sistem untuk melihat data lengkap.";

            $waErrors = [];
            foreach ([
                [$values['no_hp'], $employeeMessage],
                [nomorAdmin(), $adminMessage],
            ] as [$phone, $message]) {
                try {
                    $result = kirimWA($phone, $message);
                    if (!$result['success']) {
                        $waErrors[] = 'API Fonnte menolak salah satu notifikasi.';
                    }
                } catch (Throwable $exception) {
                    error_log('Notifikasi registrasi gagal: ' . $exception->getMessage());
                    $waErrors[] = 'Pengiriman notifikasi WhatsApp gagal.';
                }
            }

            $success = 'Registrasi berhasil. Silakan login.';
            if ($waErrors) {
                $success .= ' Data tersimpan, tetapi ' . implode(' ', array_unique($waErrors));
            }
            $values = array_fill_keys(array_keys($values), '');
        }
    }
}

$pageTitle = 'Registrasi Karyawan';
require __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-xl rounded-lg bg-white p-6 shadow">
    <h1 class="mb-5 text-2xl font-bold">Registrasi Karyawan</h1>
    <?php if ($success !== ''): ?>
        <div class="mb-4 rounded border border-green-500 bg-green-100 p-3 text-green-700"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="mb-4 rounded border border-red-500 bg-red-100 p-3 text-red-700">
            <ul class="list-inside list-disc"><?php foreach (array_unique($errors) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <?php foreach (['nik' => 'NIK', 'nama' => 'Nama', 'jabatan' => 'Jabatan', 'no_hp' => 'No HP', 'email' => 'Email'] as $name => $label): ?>
            <label class="block font-medium"><?= e($label) ?>
                <input class="mt-1 w-full rounded border p-2" name="<?= e($name) ?>" value="<?= e($values[$name]) ?>" <?= $name === 'email' ? 'type="email"' : 'type="text"' ?> required>
            </label>
        <?php endforeach; ?>
        <label class="block font-medium">Password
            <input class="mt-1 w-full rounded border p-2" type="password" name="password" minlength="8" required>
        </label>
        <label class="block font-medium">Konfirmasi Password
            <input class="mt-1 w-full rounded border p-2" type="password" name="konfirmasi_password" minlength="8" required>
        </label>
        <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Daftar</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
