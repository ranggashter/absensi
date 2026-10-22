<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 2) {
    fwrite(STDERR, "Penggunaan: php test_wa.php <nomor-WhatsApp>\n");
    exit(1);
}

require __DIR__ . '/api/fonnte.php';

$result = kirimWA(
    $argv[1],
    'Tes koneksi WhatsApp dari Sistem Karyawan.'
);

if (!$result['success']) {
    fwrite(STDERR, "Pengiriman gagal. Respons Fonnte: {$result['response']}\n");
    exit(1);
}

echo "Pesan uji berhasil diterima oleh API Fonnte.\n";
