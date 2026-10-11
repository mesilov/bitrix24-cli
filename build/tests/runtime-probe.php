<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (($argv[1] ?? '') === 'certificate') {
    $directory = $argv[2];
    $options = ['config' => $directory . '/openssl.cnf', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
    $key = openssl_pkey_new($options);
    $csr = openssl_csr_new(['commonName' => 'localhost'], $key, $options);
    $certificate = openssl_csr_sign($csr, null, $key, 1, $options + ['x509_extensions' => 'server']);
    if ($key === false || $csr === false || $certificate === false) {
        throw new RuntimeException('Cannot create HTTPS fixture.');
    }
    openssl_pkey_export($key, $keyPem, null, $options);
    openssl_x509_export($certificate, $certificatePem);
    file_put_contents($directory . '/key.pem', $keyPem);
    file_put_contents($directory . '/cert.pem', $certificatePem);
    exit(0);
}

if (($argv[1] ?? '') === 'https') {
    $curl = curl_init($argv[2]);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CAINFO => $argv[3],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_NOPROXY => '*',
    ]);
    $body = curl_exec($curl);
    if ($body === false) {
        fwrite(STDERR, curl_error($curl) . PHP_EOL);
    }
    echo json_encode(['body' => $body, 'errno' => curl_errno($curl)], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($body === false ? 1 : 0);
}

$lock = json_decode(file_get_contents(dirname(__DIR__) . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$required = ['phar', 'zlib', 'openssl', 'mbstring'];
foreach ($lock['packages'] as $package) {
    foreach ($package['require'] ?? [] as $name => $constraint) {
        if (str_starts_with($name, 'ext-')) {
            $required[] = substr($name, 4);
        }
    }
}
foreach (array_unique($required) as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException('Missing production extension: ' . $extension);
    }
}
if (!class_exists(Bitrix24\SDK\Core\ApiClient::class)) {
    throw new RuntimeException('SDK autoload is broken.');
}
if (bcadd('0.1', '0.2', 1) !== '0.3' || Normalizer::normalize("е\u{0308}") !== 'ё') {
    throw new RuntimeException('BCMath/ICU fixture failed.');
}
$unicode = new Symfony\Component\String\UnicodeString('Привет, Битрикс24');
if ($unicode->upper()->toString() !== 'ПРИВЕТ, БИТРИКС24') {
    throw new RuntimeException('Unicode fixture failed.');
}
echo json_encode([
    'php' => PHP_VERSION,
    'version' => Bitrix24\CLI\BuildVersion::version(),
    'extensions' => array_values(array_unique($required)),
    'unicode' => $unicode->toString(),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
