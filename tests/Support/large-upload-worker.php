<?php

declare(strict_types=1);

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$directory = sys_get_temp_dir() . '/b24cli-large-' . bin2hex(random_bytes(5));
$filesystem = new Symfony\Component\Filesystem\Filesystem();
$filesystem->mkdir($directory);
$path = $directory . '/big.bin';
$size = (int) ($argv[1] ?? 500000000);
$handle = fopen($path, 'wb');
ftruncate($handle, $size);
// Nonzero bytes near a chunk boundary and EOF check exact Base64 grouping/padding.
fseek($handle, 59998);
fwrite($handle, "\0\xff\x01TEST");
fseek($handle, $size - 5);
fwrite($handle, "END\0\xff");
fclose($handle);
$expectedHash = hash_file('sha256', $path);
$encodedLength = intdiv($size, 3) * 4 + ($size % 3 === 0 ? 0 : 4);
$wireBytes = 0;
$observedHash = '';
$calls = [];
$http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$wireBytes, &$observedHash, &$calls, $encodedLength): MockResponse {
    $calls[] = $url;
    if (basename(parse_url($url, PHP_URL_PATH)) === 'scope') {
        return new MockResponse('{"result":["task","disk"]}');
    }

    if (!str_contains(strtolower($url), 'disk.folder.uploadfile')) {
        return new MockResponse('{"result":true}');
    }

    $prefix = '{"id":456,"data":{"NAME":"big.bin"},"generateUniqueName":true,"fileContent":["big.bin","';
    $prefixBuffer = '';
    $suffix = '';
    $remaining = $encodedLength;
    $carry = '';
    $hash = hash_init('sha256');
    $body = $options['body'];
    while ('' !== $chunk = $body(16372)) {
        $wireBytes += strlen($chunk);
        if (strlen($prefixBuffer) < strlen($prefix)) {
            $take = min(strlen($chunk), strlen($prefix) - strlen($prefixBuffer));
            $prefixBuffer .= substr($chunk, 0, $take);
            $chunk = substr($chunk, $take);
        }

        $take = min(strlen($chunk), $remaining);
        $carry .= substr($chunk, 0, $take);
        $remaining -= $take;
        $chunk = substr($chunk, $take);
        $aligned = intdiv(strlen($carry), 4) * 4;
        if ($aligned > 0) {
            $decoded = base64_decode(substr($carry, 0, $aligned), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid streamed Base64.');
            }

            hash_update($hash, $decoded);
            $carry = substr($carry, $aligned);
        }

        $suffix .= $chunk;
    }

    if ($prefixBuffer !== $prefix || $suffix !== '"]}' || $remaining !== 0 || $carry !== '') {
        throw new RuntimeException('Invalid streamed JSON framing/length.');
    }

    $headers = implode("\n", $options['headers']);
    if (!str_contains(strtolower($headers), 'content-length: ' . $wireBytes)) {
        throw new RuntimeException('Wire Content-Length differs.');
    }

    $observedHash = hash_final($hash);
    return new MockResponse('{"result":{"ID":"789","NAME":"big.bin"}}');
});
try {
    $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, new SpyConnectionResolver(), false, $http);
    $arguments = ['--config-dir', $directory . '/config', '--json', 'task:file:attach', '123', '--file', $path, '--folder-id', '456'];
    if (($argv[2] ?? '') === 'config') {
        $config = $directory . '/runtime.json';
        $filesystem->dumpFile($config, json_encode(['maxUploadBytes' => $size], JSON_THROW_ON_ERROR));
        $arguments = ['--config', $config, ...$arguments];
    }

    $run = ConsoleHarness::run($app, $arguments);
    if ($run->status !== 0 || $observedHash !== $expectedHash || count($calls) !== 3) {
        throw new RuntimeException('Large upload failed: ' . $run->stdout . $run->stderr);
    }

    echo json_encode(['sizeBytes' => $size, 'wireBytes' => $wireBytes, 'hashMatched' => true, 'memoryLimit' => ini_get('memory_limit'), 'peakMemoryBytes' => memory_get_peak_usage(true), 'apiCalls' => 3], JSON_THROW_ON_ERROR), "\n";
} finally {
    $filesystem->remove($directory);
}
