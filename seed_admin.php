<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/config/database.php';

$username = 'admin';
$adminPhone = '6281234567890';
$placeholderPassword = '!SET_VIA_SETUP_ADMIN!';

$pdo->beginTransaction();

try {
    $select = $pdo->prepare(
        'SELECT id, password FROM admin WHERE username = :username FOR UPDATE'
    );
    $select->execute(['username' => $username]);
    $admin = $select->fetch();

    if ($admin && $admin['password'] !== $placeholderPassword) {
        $pdo->commit();
        echo "Akun admin sudah memiliki password. Tidak ada perubahan.\n";
        exit;
    }

    $password = bin2hex(random_bytes(16));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($admin) {
        $update = $pdo->prepare(
            'UPDATE admin SET password = :password WHERE id = :id'
        );
        $update->execute([
            'password' => $passwordHash,
            'id' => $admin['id'],
        ]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO admin (username, password, no_hp)
             VALUES (:username, :password, :no_hp)'
        );
        $insert->execute([
            'username' => $username,
            'password' => $passwordHash,
            'no_hp' => $adminPhone,
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

echo "Akun admin berhasil dibuat.\n";
echo "Username: {$username}\n";
echo "Password (simpan sekarang): {$password}\n";
echo "Jangan bagikan password ini dan hapus seed_admin.php setelah digunakan.\n";
