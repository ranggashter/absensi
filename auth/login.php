<?php

require_once __DIR__ . '/../includes/auth_check.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . appUrl(isAdmin() ? 'admin/index.php' : 'absensi/index.php'));
    exit;
}

require __DIR__ . '/../config/database.php';

$error = '';
$identifier = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Masukkan username/email dan password.';
    } else {
        $adminQuery = $pdo->prepare('SELECT id, username, password FROM admin WHERE username = :username');
        $adminQuery->execute(['username' => $identifier]);
        $admin = $adminQuery->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $admin['id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['nama'] = $admin['username'];
            header('Location: ' . appUrl('admin/index.php'));
            exit;
        }

        $employeeQuery = $pdo->prepare(
            'SELECT id, nik, nama, password FROM karyawan WHERE email = :email'
        );
        $employeeQuery->execute(['email' => $identifier]);
        $employee = $employeeQuery->fetch();

        if ($employee && password_verify($password, $employee['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $employee['id'];
            $_SESSION['role'] = 'karyawan';
            $_SESSION['nama'] = $employee['nama'];
            $_SESSION['nik'] = $employee['nik'];
            header('Location: ' . appUrl('absensi/index.php'));
            exit;
        }

        $error = 'Username/email atau password salah.';
    }
}

$pageTitle = 'Login';
require __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-md rounded-lg bg-white p-6 shadow">
    <h1 class="mb-5 text-2xl font-bold">Login</h1>
    <?php if ($error !== ''): ?>
        <div class="mb-4 rounded border border-red-500 bg-red-100 p-3 text-red-700"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label class="block font-medium">Email karyawan atau username admin
            <input class="mt-1 w-full rounded border p-2" type="text" name="identifier" value="<?= e($identifier) ?>" autocomplete="username" required>
        </label>
        <label class="block font-medium">Password
            <input class="mt-1 w-full rounded border p-2" type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700" type="submit">Login</button>
        <a class="ml-2 text-indigo-700 hover:underline" href="<?= e(appUrl('auth/register.php')) ?>">Daftar karyawan</a>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
