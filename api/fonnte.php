<?php

function normalisasiNomor(string $nomor): string
{
    $nomor = preg_replace('/\D+/', '', $nomor) ?? '';

    if ($nomor === '') {
        throw new InvalidArgumentException('Nomor WhatsApp tidak valid.');
    }

    if ($nomor[0] === '0') {
        return '62' . substr($nomor, 1);
    }

    if ($nomor[0] === '8') {
        return '62' . $nomor;
    }

    return $nomor;
}

function kirimWA(string $nomor, string $pesan): array
{
    if (!extension_loaded('curl')) {
        throw new RuntimeException('Ekstensi cURL PHP belum aktif.');
    }

    $config = require __DIR__ . '/../config/config.php';
    $token = trim($config['fonnte']['token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('Token Fonnte belum dikonfigurasi.');
    }

    $target = normalisasiNomor($nomor);
    $handle = curl_init('https://api.fonnte.com/send');
    if ($handle === false) {
        throw new RuntimeException('Tidak dapat memulai koneksi cURL.');
    }

    $optionsSet = curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'target' => $target,
            'message' => $pesan,
        ]),
        CURLOPT_HTTPHEADER => ['Authorization: ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if (!$optionsSet) {
        $error = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException('Gagal mengonfigurasi cURL: ' . $error);
    }

    $response = curl_exec($handle);
    if ($response === false) {
        $error = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException('Gagal mengirim WhatsApp: ' . $error);
    }

    $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    $decodedResponse = json_decode($response, true);

    return [
        'success' => $httpStatus >= 200
            && $httpStatus < 300
            && is_array($decodedResponse)
            && ($decodedResponse['status'] ?? false) === true,
        'response' => $response,
    ];
}

function nomorAdmin(): string
{
    $config = require __DIR__ . '/../config/config.php';

    return normalisasiNomor((string) ($config['fonnte']['admin_phone'] ?? ''));
}
