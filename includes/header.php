<?php

require_once __DIR__ . '/auth_check.php';
$pageTitle = $pageTitle ?? 'Sistem Karyawan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Sistem Karyawan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css')) ?>">
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <nav class="bg-indigo-700 text-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <a class="text-lg font-bold" href="<?= e(appUrl('')) ?>">Sistem Karyawan</a>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <?php if (isAdmin()): ?>
                    <a class="hover:underline" href="<?= e(appUrl('admin/index.php')) ?>">Dashboard</a>
                    <a class="hover:underline" href="<?= e(appUrl('karyawan/index.php')) ?>">Karyawan</a>
                    <a class="hover:underline" href="<?= e(appUrl('admin/absensi.php')) ?>">Absensi</a>
                    <a class="hover:underline" href="<?= e(appUrl('admin/laporan.php')) ?>">Laporan</a>
                    <a class="hover:underline" href="<?= e(appUrl('admin/izin.php')) ?>">Pengajuan Izin</a>
                    <a class="hover:underline" href="<?= e(appUrl('auth/logout.php')) ?>">Logout</a>
                <?php elseif (isKaryawan()): ?>
                    <a class="hover:underline" href="<?= e(appUrl('absensi/index.php')) ?>">Absensi</a>
                    <a class="hover:underline" href="<?= e(appUrl('auth/logout.php')) ?>">Logout</a>
                <?php else: ?>
                    <a class="hover:underline" href="<?= e(appUrl('auth/login.php')) ?>">Login</a>
                    <a class="hover:underline" href="<?= e(appUrl('auth/register.php')) ?>">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="mx-auto max-w-5xl px-4 py-8">
